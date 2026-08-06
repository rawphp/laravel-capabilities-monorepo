<?php

declare(strict_types=1);
use Rawphp\CapabilitiesAi\Package;

/**
 * rawphp/laravel-capabilities-ai package config.
 *
 * Uses getenv (not illuminate env()) so pure Pest unit boots can load this
 * file without a Laravel Application. Hosts may still publish and override.
 */
$env = static function (string $key, mixed $default = null): mixed {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return match (strtolower((string) $value)) {
        'true', '(true)' => true,
        'false', '(false)' => false,
        'empty', '(empty)' => '',
        'null', '(null)' => null,
        default => $value,
    };
};

return [
    /** Table prefix for package migrations/models (locked default). */
    'table_prefix' => $env('CAPABILITIES_AI_TABLE_PREFIX', 'capabilities_ai_'),

    'routes' => [
        'enabled' => (bool) $env('CAPABILITIES_AI_ROUTES_ENABLED', false),
        'prefix' => $env('CAPABILITIES_AI_ROUTE_PREFIX', 'capabilities-ai/chat'),
        'middleware' => ['api', 'auth:sanctum'],
    ],

    /**
     * Progress store: array (tests/default) | redis
     * Never store progress events in product MySQL tables.
     *
     * Outside APP_ENV=testing, progress.driver=array throws unless
     * CAPABILITIES_AI_ALLOW_UNSAFE=1 (local demos only — not production).
     */
    'progress' => [
        'driver' => $env('CAPABILITIES_AI_PROGRESS_DRIVER', 'array'),
        'redis_connection' => $env('CAPABILITIES_AI_PROGRESS_REDIS', 'default'),
        'redis_key_prefix' => $env('CAPABILITIES_AI_PROGRESS_PREFIX', 'capabilities_ai:progress:'),
    ],

    /**
     * LLM driver: fake (testing) | anthropic | (host custom binding)
     *
     * Outside APP_ENV=testing, llm.driver=fake throws unless
     * CAPABILITIES_AI_ALLOW_UNSAFE=1 (local demos only — not production).
     */
    'llm' => [
        'driver' => $env('CAPABILITIES_AI_LLM_DRIVER', 'fake'),
        'anthropic' => [
            'api_key' => $env('ANTHROPIC_API_KEY'),
            'model' => $env('CAPABILITIES_AI_ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
            'base_url' => $env('CAPABILITIES_AI_ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            /** Host-parity default (was hard-coded 1024; truncated long coach replies). */
            'max_tokens' => (int) $env('CAPABILITIES_AI_ANTHROPIC_MAX_TOKENS', 64000),
        ],
    ],

    /**
     * Escape hatch: allow progress.driver=array and llm.driver=fake outside testing.
     * CAPABILITIES_AI_ALLOW_UNSAFE=1 for local demos only. Default closed (false).
     * Prefer redis progress + real LlmClient (or host binding) in any real deploy.
     */
    'allow_unsafe' => (bool) $env('CAPABILITIES_AI_ALLOW_UNSAFE', false),

    /** Turn claim TTL seconds (worker heartbeat window). */
    'claim_ttl' => (int) $env('CAPABILITIES_AI_CLAIM_TTL', Package::DEFAULT_CLAIM_TTL),

    /**
     * Default RunTurnJob queue routing (happy path — no ConversationService rebind).
     * Empty/null → Laravel default queue/connection.
     */
    'queue' => [
        'connection' => $env('CAPABILITIES_AI_QUEUE_CONNECTION'),
        'name' => $env('CAPABILITIES_AI_QUEUE_NAME'),
    ],

    /**
     * Single gate for proposal accept/reject routes, TurnRunner fence extract, and history.
     * Phase 1 BC default true; greenfield docs: CAPABILITIES_AI_PROPOSALS_ENABLED=false.
     */
    'proposals' => [
        'enabled' => (bool) $env('CAPABILITIES_AI_PROPOSALS_ENABLED', true),
    ],

    /**
     * Stale-turn reaper thresholds for capabilities-ai:reap-stale-turns.
     * Host schedules the command — package does not auto-schedule (D-024).
     */
    'reaper' => [
        'stale_queued_minutes' => (int) $env('CAPABILITIES_AI_REAPER_STALE_QUEUED', 30),
        'stale_running_grace_seconds' => (int) $env('CAPABILITIES_AI_REAPER_RUNNING_GRACE', 60),
    ],

    /** Max tool-call rounds per turn before force-complete/fail. */
    'max_tool_rounds' => (int) $env('CAPABILITIES_AI_MAX_TOOL_ROUNDS', 8),

    /**
     * Eloquent user model used to resolve conversation.user_id → bus actor.
     * Empty → fall back to auth.providers.users.model. Missing both fails closed.
     */
    'user_model' => $env('CAPABILITIES_AI_USER_MODEL', null),
];
