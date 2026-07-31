<?php

namespace Rawphp\Capabilities\Persistence;

/**
 * In-process table gateway for unit-testing database store drivers.
 *
 * This is not a production driver — it proves store semantics without Eloquent.
 */
final class ArrayTableGateway implements TableGateway
{
    /** @var array<string, array<string, mixed>> */
    private array $byId = [];

    /** @var array<string, string> identityHash => id */
    private array $identityIndex = [];

    private int $sequence = 0;

    public function insert(array $row): array
    {
        $id = isset($row['id']) && is_string($row['id']) && $row['id'] !== ''
            ? $row['id']
            : $this->nextId();
        $row['id'] = $id;
        $this->byId[$id] = $row;

        return $row;
    }

    public function find(string $id): ?array
    {
        return $this->byId[$id] ?? null;
    }

    public function replace(string $id, array $row): ?array
    {
        if (! isset($this->byId[$id])) {
            return null;
        }
        $row['id'] = $id;
        $this->byId[$id] = $row;

        return $row;
    }

    public function updateWhere(array $where, array $attributes): ?array
    {
        foreach ($this->byId as $id => $row) {
            if (! $this->matches($row, $where)) {
                continue;
            }
            $merged = array_merge($row, $attributes);
            $merged['id'] = $id;
            $this->byId[$id] = $merged;

            return $merged;
        }

        return null;
    }

    public function updateWhereLeaseFree(
        array $where,
        string $leaseColumn,
        string $nowIso,
        array $attributes,
    ): ?array {
        foreach ($this->byId as $id => $row) {
            if (! $this->matches($row, $where)) {
                continue;
            }
            if (! $this->leaseIsFree($row[$leaseColumn] ?? null, $nowIso)) {
                return null;
            }
            $merged = array_merge($row, $attributes);
            $merged['id'] = $id;
            $this->byId[$id] = $merged;

            return $merged;
        }

        return null;
    }

    public function findWhere(array $where): array
    {
        $out = [];
        foreach ($this->byId as $row) {
            if ($this->matches($row, $where)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    public function upsert(array $identity, array $row): array
    {
        $hash = $this->hashIdentity($identity);
        if (isset($this->identityIndex[$hash])) {
            $id = $this->identityIndex[$hash];
            $merged = array_merge($this->byId[$id] ?? [], $row, $identity);
            $merged['id'] = $id;
            $this->byId[$id] = $merged;

            return $merged;
        }

        $inserted = $this->insert(array_merge($row, $identity));
        $this->identityIndex[$hash] = (string) $inserted['id'];

        return $inserted;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $where
     */
    private function matches(array $row, array $where): bool
    {
        foreach ($where as $key => $value) {
            if (($row[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    private function hashIdentity(array $identity): string
    {
        ksort($identity);

        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
    }

    private function nextId(): string
    {
        $this->sequence++;

        return 'row-'.$this->sequence;
    }

    private function leaseIsFree(mixed $lease, string $nowIso): bool
    {
        if ($lease === null || $lease === '') {
            return true;
        }
        if (! is_string($lease)) {
            return true;
        }

        try {
            $until = new \DateTimeImmutable($lease);
            $now = new \DateTimeImmutable($nowIso);

            return ! ($now < $until);
        } catch (\Exception) {
            // unparseable lease = free (matches DatabaseApprovalStore historical semantics)
            return true;
        }
    }
}
