<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Persistence\MigrationCatalog;

/**
 * Durable approval rows (D-006). Schema catalog is the unit-testable source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = MigrationCatalog::TABLE_APPROVALS;
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->string('id', 64)->primary();
            $blueprint->string('capability_name', 191);
            $blueprint->string('status', 32)->index();
            $blueprint->string('tenant_id', 191)->nullable()->index();
            $blueprint->json('scope_json')->nullable();
            $blueprint->string('requester_actor_type', 64);
            $blueprint->string('requester_actor_id', 191);
            $blueprint->string('original_caller', 64);
            $blueprint->json('input_json')->nullable();
            $blueprint->string('input_hash', 128)->nullable();
            $blueprint->string('idempotency_key', 191)->nullable();
            $blueprint->json('result_json')->nullable();
            $blueprint->string('result_status', 32)->nullable();
            $blueprint->string('decided_by', 191)->nullable();
            $blueprint->timestamp('decided_at')->nullable();
            $blueprint->text('decision_reason')->nullable();
            $blueprint->timestamp('expires_at')->nullable()->index();
            $blueprint->timestamp('execution_lease_until')->nullable();
            $blueprint->unsignedInteger('execution_attempt')->default(0);
            $blueprint->timestamp('approved_at')->nullable();
            $blueprint->json('channel_meta_json')->nullable();
            $blueprint->timestamps();

            $blueprint->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(MigrationCatalog::TABLE_APPROVALS);
    }
};
