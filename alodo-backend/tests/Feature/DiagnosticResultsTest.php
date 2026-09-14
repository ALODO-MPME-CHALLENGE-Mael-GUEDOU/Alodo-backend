<?php

namespace Tests\Feature;

use App\Jobs\GenerateDiagnosticInterpretation;
use App\Models\Question;
use App\Models\Result;
use App\Models\User;
use App\Services\DiagnosticInterpretationService;
use App\Services\DiagnosticScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiagnosticResultsTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        $this->seed();
        Bus::fake();
        Http::preventStrayRequests();
        config(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test-model']);
        $user = User::where('email', 'entreprise@alodo.test')->firstOrFail();
        Sanctum::actingAs($user);
        $responses = Question::all()->map(fn (Question $question): array => [
            'question_id' => $question->id,
            'valeur' => $question->type === 'text' ? 'Atelier de couture' : $question->options[0]['value'],
        ])->all();

        return ['diagnostic_id' => $user->diagnostics->id, 'status' => 'completed', 'responses' => $responses];
    }

    private function analysis(): array
    {
        return [
            'summary' => 'Les pratiques déclarées sont à structurer.',
            'strengths' => [],
            'weaknesses' => [['kind' => 'observed', 'text' => 'La marge est inconnue.', 'question_codes' => ['finance_marge']]],
            'recommendations' => [['action' => 'Lister les coûts.', 'expected_benefit' => 'Mieux comprendre la marge.', 'question_codes' => ['finance_marge']]],
        ];
    }

    public function test_completion_persists_scoring_and_queues_interpretation(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/store/answers', $payload)->assertCreated()
            ->assertJsonPath('data.result.analysis_status', 'pending')
            ->assertJsonPath('data.result.scoring_details.score', 0)
            ->assertJsonMissingPath('data.result.interpretation_input');
        $result = Result::firstOrFail();
        $this->assertCount(12, $result->interpretation_input);
        $this->assertSame('1.0.0', $result->scoring_version);
        $this->assertSame('completed', $result->diagnostic->status);
        Bus::assertDispatched(GenerateDiagnosticInterpretation::class, fn ($job): bool => $job->resultId === $result->id);
        $this->getJson('/api/diagnostics/'.$payload['diagnostic_id'].'/result')->assertOk();
    }

    public function test_sync_completion_returns_persisted_analysis_without_a_worker(): void
    {
        $this->seed();
        config(['queue.default' => 'sync', 'services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test-model']);
        Http::preventStrayRequests();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($this->analysis())]]]]],
        ])]);
        $user = User::where('email', 'entreprise@alodo.test')->firstOrFail();
        Sanctum::actingAs($user);
        $responses = Question::all()->map(fn (Question $question): array => [
            'question_id' => $question->id,
            'valeur' => $question->type === 'text' ? 'Atelier de couture' : $question->options[0]['value'],
        ])->all();

        $this->postJson('/api/store/answers', [
            'diagnostic_id' => $user->diagnostics->id, 'status' => 'completed', 'responses' => $responses,
        ])->assertCreated()->assertJsonPath('data.result.analysis_status', 'completed')
            ->assertJsonPath('data.result.analysis.summary', $this->analysis()['summary']);

        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame($this->analysis(), Result::firstOrFail()->analysis);
        Http::assertSentCount(1);
    }

    public function test_partial_save_creates_no_result_or_job(): void
    {
        $payload = $this->payload();
        $payload['status'] = 'pending';
        $payload['responses'] = [$payload['responses'][0]];
        $this->postJson('/api/store/answers', $payload)->assertCreated();
        $this->assertDatabaseCount('results', 0);
        Bus::assertNothingDispatched();
    }

    public function test_incomplete_completion_rolls_back_answers_and_creates_no_job(): void
    {
        $payload = $this->payload();
        array_pop($payload['responses']);
        $this->postJson('/api/store/answers', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('responses', 0);
        $this->assertDatabaseCount('results', 0);
        Bus::assertNothingDispatched();
    }

    public function test_valid_ai_response_is_stored_without_changing_score_and_duplicate_job_is_ignored(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/store/answers', $payload)->assertCreated();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($this->analysis())]]]]],
        ])]);
        $result = Result::firstOrFail();
        $job = new GenerateDiagnosticInterpretation($result->id);
        $job->handle(new DiagnosticInterpretationService);
        $job->handle(new DiagnosticInterpretationService);
        $this->assertSame('completed', $result->fresh()->analysis_status);
        $this->assertSame($this->analysis(), $result->fresh()->analysis);
        $this->assertSame('0.00', $result->fresh()->score);
        $this->assertNotNull($result->fresh()->analyzed_at);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => ! str_contains($request->body(), 'entreprise@alodo.test'));
    }

    public function test_provider_failure_preserves_result_and_can_be_retried_by_owner_only(): void
    {
        Log::spy();
        $payload = $this->payload();
        $this->postJson('/api/store/answers', $payload)->assertCreated();
        Http::fake(['*' => Http::response(['error' => 'private provider error'], 503)]);
        $result = Result::firstOrFail();
        (new GenerateDiagnosticInterpretation($result->id))->handle(new DiagnosticInterpretationService);
        Log::shouldHaveReceived('error')->with('diagnostic.interpretation.provider_rejected', [
            'result_id' => $result->id,
            'http_status' => 503,
            'provider_status' => null,
            'finish_reason' => null,
            'block_reason' => null,
        ])->once();
        Log::shouldHaveReceived('error')->with('diagnostic.interpretation.failed', [
            'result_id' => $result->id,
            'reason' => 'provider_rejected',
            'exception_class' => \RuntimeException::class,
        ])->once();
        $this->assertSame('failed', $result->fresh()->analysis_status);
        $this->assertSame('0.00', $result->fresh()->score);
        $this->assertNull($result->fresh()->analysis);
        $this->postJson('/api/diagnostics/'.$payload['diagnostic_id'].'/interpretation/retry')->assertAccepted();
        $this->postJson('/api/diagnostics/'.$payload['diagnostic_id'].'/interpretation/retry')->assertConflict();
        $other = User::factory()->create(['role_id' => User::where('email', 'entreprise@alodo.test')->first()->role_id]);
        Sanctum::actingAs($other);
        $this->getJson('/api/diagnostics/'.$payload['diagnostic_id'].'/result')->assertNotFound();
        $this->postJson('/api/diagnostics/'.$payload['diagnostic_id'].'/interpretation/retry')->assertNotFound();
    }

    public function test_invalid_evidence_from_ai_is_rejected(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/store/answers', $payload)->assertCreated();
        $analysis = $this->analysis();
        $analysis['weaknesses'][0]['question_codes'] = ['invented_question'];
        Http::fake(['*' => Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($analysis)]]]]]])]);
        $result = Result::firstOrFail();
        (new GenerateDiagnosticInterpretation($result->id))->handle(new DiagnosticInterpretationService);
        $this->assertSame('failed', $result->fresh()->analysis_status);
        $this->assertNull($result->fresh()->analysis);
    }

    public function test_provider_schema_omits_list_bounds_but_local_validation_enforces_them(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/store/answers', $payload)->assertCreated();
        $analysis = $this->analysis();
        $analysis['recommendations'] = array_fill(0, 4, $analysis['recommendations'][0]);
        Http::fake(['*' => Http::response([
            'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($analysis)]]]]],
        ])]);
        $result = Result::firstOrFail();

        (new GenerateDiagnosticInterpretation($result->id))->handle(new DiagnosticInterpretationService);

        Http::assertSent(function ($request): bool {
            $schema = json_encode($request['generationConfig']['responseJsonSchema']);

            return ! str_contains($schema, 'minItems') && ! str_contains($schema, 'maxItems');
        });
        $this->assertSame('failed', $result->fresh()->analysis_status);
        $this->assertNull($result->fresh()->analysis);
        $this->assertSame('0.00', $result->fresh()->score);
    }

    public function test_scoring_failure_rolls_back_closure_without_dispatching_ai(): void
    {
        $payload = $this->payload();
        $this->mock(DiagnosticScoringService::class, function ($mock): void {
            $mock->shouldReceive('calculate')->once()->andThrow(new \LogicException('Invalid scoring configuration'));
        });
        $this->postJson('/api/store/answers', $payload)->assertStatus(500);
        $this->assertDatabaseCount('responses', 0);
        $this->assertDatabaseCount('results', 0);
        $this->assertDatabaseHas('diagnostics', ['id' => $payload['diagnostic_id'], 'status' => 'baseline']);
        Bus::assertNothingDispatched();
    }

    public function test_truncated_and_malformed_ai_responses_are_not_saved(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/store/answers', $payload)->assertCreated();
        $result = Result::firstOrFail();
        foreach ([
            ['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => [['text' => json_encode($this->analysis())]]]],
            ['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'not json']]]],
            ['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '{"summary":"Incomplete"}']]]],
        ] as $candidate) {
            $result->update(['analysis_status' => 'pending']);
            Http::fake(['*' => Http::response(['candidates' => [$candidate]])]);
            (new GenerateDiagnosticInterpretation($result->id))->handle(new DiagnosticInterpretationService);
            $this->assertSame('failed', $result->fresh()->analysis_status);
            $this->assertNull($result->fresh()->analysis);
        }
    }

    public function test_insufficient_coverage_persists_nullable_score(): void
    {
        $payload = $this->payload();
        $ids = Question::whereIn('question_code', ['comptabilite_enregistrement', 'comptabilite_tracabilite', 'comptabilite_verification'])->pluck('id')->all();
        foreach ($payload['responses'] as &$answer) {
            if (in_array($answer['question_id'], $ids, true)) {
                $answer['valeur'] = 'pas_operations';
            }
        }
        unset($answer);
        $this->postJson('/api/store/answers', $payload)->assertCreated()->assertJsonPath('data.result.score', null);
        $this->assertNull(Result::firstOrFail()->score);
    }
}
