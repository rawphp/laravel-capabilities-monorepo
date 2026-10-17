<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Persistence\MigrationCatalog;

/**
 * Store the agent turn an approval was requested from, so the approved run's
 * CapabilityContext::agent() names the same conversation and turn.
 * Additive and nullable: rows without it run with agent() null.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = MigrationCatalog::TABLE_APPROVALS;
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'agent_json')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->json('agent_json')->nullable();
        });
    }

    public function down(): void
    {
        $table = MigrationCatalog::TABLE_APPROVALS;
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'agent_json')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('agent_json');
        });
    }
};
