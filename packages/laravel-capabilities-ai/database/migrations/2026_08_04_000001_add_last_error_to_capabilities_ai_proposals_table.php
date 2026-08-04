<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Models\TableNames;

/**
 * Upgrade path: hosts who already ran the create migration before last_error
 * was added need this ALTER. Greenfield installs already get last_error from
 * 2026_08_02_000004_create_capabilities_ai_proposals_table (hasColumn guards
 * keep this migration idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = TableNames::proposals();
        if (! Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasColumn($table, 'last_error')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->text('last_error')->nullable();
        });
    }

    public function down(): void
    {
        $table = TableNames::proposals();
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, 'last_error')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('last_error');
        });
    }
};
