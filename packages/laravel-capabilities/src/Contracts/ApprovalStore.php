<?php

namespace Rawphp\Capabilities\Contracts;

/**
 * Persistence for capability approval rows (D-006).
 *
 * Production drivers may use Eloquent/DB; unit tests inject
 * {@see \Rawphp\Capabilities\Support\InMemoryApprovalStore}.
 *
 * @phpstan-type ApprovalRecord array{
 *     id: string,
 *     capability_name: string,
 *     status: string,
 *     tenant_id: string|null,
 *     scope: mixed,
 *     requester_actor_type: string,
 *     requester_actor_id: string,
 *     original_caller: string,
 *     input_json: mixed,
 *     input_hash: string|null,
 *     idempotency_key: string|null,
 *     result_json: mixed,
 *     result_status: string|null,
 *     decided_by: string|null,
 *     decided_at: string|null,
 *     decision_reason: string|null,
 *     expires_at: string|null,
 *     execution_lease_until: string|null,
 *     execution_attempt: int,
 *     approved_at: string|null,
 *     messaging: mixed,
 *     created_at: string,
 *     updated_at: string
 * }
 */
interface ApprovalStore
{
    /**
     * Insert a new approval row. Generates `id` / timestamps when omitted.
     *
     * @param  array<string, mixed>  $record
     * @return ApprovalRecord
     */
    public function put(array $record): array;

    /**
     * @return ApprovalRecord|null
     */
    public function find(string $id): ?array;

    /**
     * Merge attributes into an existing row; returns null when missing.
     *
     * @param  array<string, mixed>  $attributes
     * @return ApprovalRecord|null
     */
    public function update(string $id, array $attributes): ?array;

    /**
     * Conditional update: only applies when current status matches `$expectedStatus`.
     * Enables exactly-once accept/resume without double-run races (D-006).
     *
     * @param  array<string, mixed>  $attributes
     * @return ApprovalRecord|null  null when missing or status mismatch
     */
    public function compareAndUpdate(string $id, string $expectedStatus, array $attributes): ?array;

    /**
     * Claim execution under a free/expired lease while status matches.
     * Returns null when missing, status mismatch, or lease still held past `$now`.
     *
     * @param  array<string, mixed>  $attributes  Must include execution_lease_until
     * @return ApprovalRecord|null
     */
    public function claimLease(
        string $id,
        string $expectedStatus,
        string $nowIso,
        array $attributes,
    ): ?array;

    /**
     * @return list<ApprovalRecord>
     */
    public function findByStatus(string $status): array;
}
