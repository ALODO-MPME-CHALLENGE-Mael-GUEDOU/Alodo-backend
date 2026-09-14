<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnswersRequest;
use App\Jobs\GenerateDiagnosticInterpretation;
use App\Models\Diagnostic;
use App\Models\Domain;
use App\Models\Question;
use App\Models\Result;
use App\Services\DiagnosticInterpretationService;
use App\Services\DiagnosticScoringService;
use App\TraitsApiResponseTrait;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Annotations as OA;
use Throwable;

class DiagnosticController extends Controller
{
    use TraitsApiResponseTrait;

    /**
     * @OA\Get(
     *     path="/api/display/questionnaire",
     *     operationId="indexQuestionnaire",
     *     summary="Charger le questionnaire et les réponses",
     *     description="DiagnosticController::indexQuestionnaire. Rôle user. Domaines non vides triés par ordre, questions par ordre puis id. Retourne les réponses du diagnostic connecté. Toutes les questions, y compris Général non noté, sont obligatoires à la finalisation. Un diagnostic completed reste consultable.",
     *     tags={"Diagnostic"},
     *     security={{"bearerAuth"={}}},
     *
     *     @OA\Response(response=200, description="Succès", @OA\JsonContent(type="object", @OA\Property(property="success", type="boolean", enum={true}), @OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/Questionnaire"), required={"success", "message", "data"})),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound")
     * )
     */
    public function indexQuestionnaire(Request $request): JsonResponse
    {
        $diagnostic = $request->user()->diagnostics()->first();

        if (! $diagnostic) {
            return $this->errorResponse('No diagnostic found for this user.', 404);
        }

        $domains = Domain::query()
            ->whereHas('questions')
            ->orderBy('ordre')
            ->with([
                'questions' => fn (HasMany $query) => $query->orderBy('ordre')->orderBy('id'),
                'questions.responses' => fn (HasMany $query) => $query->where('diagnostic_id', $diagnostic->id),
            ])
            ->get()
            ->map(fn (Domain $domain): array => [
                'id' => $domain->id,
                'intitule' => $domain->intitule,
                'description' => $domain->description,
                'ordre' => $domain->ordre,
                'is_scored' => $domain->is_scored,
                'questions' => $domain->questions->map(fn (Question $question): array => [
                    'id' => $question->id,
                    'intitule' => $question->intitule,
                    'type' => $question->type,
                    'ordre' => $question->ordre,
                    'options' => $question->options,
                    'valeur' => $question->responses->first()?->valeur,
                ])->values(),
            ])->values();

        return $this->successResponse([
            'diagnostic' => [
                'id' => $diagnostic->id,
                'status' => $diagnostic->status,
                'completed_at' => $diagnostic->completed_at,
            ],
            'domains' => $domains,
        ], $diagnostic->status === 'completed' ? 'Diagnostic completed.' : 'Questionnaire');
    }

