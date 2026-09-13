<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('questions', 'question_code')) {
            Schema::table('questions', function (Blueprint $table): void {
                $table->string('question_code')->nullable();
            });
        }

        if (! Schema::hasIndex('questions', ['question_code'], 'unique')) {
            Schema::table('questions', function (Blueprint $table): void {
                $table->unique('question_code');
            });
        }
    }

    public function down(): void
    {
        // Preserve codes: the column may predate this compatibility migration.
    }
};
