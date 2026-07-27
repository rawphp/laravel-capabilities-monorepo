<?php

namespace Rawphp\Capabilities\Idempotency;

/**
 * Emits warn/metric when mutating invokes omit an idempotency key (D-005).
 *
 * Unit-test friendly: records warnings in memory rather than logging I/O.
 */
final class MissingKeyWarner
{
    /** @var list<array{capability: string, caller: string, message: string}> */
    private array $warnings = [];

    public function __construct(
        private readonly bool $enabled = true,
    ) {}

    public function maybeWarn(string $capability, string $caller, bool $isMutating, ?string $key): void
    {
        if (! $this->enabled || ! $isMutating) {
            return;
        }

        if ($key !== null && $key !== '') {
            return;
        }

        $this->warnings[] = [
            'capability' => $capability,
            'caller' => $caller,
            'message' => sprintf(
                'Mutating capability "%s" invoked via %s without Idempotency-Key (D-005).',
                $capability,
                $caller,
            ),
        ];
    }

    /**
     * @return list<array{capability: string, caller: string, message: string}>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function count(): int
    {
        return count($this->warnings);
    }

    public function clear(): void
    {
        $this->warnings = [];
    }
}
