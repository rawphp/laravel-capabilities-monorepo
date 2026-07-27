<?php

namespace Rawphp\Capabilities\Observability;

use Rawphp\Capabilities\Contracts\Tracer;

/**
 * In-process tracer for unit tests (D-019).
 */
final class InMemoryTracer implements Tracer
{
    /** @var list<array{id: string, name: string, attributes: array<string, scalar|null>, status: ?string, ended: bool}> */
    private array $spans = [];

    private int $seq = 0;

    public function __construct(
        private readonly bool $enabled = true,
        private readonly bool $hashSensitive = false,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function startSpan(string $name, array $attributes = []): string
    {
        if (! $this->enabled) {
            return 'disabled';
        }

        $id = 'span-'.(++$this->seq);
        $this->spans[] = [
            'id' => $id,
            'name' => $name,
            'attributes' => $this->normalizeAttributes($attributes),
            'status' => null,
            'ended' => false,
        ];

        return $id;
    }

    public function setAttributes(string $spanId, array $attributes): void
    {
        if (! $this->enabled || $spanId === 'disabled') {
            return;
        }

        foreach ($this->spans as $i => $span) {
            if ($span['id'] === $spanId) {
                $this->spans[$i]['attributes'] = array_merge(
                    $span['attributes'],
                    $this->normalizeAttributes($attributes),
                );

                return;
            }
        }
    }

    public function endSpan(string $spanId, ?string $status = null): void
    {
        if (! $this->enabled || $spanId === 'disabled') {
            return;
        }

        foreach ($this->spans as $i => $span) {
            if ($span['id'] === $spanId) {
                $this->spans[$i]['ended'] = true;
                $this->spans[$i]['status'] = $status;

                return;
            }
        }
    }

    public function spans(): array
    {
        return $this->spans;
    }

    /**
     * @return array{id: string, name: string, attributes: array<string, scalar|null>, status: ?string, ended: bool}|null
     */
    public function lastSpan(): ?array
    {
        if ($this->spans === []) {
            return null;
        }

        return $this->spans[array_key_last($this->spans)];
    }

    /**
     * @param  array<string, scalar|null>  $attributes
     * @return array<string, scalar|null>
     */
    private function normalizeAttributes(array $attributes): array
    {
        if (! $this->hashSensitive) {
            return $attributes;
        }

        $sensitive = ['tenant_id', 'idempotency_key', 'approval_id', 'actor_id'];
        $out = [];
        foreach ($attributes as $k => $v) {
            if (in_array($k, $sensitive, true) && is_string($v) && $v !== '') {
                $out[$k] = 'sha256:'.substr(hash('sha256', $v), 0, 16);
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
    }
}
