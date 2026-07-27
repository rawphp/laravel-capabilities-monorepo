<?php

namespace Rawphp\Capabilities\Observability;

use Rawphp\Capabilities\Contracts\Metrics;
use Rawphp\Capabilities\Contracts\Tracer;

/**
 * Records invoke metrics + capabilities.invoke spans (D-019).
 */
final class InvokeTelemetry
{
    public const METRIC_INVOKE = 'capabilities_invoke_total';

    public const METRIC_LATENCY = 'capabilities_invoke_duration_ms';

    public const METRIC_APPROVAL_REQUIRED = 'approval_required_total';

    public const METRIC_AUTHZ_DENY = 'authz_deny_total';

    public const METRIC_RATE_LIMITED = 'rate_limited_total';

    public const METRIC_IDEMPOTENT_REPLAY = 'idempotent_replay_total';

    public const METRIC_STUCK_APPROVED = 'approvals_stuck_approved_total';

    public const METRIC_RESUME = 'approvals_resume_total';

    public const METRIC_ACCEPT = 'approvals_accept_total';

    public const SPAN_INVOKE = 'capabilities.invoke';

    /** @var list<string> */
    public const INVOKE_STATUSES = [
        'ok',
        'validation_failed',
        'forbidden',
        'unauthenticated',
        'approval_required',
        'rate_limited',
        'domain_error',
        'conflict',
        'not_found',
        'output_invalid',
        'internal',
    ];

    /** @var list<string> */
    public const SPAN_ATTRIBUTES = [
        'capability',
        'caller',
        'surface',
        'tenant_id',
        'actor_type',
        'approval_id',
        'idempotency_key',
    ];

    /** @var list<string> */
    public const CALLERS = ['agent', 'mcp', 'http', 'cli', 'job'];

    public function __construct(
        private readonly Metrics $metrics = new InMemoryMetrics,
        private readonly Tracer $tracer = new InMemoryTracer,
    ) {}

    /**
     * Canonical labels for capabilities_invoke_total.
     *
     * @return array{capability: string, caller: string, status: string}
     */
    public static function invokeLabels(string $caller, string $status, string $capability = 'unknown'): array
    {
        return [
            'capability' => $capability,
            'caller' => $caller,
            'status' => $status,
        ];
    }

    /**
     * @param  array<string, scalar|null>  $spanAttributes
     */
    public function recordInvoke(
        string $capability,
        string $caller,
        string $status,
        float $durationMs = 0.0,
        array $spanAttributes = [],
    ): string {
        $labels = self::invokeLabels($caller, $status, $capability);
        $this->metrics->increment(self::METRIC_INVOKE, 1, $labels);
        $this->metrics->histogram(self::METRIC_LATENCY, $durationMs, [
            'capability' => $capability,
            'caller' => $caller,
        ]);

        match ($status) {
            'approval_required' => $this->metrics->increment(self::METRIC_APPROVAL_REQUIRED, 1, ['caller' => $caller]),
            'forbidden' => $this->metrics->increment(self::METRIC_AUTHZ_DENY, 1, ['caller' => $caller]),
            'rate_limited' => $this->metrics->increment(self::METRIC_RATE_LIMITED, 1, ['caller' => $caller]),
            default => null,
        };

        $attrs = array_merge([
            'capability' => $capability,
            'caller' => $caller,
            'surface' => $spanAttributes['surface'] ?? $caller,
        ], $spanAttributes);

        $spanId = $this->tracer->startSpan(self::SPAN_INVOKE, $attrs);
        $this->tracer->endSpan($spanId, $status);

        return $spanId;
    }

    public function recordIdempotentReplay(string $capability, string $caller): void
    {
        $this->metrics->increment(self::METRIC_IDEMPOTENT_REPLAY, 1, [
            'capability' => $capability,
            'caller' => $caller,
        ]);
    }

    public function recordStuckApproved(int $count = 1): void
    {
        $this->metrics->increment(self::METRIC_STUCK_APPROVED, $count);
    }

    public function recordResume(string $result): void
    {
        $this->metrics->increment(self::METRIC_RESUME, 1, ['result' => $result]);
    }

    public function recordAccept(string $result): void
    {
        $this->metrics->increment(self::METRIC_ACCEPT, 1, ['result' => $result]);
    }

    public function metrics(): Metrics
    {
        return $this->metrics;
    }

    public function tracer(): Tracer
    {
        return $this->tracer;
    }
}
