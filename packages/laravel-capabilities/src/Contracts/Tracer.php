<?php

namespace Rawphp\Capabilities\Contracts;

/**
 * Tracing contract (D-019). Span name `capabilities.invoke` with standard attributes.
 */
interface Tracer
{
    /**
     * @param  array<string, scalar|null>  $attributes
     * @return string span id
     */
    public function startSpan(string $name, array $attributes = []): string;

    /**
     * @param  array<string, scalar|null>  $attributes
     */
    public function setAttributes(string $spanId, array $attributes): void;

    public function endSpan(string $spanId, ?string $status = null): void;

    public function enabled(): bool;

    /**
     * @return list<array{id: string, name: string, attributes: array<string, scalar|null>, status: ?string, ended: bool}>
     */
    public function spans(): array;
}
