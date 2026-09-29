<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Persistence\MigrationCatalog;

/**
 * Record which principal ran the domain for an approval (D-002 / D-006):
 * the deciding user on accept, a SystemActor on resume. Additive and nullable,
 * so existing rows stay valid and fresh installs converge after the create
 * migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = MigrationCatalog::TABLE_APPROVALS;
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'executor_actor_type')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('executor_actor_type', 64)->nullable();
            $blueprint->string('executor_actor_id', 191)->nullable();
        });
    }

    public function down(): void
    {
        $table = MigrationCatalog::TABLE_APPROVALS;
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'executor_actor_type')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn(['executor_actor_type', 'executor_actor_id']);
        });
    }
};
