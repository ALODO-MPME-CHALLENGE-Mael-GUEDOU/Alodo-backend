<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table): void {
            $table->decimal('score', 5, 2)->nullable()->change();
            $table->json('analysis')->nullable()->change();
            $table->string('scoring_version')->nullable();
            $table->json('scoring_details')->nullable();
            $table->json('interpretation_input')->nullable();
            $table->string('analysis_status')->default('pending');
            $table->string('analysis_error')->nullable();
            $table->string('analysis_provider')->nullable();
            $table->string('analysis_model')->nullable();
            $table->string('prompt_version')->nullable();
            $table->timestamp('analyzed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table): void {
            $table->dropColumn(['scoring_version', 'scoring_details', 'interpretation_input', 'analysis_status', 'analysis_error', 'analysis_provider', 'analysis_model', 'prompt_version', 'analyzed_at']);
        });
    }
};
