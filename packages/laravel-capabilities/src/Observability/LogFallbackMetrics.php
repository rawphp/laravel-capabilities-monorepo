<?php

namespace Rawphp\Capabilities\Observability;

use Rawphp\Capabilities\Contracts\Metrics;

/**
 * Metrics driver that records to an in-memory log sink when no Pulse/OTel driver is bound (D-019).
 */
final class LogFallbackMetrics implements Metrics
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $lines = [];

    private readonly InMemoryMetrics $inner;

    public function __construct(bool $enabled = true)
    {
        $this->inner = new InMemoryMetrics($enabled);
    }

    public function enabled(): bool
    {
        return $this->inner->enabled();
    }

    public function increment(string $name, int $by = 1, array $labels = []): void
    {
        if (! $this->enabled()) {
            return;
        }
        $this->inner->increment($name, $by, $labels);
        $this->lines[] = [
            'level' => 'info',
            'message' => 'metrics.increment '.$name,
            'context' => ['name' => $name, 'by' => $by, 'labels' => $labels],
        ];
    }

    public function histogram(string $name, float $value, array $labels = []): void
    {
        if (! $this->enabled()) {
            return;
        }
        $this->inner->histogram($name, $value, $labels);
        $this->lines[] = [
            'level' => 'info',
            'message' => 'metrics.histogram '.$name,
            'context' => ['name' => $name, 'value' => $value, 'labels' => $labels],
        ];
    }

    public function get(string $name, array $labels = []): int|float
    {
        return $this->inner->get($name, $labels);
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function logLines(): array
    {
        return $this->lines;
    }

    public function inner(): InMemoryMetrics
    {
        return $this->inner;
    }
}
