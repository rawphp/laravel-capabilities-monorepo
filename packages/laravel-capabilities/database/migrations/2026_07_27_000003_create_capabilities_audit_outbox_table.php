<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Persistence\MigrationCatalog;

/**
 * Durable audit records (D-010). `audit.driver=database` inserts one `pending` row per
 * entry via DatabaseAuditWriter; a host drain may forward rows and mark them `completed`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = MigrationCatalog::TABLE_AUDIT_OUTBOX;
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->string('id', 64)->primary();
            $blueprint->string('event', 64);
            $blueprint->string('capability_name', 191)->nullable();
            $blueprint->string('tenant_id', 191)->nullable();
            $blueprint->json('payload_json');
            $blueprint->string('status', 32)->default('pending');
            $blueprint->unsignedInteger('attempts')->default(0);
            $blueprint->timestamp('available_at')->nullable();
            $blueprint->timestamps();

            $blueprint->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(MigrationCatalog::TABLE_AUDIT_OUTBOX);
    }
};
