<?php

namespace App\Services;

use App\Models\Diagnostic;
use App\Models\Question;
use DomainException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use LogicException;

class DiagnosticScoringService
{
    public function calculate(Diagnostic $diagnostic): array
    {
        if (! $diagnostic->exists) {
            throw new DomainException('Diagnostic not found.');
        }

        $config = config('diagnostic_scoring');
        if (! is_array($config) || empty($config['version']) || empty($config['questions']) || empty($config['domains'])) {
            throw new LogicException('Scoring configuration is missing');
        }

        $settings = $config['calculation'];
        if ($settings['domain_method'] !== 'earned_points_over_evaluable_max_points'
            || $settings['global_method'] !== 'equal_domain_mean'
            || $settings['rounding'] !== 'half_up'
            || $settings['require_all_scored_domains_for_global'] !== true
            || $settings['exclude_null_points_from_numerator_and_denominator'] !== true
            || $settings['round_only_final_scores'] !== true) {
            throw new LogicException('Calculation method or settings not supported.');
        }
        $minimum = $settings['min_evaluable_questions_per_domain'];
        if (! is_int($minimum) || $minimum < 1 || $settings['scale'] !== 100 || ! is_int($settings['precision']) || $settings['precision'] < 0 || $settings['precision'] > 6) {
            throw new LogicException('Calculation settings are invalid.');
        }

        $questions = Question::with('domain')->orderBy('id')->get();
        $codes = $questions->pluck('question_code')->all();
        if (count($codes) !== count(array_unique($codes)) || in_array(null, $codes, true)
            || array_diff($codes, array_keys($config['questions']))
            || array_diff(array_keys($config['questions']), $codes)) {
            throw new LogicException('Question codes in database do not match scoring configuration.');
        }

        $responses = $diagnostic->responses()->get()->keyBy('question_id');
        $domains = [];
        $domainIds = [];
        foreach ($config['domains'] as $code => $definition) {
            $expected = array_keys(array_filter($config['questions'], fn (array $rule): bool => $rule['domain'] === $code));
            if (array_diff($expected, $definition['question_codes']) || array_diff($definition['question_codes'], $expected) || count($expected) !== count($definition['question_codes'])) {
                throw new LogicException('Question codes in configuration do not match expected for domain '.$code);
            }
            $domains[$code] = ['is_scored' => $definition['is_scored'], 'score' => null, 'earned_points' => 0, 'max_points' => 0,
                'coverage' => ['evaluated' => 0, 'total' => count($expected)], 'questions' => []];
        }

        foreach ($questions as $question) {
            $code = $question->question_code;
            $rule = $config['questions'][$code];
            $domainCode = $rule['domain'];
            if (! isset($domains[$domainCode]) || ! $question->domain
                || $question->type !== $rule['type']
                || $question->domain->is_scored !== $rule['is_scored']
                || $domains[$domainCode]['is_scored'] !== $rule['is_scored']) {
                throw new LogicException('Domain or type incoherent for question '.$code);
            }
            if (isset($domainIds[$domainCode]) && $domainIds[$domainCode] !== $question->domain_id) {
                throw new LogicException('Un domaine du barème est réparti sur plusieurs domaines en base.');
            }

            $domainIds[$domainCode] = $question->domain_id;
            if (count(array_unique($domainIds)) !== count($domainIds)) {
                throw new LogicException('Plusieurs domaines du barème partagent le même domaine en base.');
            }
            $response = $responses->get($question->id);
            if (! $response) {
                throw new DomainException('Réponse manquante pour '.$code);
            }
            $value = $response->valeur;
            $allowed = array_column($question->options ?? [], 'value');
            $validation = match ($question->type) {
                'text' => ['required', 'string', 'max:5000'],
                'unique_choice' => ['required', 'string', Rule::in($allowed)],
                default => throw new LogicException('Type non pris en charge : '.$question->type),
            };
            if (Validator::make(['value' => $value], ['value' => $validation])->fails()
                || ($question->type === 'unique_choice' && ! in_array($value, $allowed, true))) {
                throw new DomainException('Réponse invalide pour '.$code);
            }

            $points = null;
            $reason = $rule['reason'] ?? null;
            $maximum = $rule['max_points'];
            if ($rule['is_scored']) {
                if ($question->type !== 'unique_choice' || ! is_int($maximum) || $maximum <= 0
                    || array_diff($allowed, array_keys($rule['options'])) || array_diff(array_keys($rule['options']), $allowed)) {
                    throw new LogicException('Barème non conforme pour la question '.$code);
                }
                foreach ($rule['options'] as $option) {
                    $p = $option['points'];
                    if (($p !== null && (! is_int($p) || $p < 0 || $p > $maximum || $option['reason'] !== null))
                        || ($p === null && ! isset($config['exclusion_reasons'][$option['reason']]))) {
                        throw new LogicException('Points ou motif invalides pour '.$code);
                    }
                }
                $points = $rule['options'][$value]['points'];
                $reason = $rule['options'][$value]['reason'];
            }
            if ($points === null && ! isset($config['exclusion_reasons'][$reason])) {
                throw new LogicException('Motif d’exclusion absent pour '.$code);
            }
            $domains[$domainCode]['questions'][$code] = [
                'question_id' => $question->id, 'question_code' => $code, 'answer' => $value,
                'points' => $points, 'max_points' => $maximum, 'included' => $points !== null,
                'exclusion_reason' => $reason, 'exclusion_label' => $reason === null ? null : $config['exclusion_reasons'][$reason],
            ];
            if ($points !== null) {
                $domains[$domainCode]['earned_points'] += $points;
                $domains[$domainCode]['max_points'] += $maximum;
                $domains[$domainCode]['coverage']['evaluated']++;
            }
        }

        $rawScores = [];
        $scoredCount = 0;
        foreach ($domains as $code => &$domain) {
            $domain['domain_id'] = $domainIds[$code] ?? null;
            $domain['status'] = $domain['is_scored'] ? 'insufficient_coverage' : 'not_scored';
            if (! $domain['is_scored']) {
                continue;
            }
            $scoredCount++;
            if ($domain['coverage']['evaluated'] >= $minimum && $domain['max_points'] > 0) {
                $raw = $domain['earned_points'] / $domain['max_points'] * $settings['scale'];
                $rawScores[] = $raw;
                $domain['score'] = round($raw, $settings['precision'], PHP_ROUND_HALF_UP);
                $domain['status'] = 'evaluated';
            }
        }
        unset($domain);
        $complete = $scoredCount > 0 && count($rawScores) === $scoredCount;

        return [
            'scoring_version' => $config['version'],
            'status' => $complete ? 'evaluated' : 'partial',
            'score' => $complete ? round(array_sum($rawScores) / $scoredCount, $settings['precision'], PHP_ROUND_HALF_UP) : null,
            'coverage' => ['evaluated_domains' => count($rawScores), 'scored_domains' => $scoredCount],
            'domains' => $domains,
        ];
    }
}
