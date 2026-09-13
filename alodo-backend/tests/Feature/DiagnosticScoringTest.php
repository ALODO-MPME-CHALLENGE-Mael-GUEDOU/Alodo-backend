<?php

namespace Tests\Feature;

use App\Models\Diagnostic;
use App\Models\Question;
use App\Models\User;
use App\Services\DiagnosticScoringService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class DiagnosticScoringTest extends TestCase
{
    use RefreshDatabase;

    private function diagnostic(int $points = 3): Diagnostic
    {
        $this->seed();
        $diagnostic = User::where('email', 'entreprise@alodo.test')->firstOrFail()->diagnostics;
        foreach (Question::all() as $question) {
            $rule = config('diagnostic_scoring.questions.'.$question->question_code);
            $value = $question->type === 'text' ? 'Atelier de couture' : $question->options[0]['value'];
            if ($rule['is_scored']) {
                $value = array_key_first(array_filter($rule['options'], fn (array $option): bool => $option['points'] === $points));
            }
            $diagnostic->responses()->create(['question_id' => $question->id, 'valeur' => $value]);
        }

        return $diagnostic;
    }

    private function answer(Diagnostic $diagnostic, string $code, string $value): void
    {
        $question = Question::where('question_code', $code)->firstOrFail();
        $response = $diagnostic->responses()->where('question_id', $question->id)->firstOrFail();
        $response->update(['valeur' => $value]);
    }

    public function test_maximum_is_repeatable_and_does_not_write_result_or_complete_diagnostic(): void
    {
        $d = $this->diagnostic();
        $service = new DiagnosticScoringService;
        $result = $service->calculate($d);
        $this->assertSame(100.0, $result['score']);
        $this->assertSame($result, $service->calculate($d));
        $this->assertSame('1.0.0', $result['scoring_version']);
        $this->assertNull($result['domains']['general']['score']);
        $this->assertSame('baseline', $d->fresh()->status);
        $this->assertDatabaseCount('results', 0);
    }

    public function test_zero_is_evaluated_and_not_excluded(): void
    {
        $r = (new DiagnosticScoringService)->calculate($this->diagnostic(0));
        $this->assertSame(0.0, $r['score']);
        $this->assertSame(3, $r['domains']['finance']['coverage']['evaluated']);
    }

    public function test_exclusions_remove_maximum_and_global_uses_unrounded_domain_scores(): void
    {
        $d = $this->diagnostic(0);
        $this->answer($d, 'finance_marge', 'calcul');
        $this->answer($d, 'finance_creances', 'paiement_immediat');
        $r = (new DiagnosticScoringService)->calculate($d);
        $this->assertSame(33.33, $r['domains']['finance']['score']);
        $this->assertSame(6, $r['domains']['finance']['max_points']);
        $this->assertSame(11.11, $r['score']);
        $this->assertSame('non_applicable', $r['domains']['finance']['questions']['finance_creances']['exclusion_reason']);
    }

    public function test_insufficient_coverage_gives_null_global_without_division_by_zero(): void
    {
        $d = $this->diagnostic();
        foreach (['comptabilite_enregistrement', 'comptabilite_tracabilite', 'comptabilite_verification'] as $code) {
            $this->answer($d, $code, 'pas_operations');
        }
        $r = (new DiagnosticScoringService)->calculate($d);
        $this->assertNull($r['score']);
        $this->assertNull($r['domains']['comptabilite']['score']);
        $this->assertSame(0, $r['domains']['comptabilite']['max_points']);
    }

    public function test_missing_answer_fails(): void
    {
        $d = $this->diagnostic();
        $d->responses()->first()->delete();
        $this->expectException(DomainException::class);
        (new DiagnosticScoringService)->calculate($d);
    }

    public function test_unknown_option_fails(): void
    {
        $d = $this->diagnostic();
        $this->answer($d, 'finance_marge', 'unknown');
        $this->expectException(DomainException::class);
        (new DiagnosticScoringService)->calculate($d);
    }

    public function test_unknown_code_fails(): void
    {
        $d = $this->diagnostic();
        Question::first()->update(['question_code' => 'unknown']);
        $this->expectException(LogicException::class);
        (new DiagnosticScoringService)->calculate($d);
    }

    public function test_missing_option_rule_fails(): void
    {
        $d = $this->diagnostic();
        $rules = config('diagnostic_scoring.questions.finance_marge.options');
        unset($rules['estimation']);
        config(['diagnostic_scoring.questions.finance_marge.options' => $rules]);
        $this->expectException(LogicException::class);
        (new DiagnosticScoringService)->calculate($d);
    }

    public function test_domain_scoring_mismatch_fails(): void
    {
        $d = $this->diagnostic();
        Question::where('question_code', 'finance_marge')->first()->domain->update(['is_scored' => false]);
        $this->expectException(LogicException::class);
        (new DiagnosticScoringService)->calculate($d);
    }
}
