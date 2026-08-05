<?php

namespace Rawphp\Capabilities\Contracts;

use Rawphp\Capabilities\Support\CapabilityResult;

/**
 * Sibling-safe port for approval accept / reject / lookup (D-006).
 *
 * Conversation surfaces (messaging callbacks, etc.) must depend on this
 * contract only — never on concrete ApprovalManager.
 * Host apps may still type-hint ApprovalManager for resume/ops APIs that
 * stay inside core.
 *
 * Intentionally no `use …\ApprovalManager` import: keep this contract free of
 * the concrete class so sibling packages do not pull it via the port surface.
 */
interface ApprovalGateway
{
    /**
     * Load an approval row by id (implementations may apply lazy TTL expiry).
     *
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array;

    /**
     * Accept a pending approval and drive exactly-once execution when applicable.
     *
     * @param  array<string, mixed>  $options  tenant_id?, reason?
     */
    public function accept(string $id, object $approver, array $options = []): CapabilityResult;

    /**
     * Reject a pending approval.
     *
     * @param  array<string, mixed>  $options
     */
    public function reject(string $id, object $approver, ?string $reason = null, array $options = []): CapabilityResult;
}
