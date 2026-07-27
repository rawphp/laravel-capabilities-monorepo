<?php

namespace Rawphp\Capabilities\Pipeline;

/**
 * Canonical server pipeline stage names and order (PIPE-001 / docs/spec.md).
 */
final class PipelineStages
{
    public const JSON_SCHEMA_VALIDATE = 'json_schema_validate';

    public const HYDRATE_DTO = 'hydrate_dto';

    public const SERVER_ONLY_VALIDATE = 'server_only_validate';

    public const RESOLVE_ACTOR = 'resolve_actor';

    public const RESOLVE_SCOPE = 'resolve_scope';

    public const IDEMPOTENCY_LOOKUP = 'idempotency_lookup';

    public const AUTHORIZE = 'authorize';

    public const NEEDS_APPROVAL = 'needs_approval';

    public const RATE_LIMIT = 'rate_limit';

    public const RUN = 'run';

    public const VALIDATE_OUTPUT = 'validate_output';

    public const STORE_IDEMPOTENCY = 'store_idempotency';

    /** Alias used by some inventory scenarios. */
    public const STORE_IDEMPOTENCY_RESULT = 'store_idempotency_result';

    public const RECORD_AUDIT = 'record_audit';

    public const EMIT_EVENTS = 'emit_events';

    public const WIRE_RESPONSE = 'wire_response';

    /**
     * Ordered stages for a successful non-replay, non-approval invoke.
     *
     * @return list<string>
     */
    public static function ordered(): array
    {
        return [
            self::JSON_SCHEMA_VALIDATE,
            self::HYDRATE_DTO,
            self::SERVER_ONLY_VALIDATE,
            self::RESOLVE_ACTOR,
            self::RESOLVE_SCOPE,
            self::IDEMPOTENCY_LOOKUP,
            self::AUTHORIZE,
            self::NEEDS_APPROVAL,
            self::RATE_LIMIT,
            self::RUN,
            self::VALIDATE_OUTPUT,
            self::STORE_IDEMPOTENCY,
            self::RECORD_AUDIT,
            self::EMIT_EVENTS,
            self::WIRE_RESPONSE,
        ];
    }

    /**
     * Error envelope code for a pre-run stage failure (PIPE-002).
     */
    public static function errorCodeFor(string $stage): string
    {
        return match ($stage) {
            self::JSON_SCHEMA_VALIDATE,
            self::HYDRATE_DTO,
            self::SERVER_ONLY_VALIDATE => 'validation_failed',
            self::RESOLVE_ACTOR => 'unauthenticated',
            self::RESOLVE_SCOPE => 'forbidden',
            self::IDEMPOTENCY_LOOKUP => 'conflict',
            self::AUTHORIZE => 'forbidden',
            self::NEEDS_APPROVAL => 'approval_required',
            self::RATE_LIMIT => 'rate_limited',
            default => 'internal',
        };
    }
}
