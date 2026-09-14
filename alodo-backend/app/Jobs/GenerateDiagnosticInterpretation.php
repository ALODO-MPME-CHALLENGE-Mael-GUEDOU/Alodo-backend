<?php

namespace App\Jobs;

use App\Models\Result;
use App\Services\DiagnosticInterpretationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class GenerateDiagnosticInterpretation implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

    public function __construct(public int $resultId) {}

    public function handle(DiagnosticInterpretationService $service): void
    {
        $claimed = Result::whereKey($this->resultId)->where('analysis_status', 'pending')->update([
            'analysis_status' => 'processing', 'analysis_error' => null,
        ]);
        if (! $claimed) {
            return;
        }
        try {
            $result = Result::findOrFail($this->resultId);
            $analysis = $service->generate($result);
            $result->update(['analysis' => $analysis, 'analysis_status' => 'completed', 'analyzed_at' => now(), 'analysis_error' => null]);
        } catch (Throwable $exception) {
            $this->failed($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $reason = match (true) {
            $exception instanceof ConnectionException => 'connection_or_timeout',
            $exception instanceof ValidationException => 'invalid_analysis_fields',
            $exception instanceof JsonException => 'invalid_json',
            default => match ($exception?->getMessage()) {
                'Gemini configuration missing or invalid.' => 'invalid_gemini_configuration',
                'Interpretation snapshot missing.' => 'missing_interpretation_snapshot',
                'Gemini returned an unsuccessful or incomplete response.' => 'provider_rejected',
                'Invalid interpretation payload.' => 'invalid_analysis_payload',
                default => 'internal_or_provider_error',
            },
        };
        Log::error('diagnostic.interpretation.failed', [
            'result_id' => $this->resultId,
            'reason' => $reason,
            'exception_class' => $exception ? get_class($exception) : null,
        ]);
        Result::whereKey($this->resultId)->whereIn('analysis_status', ['pending', 'processing'])->update([
            'analysis_status' => 'failed',
            'analysis_error' => 'Interprétation indisponible. Vérifiez la configuration Gemini puis relancez l’analyse.',
        ]);
    }
}
