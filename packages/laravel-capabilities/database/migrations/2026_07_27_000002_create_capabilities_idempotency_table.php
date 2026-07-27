<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Persistence\MigrationCatalog;

/**
 * Durable mutating-invoke outcomes (D-005). Composite unique identity is required.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = MigrationCatalog::TABLE_IDEMPOTENCY;
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->bigIncrements('id');
            // Empty string for null tenant so unique index works on MySQL.
            $blueprint->string('tenant_id', 191)->default('');
            $blueprint->string('actor_type', 64);
            $blueprint->string('actor_id', 191);
            $blueprint->string('capability_name', 191);
            $blueprint->string('idempotency_key', 191);
            $blueprint->string('request_hash', 128)->nullable();
            $blueprint->string('status', 32);
            $blueprint->json('result_json')->nullable();
            $blueprint->string('approval_id', 64)->nullable();
            $blueprint->timestamp('created_at')->useCurrent();
            $blueprint->timestamp('expires_at')->nullable()->index();

            $blueprint->unique(
                ['tenant_id', 'actor_type', 'actor_id', 'capability_name', 'idempotency_key'],
                'capabilities_idempotency_identity_unique',
            );
            $blueprint->index(['capability_name', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(MigrationCatalog::TABLE_IDEMPOTENCY);
    }
};
