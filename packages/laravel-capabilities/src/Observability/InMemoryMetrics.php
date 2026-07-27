<?php

namespace Rawphp\Capabilities\Observability;

use Rawphp\Capabilities\Contracts\Metrics;

/**
 * In-process metrics for unit tests and local ops (D-019).
 */
final class InMemoryMetrics implements Metrics
{
    /** @var array<string, int|float> */
    private array $counters = [];

    /** @var array<string, list<float>> */
    private array $histograms = [];

    /** @var list<array{name: string, labels: array<string, scalar|null>, by: int}> */
    private array $log = [];

    public function __construct(
        private readonly bool $enabled = true,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function increment(string $name, int $by = 1, array $labels = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $key = self::key($name, $labels);
        $this->counters[$key] = (int) ($this->counters[$key] ?? 0) + $by;
        $this->log[] = ['name' => $name, 'labels' => $labels, 'by' => $by];
    }

    public function histogram(string $name, float $value, array $labels = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $key = self::key($name, $labels);
        $this->histograms[$key] ??= [];
        $this->histograms[$key][] = $value;
    }

    public function get(string $name, array $labels = []): int|float
    {
        return $this->counters[self::key($name, $labels)] ?? 0;
    }

    /**
     * @return list<float>
     */
    public function histogramSamples(string $name, array $labels = []): array
    {
        return $this->histograms[self::key($name, $labels)] ?? [];
    }

    /**
     * @return list<array{name: string, labels: array<string, scalar|null>, by: int}>
     */
    public function emissions(): array
    {
        return $this->log;
    }

    /**
     * @return array<string, int|float>
     */
    public function all(): array
    {
        return $this->counters;
    }

    /**
     * @param  array<string, scalar|null>  $labels
     */
    public static function key(string $name, array $labels): string
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
