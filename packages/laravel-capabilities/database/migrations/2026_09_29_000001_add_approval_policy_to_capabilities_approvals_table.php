<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Persistence\MigrationCatalog;

/**
 * Store the capability's approvalPolicy on the approval row (D-006) so accept / reject
 * enforce the rule the capability declared, not only the global default. Additive and
 * nullable: rows without a policy fall back to `approval.default_policy`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = MigrationCatalog::TABLE_APPROVALS;
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'approval_policy')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('approval_policy', 191)->nullable();
        });
    }

    public function down(): void
    {
        $table = MigrationCatalog::TABLE_APPROVALS;
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'approval_policy')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('approval_policy');
        });
    }
};