    /**
     * @OA\Post(
     *     path="/api/store/answers",
     *     operationId="storeAnswers",
     *     summary="Enregistrer ou terminer le diagnostic",
     *     description="DiagnosticController::storeAnswers. Rôle user, propriétaire seulement. pending sauvegarde les réponses envoyées sans supprimer les autres. completed revalide toutes les réponses, calcule et persiste le résultat, termine le diagnostic puis déclenche le job IA. La finalisation est définitive. result=null pour pending. Pour completed, lire analysis_status : l’IA peut être en attente, en cours, terminée ou en échec. Un score global null est possible même avec toutes les réponses. Pas de tableau responses vide, même pour completed. Le barème actuel requiert les 12 questions configurées (texte/choix unique), malgré les 4 types acceptés par la validation des réponses.",
     *     tags={"Diagnostic"},
     *     security={{"bearerAuth"={}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AnswersRequest")),
     *
     *     @OA\Response(response=201, description="Succès", @OA\JsonContent(type="object", @OA\Property(property="success", type="boolean", enum={true}), @OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/AnswersData"), required={"success", "message", "data"})),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=422, ref="#/components/responses/Validation"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerError")
     * )
     */
    public function storeAnswers(AnswersRequest $request, DiagnosticScoringService $scoring)
    {
        $validated = $request->validated();

        $data = DB::transaction(function () use ($validated, $request, $scoring) {
            // Find the diagnostic belonging to the current user.
            $diagnostic = Diagnostic::query()
                ->where('id', $validated['diagnostic_id'])
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($diagnostic->status === 'completed') {
                throw ValidationException::withMessages([
                    'diagnostic_id' => 'Diagnostic already completed. No further answers can be submitted.',
                ]);
            }

            $responses = [];

            foreach ($validated['responses'] as $response) {
                $responses[] = $diagnostic->responses()->updateOrCreate(
                    [
                        'question_id' => $response['question_id'],
                    ],
                    [
                        'valeur' => $response['valeur'],
                    ]
                );
            }

            $status = $validated['status'];
            $result = null;

            if ($status === 'completed') {

                $questions = Question::all();

                if ($questions->isEmpty()) {
                    throw ValidationException::withMessages([
                        'status' => 'Cannot complete diagnostic: No questions available in the system.',
                    ]);
                }

                $savedResponses = $diagnostic->responses()
                    ->get()
                    ->keyBy('question_id');

                $values = [];
                $rules = [];

                foreach ($questions as $question) {
                    $field = "answers.{$question->id}";

                    $allowedValues = array_column(
                        $question->options ?? [],
                        'value'
                    );

                    $values[$question->id] =
                        $savedResponses->get($question->id)?->valeur;

                    $rules[$field] = match ($question->type) {
                        'unique_choice' => [
                            'required',
                            'string',
                            Rule::in($allowedValues),
                        ],
                        'multiple_choice' => [
                            'required',
                            'array',
                            'list',
                            'min:1',
                        ],
                        'text' => [
                            'required',
                            'string',
                            'max:5000',
                        ],
                        'number' => [
                            'required',
                            'numeric',
                        ],
                        default => ['required', Rule::in([])],
                    };

                    if ($question->type === 'multiple_choice') {
                        $rules["$field.*"] = [
                            'required',
                            'string',
                            'distinct',
                            Rule::in($allowedValues),
                        ];
                    }
                }

                Validator::make(
                    ['answers' => $values],
                    $rules,
                    [
                        'required' => 'A required answer is missing.',
                        'in' => 'The answer does not match the allowed choices.',
                    ]
                )->validate();
            }

            if ($status === 'completed') {
                $calculation = $scoring->calculate($diagnostic);
                $snapshot = $diagnostic->responses()->with('question.domain')->get()->map(function ($response): array {
                    $question = $response->question;

                    return [
                        'question_code' => $question->question_code,
                        'question' => $question->intitule,
                        'domain' => $question->domain->intitule,
                        'is_scored' => $question->domain->is_scored,
                        'options' => $question->options,
                        'answer' => $response->valeur,
                    ];
                })->all();
                $result = $diagnostic->result()->create([
                    'score' => $calculation['score'],
                    'scoring_version' => $calculation['scoring_version'],
                    'scoring_details' => $calculation,
                    'interpretation_input' => $snapshot,
                    'analysis' => null,
                    'analysis_status' => 'pending',
                    'analysis_provider' => 'gemini',
                    'analysis_model' => config('services.gemini.model'),
                    'prompt_version' => DiagnosticInterpretationService::PROMPT_VERSION,
                ]);
            }

            $diagnostic->update([
                'status' => $status,
                'completed_at' => $status === 'completed' ? now() : null,
            ]);

            return [
                'result' => $result,
                'responses' => $responses,
                'newStatus' => $diagnostic->status,
                'completed_at' => $diagnostic->completed_at,
            ];
        });

        if ($data['result']) {
            $this->dispatchInterpretation($data['result']);
            $data['result']->refresh();
        }

        return $this->successResponse(
            $data,
            'Responses saved successfully.',
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/diagnostics/{diagnostic}/result",
     *     operationId="showResult",
     *     summary="Consulter le résultat persisté",
     *     description="DiagnosticController::showResult. Rôle user, propriétaire seulement. 404 si aucun résultat. Le score est une chaîne décimale ou null ; scoring_details contient des nombres. Au moins 2 questions évaluables par domaine et les 3 domaines évaluables sont requis pour le score global. Général n’est pas noté. Lire régulièrement pendant analysis_status=pending/processing ; arrêter à completed/failed. Aucun appel IA n’est déclenché par cette lecture.",
     *     tags={"Résultats"},
     *     security={{"bearerAuth"={}}},
     *
     *     @OA\Parameter(name="diagnostic", in="path", required=true, description="ID du diagnostic appartenant au compte connecté, obtenu via le questionnaire. Ce n’est pas l’ID du résultat.", @OA\Schema(type="integer", minimum=1, example=1)),
     *
     *     @OA\Response(response=200, description="Succès", @OA\JsonContent(type="object", @OA\Property(property="success", type="boolean", enum={true}), @OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/Result"), required={"success", "message", "data"})),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound")
     * )
     */
    public function showResult(Request $request, int $diagnostic): JsonResponse
    {
        $owned = Diagnostic::whereKey($diagnostic)->where('user_id', $request->user()->id)->firstOrFail();

        return $this->successResponse($owned->result()->firstOrFail(), 'Diagnostic result.');
    }

    /**
     * @OA\Post(
     *     path="/api/diagnostics/{diagnostic}/interpretation/retry",
     *     operationId="retryInterpretation",
     *     summary="Relancer une interprétation échouée",
     *     description="DiagnosticController::retryInterpretation. Rôle user, propriétaire seulement. Aucun corps nécessaire. Accepte uniquement analysis_status=failed avec les données nécessaires. Ne recalcule pas le score. Le 202 confirme la relance, pas la réussite de l’IA ; relire le résultat. Le job nécessite un worker avec une connexion de queue asynchrone.",
     *     tags={"Résultats"},
     *     security={{"bearerAuth"={}}},
     *
     *     @OA\Parameter(name="diagnostic", in="path", required=true, description="ID du diagnostic appartenant au compte connecté, obtenu via le questionnaire. Ce n’est pas l’ID du résultat.", @OA\Schema(type="integer", minimum=1, example=1)),
     *
     *     @OA\Response(response=202, description="Succès", @OA\JsonContent(type="object", @OA\Property(property="success", type="boolean", enum={true}), @OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/Result"), required={"success", "message", "data"})),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=409, ref="#/components/responses/Conflict")
     * )
     */
    public function retryInterpretation(Request $request, int $diagnostic): JsonResponse
    {
        $owned = Diagnostic::whereKey($diagnostic)->where('user_id', $request->user()->id)->firstOrFail();
        $result = $owned->result()->firstOrFail();
        if (! $result->scoring_details || ! $result->interpretation_input) {
            return $this->errorResponse('Le résultat ne contient pas les données nécessaires à une interprétation.', 409);
        }
        $changed = Result::whereKey($result->id)->where('analysis_status', 'failed')->update([
            'analysis_status' => 'pending', 'analysis_error' => null,
            'analysis_model' => config('services.gemini.model'),
        ]);
        if (! $changed) {
            return $this->errorResponse('Seule une interprétation en échec peut être relancée.', 409);
        }
        $this->dispatchInterpretation($result);

        return $this->successResponse($result->fresh(), 'Interprétation relancée.', 202);
    }

    private function dispatchInterpretation(Result $result): void
    {
        try {
            Bus::dispatch(new GenerateDiagnosticInterpretation($result->id));
        } catch (Throwable $exception) {
            Result::whereKey($result->id)->where('analysis_status', 'pending')->update([
                'analysis_status' => 'failed',
                'analysis_error' => 'La tâche d’interprétation n’a pas pu être envoyée. Vous pouvez la relancer.',
            ]);
        }
    }
}
