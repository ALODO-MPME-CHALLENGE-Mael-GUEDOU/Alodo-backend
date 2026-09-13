<?php

namespace App\Jobs;

use App\Models\Result;
use App\Services\DiagnosticInterpretationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
        Result::whereKey($this->resultId)->whereIn('analysis_status', ['pending', 'processing'])->update([
            'analysis_status' => 'failed',
            'analysis_error' => 'Interprétation indisponible. Vérifiez la configuration Gemini puis relancez l’analyse.',
        ]);
    }
}
