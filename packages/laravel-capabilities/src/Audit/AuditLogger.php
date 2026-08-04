<?php

namespace Rawphp\Capabilities\Audit;

use Rawphp\Capabilities\Pipeline\InvokeState;
use Rawphp\Capabilities\Pipeline\ResolveActor;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityData;
use Rawphp\Capabilities\Support\CapabilityResult;

/**
 * Builds structured audit entries for capability lifecycle events (D-010).
 */
final class AuditLogger
{
    /** @var list<string> */
    public const SUPPORTED_DRIVERS = ['database', 'log', 'queue'];

    /** @var list<string> */
    public const SUPPORTED_MODES = ['best_effort', 'strict'];

    /**
     * @return array<string, mixed>
     */
    public static function entry(
        InvokeState $state,
        bool $success,
        ?CapabilityResult $failure = null,
        ?float $durationMs = null,
    ): array {
        /** @var CapabilityContext|null $ctx */
        $ctx = $state->context;

        $redacted = self::redactInput($state);

        return [
            'event' => $success ? 'capability.invoked' : 'capability.failed',
            'name' => $state->definition->name,
            'capability_name' => $state->definition->name,
            'caller' => $state->caller,
            'actor' => $ctx !== null
                ? [
                    'type' => ResolveActor::actorType($ctx->actor()),
                    'id' => ResolveActor::actorId($ctx->actor()),
                ]
                : null,
            'actor_type' => $ctx !== null ? ResolveActor::actorType($ctx->actor()) : null,
            'actor_id' => $ctx !== null ? ResolveActor::actorId($ctx->actor()) : null,
            'scope' => $ctx !== null
                ? [
                    'tenant_id' => $ctx->tenantId(),
                    'team_id' => $ctx->scope()?->teamId,
                ]
                : null,
            'tenant_id' => $ctx?->tenantId(),
            'idempotency' => $state->idempotencyKey,
            'idempotency_key' => $state->idempotencyKey,
            'replay' => $state->idempotentReplay,
            'result' => $success
                ? ['ok' => true, 'summary' => self::resultSummary($state->output)]
                : [
                    'ok' => false,
                    'code' => $failure?->errorCode(),
                    'message' => $failure?->error['message'] ?? null,
                ],
            'duration' => $durationMs,
            'duration_ms' => $durationMs,
            'approval_id' => $state->approvalId,
            'redacted_input' => $redacted,
            'payload' => [
                'caller' => $state->caller,
                'ok' => $success,
                'code' => $failure?->errorCode(),
                'request_id' => $state->requestId,
            ],
            'request_id' => $state->requestId,
            // D-023: MCP auth profile + client_id on every MCP invoke when present.
            'mcp' => $ctx?->mcp(),
        ];
    }

    public static function assertValidMode(string $mode): string
    {
        if (! in_array($mode, self::SUPPORTED_MODES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Undefined audit mode "%s"; expected best_effort|strict (D-010).',
                $mode,
            ));
        }

        return $mode;
    }

    public static function assertValidDriver(string $driver): string
    {
        if (! in_array($driver, self::SUPPORTED_DRIVERS, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported audit driver "%s"; expected: %s.',
                $driver,
                implode(', ', self::SUPPORTED_DRIVERS),
            ));
        }

        return $driver;
    }

    /**
     * @return array<string, mixed>|list<mixed>|null
     */
    private static function redactInput(InvokeState $state): ?array
    {
        $raw = $state->input instanceof CapabilityData
            ? $state->input->toArray()
            : $state->rawInput;

        if (! is_array($raw)) {
            return null;
        }

        $redacted = $raw;
        foreach (['password', 'secret', 'token', 'api_key', 'authorization'] as $sensitive) {
            if (array_key_exists($sensitive, $redacted)) {
                $redacted[$sensitive] = '[REDACTED]';
            }
        }

        return $redacted;
    }

    private static function resultSummary(mixed $output): mixed
    {
        if ($output instanceof CapabilityData) {
            return $output->toArray();
        }

        if (is_object($output) && method_exists($output, 'toArray')) {
            return $output->toArray();
        }

        if (is_scalar($output) || $output === null) {
            return $output;
        }

        if (is_array($output)) {
            return $output;
        }

        return get_debug_type($output);
    }
}
