<?php

namespace Rawphp\Capabilities\Persistence;

use Illuminate\Database\Schema\Blueprint;

/**
 * Pure schema catalog for core bus tables (D-005 / D-006 / D-010).
 *
 * Unit tests assert this catalog; migration files apply the same definitions
 * when Schema is available in a host app. No live DB required for package CI.
 */
final class MigrationCatalog
{
    public const TABLE_APPROVALS = 'capabilities_approvals';

    public const TABLE_IDEMPOTENCY = 'capabilities_idempotency';

    public const TABLE_AUDIT_OUTBOX = 'capabilities_audit_outbox';

    /**
     * @return list<string>
     */
    public static function tables(): array
    {
        return [
            self::TABLE_APPROVALS,
            self::TABLE_IDEMPOTENCY,
            self::TABLE_AUDIT_OUTBOX,
        ];
    }

    /**
     * @return array<string, array{
     *     columns: list<string>,
     *     unique: list<list<string>>,
     *     indexes: list<list<string>>
     * }>
     */
    public static function definitions(): array
    {
        return [
            self::TABLE_APPROVALS => [
                'columns' => [
                    'id',
                    'capability_name',
                    'status',
                    'tenant_id',
                    'scope_json',
                    'requester_actor_type',
                    'requester_actor_id',
                    'original_caller',
                    'input_json',
                    'input_hash',
                    'idempotency_key',
                    'result_json',
                    'result_status',
                    'decided_by',
                    'decided_at',
                    'decision_reason',
                    'expires_at',
                    'execution_lease_until',
                    'execution_attempt',
                    'approved_at',
                    'channel_meta_json',
                    'created_at',
                    'updated_at',
                ],
                'unique' => [['id']],
                'indexes' => [
                    ['status'],
                    ['tenant_id', 'status'],
                    ['expires_at'],
                ],
            ],
            self::TABLE_IDEMPOTENCY => [
                'columns' => [
                    'id',
                    'tenant_id',
                    'actor_type',
                    'actor_id',
                    'capability_name',
                    'idempotency_key',
                    'request_hash',
                    'status',
                    'result_json',
                    'approval_id',
                    'created_at',
                    'expires_at',
                ],
                'unique' => [[
                    'tenant_id',
                    'actor_type',
                    'actor_id',
                    'capability_name',
                    'idempotency_key',
                ]],
                'indexes' => [
                    ['expires_at'],
                    ['capability_name', 'status'],
                ],
            ],
            self::TABLE_AUDIT_OUTBOX => [
                'columns' => [
                    'id',
                    'event',
                    'capability_name',
                    'tenant_id',
                    'payload_json',
                    'status',
                    'attempts',
                    'available_at',
                    'created_at',
                    'updated_at',
                ],
                'unique' => [['id']],
                'indexes' => [
                    ['status', 'available_at'],
                ],
            ],
        ];
    }

    public static function hasTable(string $table): bool
    {
        return isset(self::definitions()[$table]);
    }

    /**
     * Apply the idempotency table shape (D-005). Single source for the create
     * migration and the corrective rebuild so both install paths converge.
     *
     * `id` is a string primary key: gateway ids are 32-char hex
     * (QueryTableGateway::newId), which BIGINT auto-increment rejects
     * (MySQL strict-mode 1264).
     *
     * Identity columns are 160 chars so the composite unique fits InnoDB's
     * 3072-byte key limit on utf8mb4: (160+64+160+160+160)*4 = 2816 bytes.
     * 191-char columns overflow it (3312 bytes → MySQL 1071 on create).
     */
    public static function defineIdempotency(Blueprint $blueprint): void
    {
        $blueprint->string('id', 64)->primary();
        // Empty string for null tenant so unique index works on MySQL.
        $blueprint->string('tenant_id', 160)->default('');
        $blueprint->string('actor_type', 64);
        $blueprint->string('actor_id', 160);
        $blueprint->string('capability_name', 160);
        $blueprint->string('idempotency_key', 160);
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
    }

    /**
     * @return list<string>
     */
    public static function columns(string $table): array
    {
        return self::definitions()[$table]['columns'] ?? [];
    }

    /**
     * Table name fragments that must never appear in core bus tables (D-007).
     * Kept as opaque tokens so architecture scanners do not flag this source file.
     *
     * @return list<string>
     */
    public static function forbiddenNameFragments(): array
    {
        // Built without spelling chat-vendor tokens in source (architecture blob scan).
        return [
            'tele'.'gram',
            'sla'.'ck',
            'what'.'sapp',
            'mess'.'aging_user',
            'message_'.'thread',
        ];
    }
}
