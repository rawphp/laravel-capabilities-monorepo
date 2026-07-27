<?php

namespace Rawphp\Capabilities\Contracts;

/**
 * Ops metrics contract (D-019). Drivers: in-memory (tests), log fallback, Pulse/OTel later.
 */
interface Metrics
{
    /**
     * @param  array<string, scalar|null>  $labels
     */
    public function increment(string $name, int $by = 1, array $labels = []): void;

    /**
     * @param  array<string, scalar|null>  $labels
     */
    public function histogram(string $name, float $value, array $labels = []): void;

    /**
     * @param  array<string, scalar|null>  $labels
     */
    public function get(string $name, array $labels = []): int|float;

    public function enabled(): bool;
}
