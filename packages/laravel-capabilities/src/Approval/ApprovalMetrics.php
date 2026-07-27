<?php

namespace Rawphp\Capabilities\Approval;

/**
 * Lightweight in-process counters for approval observability (D-019 / P2-004).
 *
 * Unit tests inspect values; production may bridge to Prometheus later.
 */
final class ApprovalMetrics
{
    /** @var array<string, int|float> */
    private array $counters = [];

    public function increment(string $name, int $by = 1, array $labels = []): void
    {
        $key = $this->key($name, $labels);
        $this->counters[$key] = (int) ($this->counters[$key] ?? 0) + $by;
    }

    public function set(string $name, int|float $value, array $labels = []): void
    {
        $this->counters[$this->key($name, $labels)] = $value;
    }

    public function get(string $name, array $labels = []): int|float
    {
        return $this->counters[$this->key($name, $labels)] ?? 0;
    }

    /**
     * @return array<string, int|float>
     */
    public function all(): array
    {
        return $this->counters;
    }

    /**
     * @param  array<string, scalar>  $labels
     */
    private function key(string $name, array $labels): string
    {
        if ($labels === []) {
            return $name;
        }

        ksort($labels);
        $parts = [];
        foreach ($labels as $k => $v) {
            $parts[] = $k.'='.(string) $v;
        }

        return $name.'{'.implode(',', $parts).'}';
    }
}
