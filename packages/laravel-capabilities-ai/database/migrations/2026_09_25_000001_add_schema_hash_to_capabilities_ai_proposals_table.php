<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Models\TableNames;

/**
 * Target tool input-schema fingerprint stamped at proposal creation; accept refuses
 * with conflict/schema_changed when it no longer matches. Null = legacy row, no check.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = TableNames::proposals();
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'schema_hash')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('schema_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        $table = TableNames::proposals();
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'schema_hash')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('schema_hash');
        });
    }
};
