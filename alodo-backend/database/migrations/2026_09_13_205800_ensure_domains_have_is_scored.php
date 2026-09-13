<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('domains', 'is_scored')) {
            Schema::table('domains', function (Blueprint $table): void {
                $table->boolean('is_scored')->default(true);
            });
        }
    }

    public function down(): void
    {
        // Preserve the flag when it was introduced by the original migration.
    }
};
