<?php

namespace Rawphp\Capabilities\Approval;

use Rawphp\Capabilities\Contracts\ApprovalStore;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Support\SystemClock;

/**
 * High-level approval API exposed via Capability facade / registry.
 */
final class ApprovalManager
{
    public function __construct(
        private readonly ApprovalStore $store,
    ) {}

    public static function inMemory(): self
    {
        return new self(new InMemoryApprovalStore(new SystemClock));
    }

    public function store(): ApprovalStore
    {
        return $this->store;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function request(array $record): array
    {
        $record['status'] = $record['status'] ?? 'pending';

        return $this->store->put($record);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        return $this->store->find($id);
    }
}
