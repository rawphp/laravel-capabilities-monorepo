<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Models\TableNames;

/**
 * Per-round LLM usage on turns: list of {latency_ms, input_tokens?, output_tokens?}.
 * hasColumn guards keep this idempotent for hosts on either side of the change.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = TableNames::turns();
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'usage')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->json('usage')->nullable();
        });
    }

    public function down(): void
    {
        $table = TableNames::turns();
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'usage')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('usage');
        });
    }
};
