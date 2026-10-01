<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Persistence\MigrationCatalog;

/**
 * Store the surface an approval was requested through (D-022). An HTTP caller
 * downgrade splits it from original_caller, and the approved run re-gates on it.
 * Additive and nullable: rows without it gate on original_caller.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = MigrationCatalog::TABLE_APPROVALS;
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'original_surface')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('original_surface', 64)->nullable();
        });
    }

    public function down(): void
    {
        $table = MigrationCatalog::TABLE_APPROVALS;
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'original_surface')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('original_surface');
        });
    }
};
