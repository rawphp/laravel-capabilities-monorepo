<?php

/**
 * Scaffold defaults — full shape lives in docs/spec.md.
 *
 * Uses plain PHP defaults so unit tests can load this file without a Laravel
 * app. When booted in Laravel, ServiceProvider merge + env() at host level
 * still apply via published config overrides.
 */
// Avoid Laravel's env() helper here — monorepo unit tests load this file without
// a full Illuminate env repository (PhpOption). Host apps override via published config.
$env = static function (string $key, mixed $default = null): mixed {
    $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($v === false || $v === null || $v === '') {
        return $default;
    }

    return match (strtolower((string) $v)) {
        'true', '(true)', '1' => true,
        'false', '(false)', '0' => false,
        'null', '(null)' => null,
        default => $v,
    };
};

$appPath = static function (string $path = ''): string {
    if (function_exists('app_path')) {
        return app_path($path);
    }

    return $path === '' ? 'app' : 'app/'.ltrim($path, '/');
};

return [
    'path' => $appPath('Capabilities'),

    'surfaces' => [
        'agent' => [
            'enabled' => $env('CAPABILITIES_SURFACE_AGENT', true),
            'require_package' => true,
            'on_incompatible' => $env('CAPABILITIES_AGENT_ON_INCOMPATIBLE', 'fail'),
            'profiles' => [],
            'require_profile' => true,
            'max_tools_warn' => 32,
            'max_tools_hard' => 64,
            'max_tool_calls_per_turn' => 16,
        ],
        'mcp' => [
            'enabled' => $env('CAPABILITIES_SURFACE_MCP', true),
            'require_package' => true,
            'on_incompatible' => $env('CAPABILITIES_MCP_ON_INCOMPATIBLE', 'fail'),
            'profiles' => [],
            'require_profile' => true,
            'auth' => [
                'default_profile' => 'user_pat',
                'allow_integration_credentials' => false,
                'integration_actors' => [],
                'audit_client_id' => true,
            ],
        ],
        'http' => [
            'enabled' => $env('CAPABILITIES_SURFACE_HTTP', true),
            'prefix' => 'capabilities',
            'middleware' => ['api', 'auth:sanctum'],
        ],
        'cli' => [
            'enabled' => $env('CAPABILITIES_SURFACE_CLI', true),
        ],
        'job' => [
            'enabled' => $env('CAPABILITIES_SURFACE_JOB', true),
        ],
        'artisan' => [
            'enabled' => $env('CAPABILITIES_SURFACE_ARTISAN', true),
        ],
        'messaging' => [
            'enabled' => $env('CAPABILITIES_SURFACE_MESSAGING', false),
        ],
    ],

    'audit' => [
        'enabled' => true,
        'mode' => $env('CAPABILITIES_AUDIT_MODE', 'best_effort'),
        'driver' => 'database',
        'queue' => 'capabilities-audit',
        'required' => false,
    ],

    'transactions' => [
        'wrap_run' => false,
    ],

    'events' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Approval store
    |--------------------------------------------------------------------------
    |
    | store: memory | in_memory | array | database | db | eloquent
    | connection: optional Illuminate connection name when store=database.
    |             Defaults to the app default connection (db.connection).
    | Host may bind TableGateway (e.g. ArrayTableGateway in unit tests) or
    | ConnectionInterface; package builds QueryTableGateway on
    | capabilities_approvals (MigrationCatalog::TABLE_APPROVALS).
    |
    */
    'approval' => [
        'store' => 'database',
        'connection' => $env('CAPABILITIES_APPROVAL_CONNECTION', null),
        'ttl_hours' => 24,
        'default_policy' => 'requester_or_role',
        'execution' => $env('CAPABILITIES_APPROVAL_EXECUTION', 'deferred'),
        'resume' => [
            'enabled' => true,
            'every_seconds' => 60,
            'grace_seconds' => 30,
            'stuck_after_seconds' => 300,
            'lease_seconds' => 120,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency store
    |--------------------------------------------------------------------------
    |
    | driver: memory | in_memory | array | database | db | eloquent
    | connection: optional Illuminate connection name when driver=database.
    | Package default is database — aligned with approval.store so multi-worker
    | mutating invokes honor Idempotency-Key durably (L-009 / REQ-070 / D-005).
    | Use memory only for single-process unit tests / local throwaway installs.
    | Dual-table: database path uses capabilities_idempotency (not approvals).
    |
    */
    'idempotency' => [
        'enabled' => true,
        // Aligned with approval.store=database (L-009 / REQ-070).
        'driver' => $env('CAPABILITIES_IDEMPOTENCY_DRIVER', 'database'),
        'connection' => $env('CAPABILITIES_IDEMPOTENCY_CONNECTION', null),
        'ttl_hours' => 24,
        'header' => 'Idempotency-Key',
        'warn_missing_key' => $env('CAPABILITIES_IDEMPOTENCY_WARN', true),
    ],

    'validation' => [
        'validate_output' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limits (D-013 / L-008)
    |--------------------------------------------------------------------------
    |
    | driver: memory | cache
    |   memory — process-local InMemoryRateLimiter (unit tests / single worker only)
    |   cache  — LaravelCacheRateLimiter over shared Illuminate cache (Redis, etc.)
    |
    | Package default is cache so multi-worker FPM/queue hosts share counters.
    | Unit tests must pass rate_limits.driver=memory or inject RateLimitCache
    | (ArrayRateLimitCache). Production multi-worker MUST keep driver=cache and
    | ensure the app cache store is shared (typically redis) — memory gives a
    | false sense of protection under scale-out.
    |
    */
    'rate_limits' => [
        'enabled' => true,
        'driver' => $env('CAPABILITIES_RATE_LIMITS_DRIVER', 'cache'),
        'defaults' => [
            'per_minute' => 60,
            'per_capability_per_minute' => 30,
        ],
        'agent_turn' => [
            'max_tool_calls' => 16,
        ],
    ],

    'observability' => [
        'metrics' => true,
        'tracing' => true,
    ],

    'clients' => [
        'oauth' => [],
        'token_abilities' => [
            'capabilities:cli' => 'cli',
        ],
        'privilege_order' => ['http', 'cli', 'mcp', 'agent', 'job'],
        'reject_upgrade_attempts' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Peer support matrix (D-011)
    |--------------------------------------------------------------------------
    |
    | Declared Composer-style version constraints for optional peers.
    | Runtime source of truth is PeerSupportMatrix; this key mirrors it for
    | published config / host overrides. PeerVersionProbe defaults to the matrix.
    | Do not use bare '*' as the sole constraint forever.
    |
    */
    'peers' => [
        'support' => class_exists(\Rawphp\Capabilities\Adapters\PeerSupportMatrix::class)
            ? \Rawphp\Capabilities\Adapters\PeerSupportMatrix::constraints()
            : [
                'laravel/ai' => ['^0.1', '^1.0'],
                'laravel/mcp' => ['^0.1', '^1.0'],
            ],
    ],
];
