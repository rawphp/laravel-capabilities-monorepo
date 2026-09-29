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
        /** Redis key lifetime (seconds) after a turn's last event; progress is transient. */
        'ttl_seconds' => (int) $env('CAPABILITIES_AI_PROGRESS_TTL', 86400),
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
            /**
             * Host-parity ceiling (was hard-coded 1024; truncated long coach replies). A ceiling,
             * not a target: requests are non-streaming, so the reply a turn can actually get is
             * whatever the model writes within `timeout` — a longer one fails the turn as a
             * retryable timeout. Raise timeout and claim_ttl together for very long replies.
             */
            'max_tokens' => (int) $env('CAPABILITIES_AI_ANTHROPIC_MAX_TOKENS', 64000),
            /**
             * 429 retries per request (honours Retry-After, capped at 60s); 0 disables. A retry
             * runs with its timeout capped to what is left of the turn's claim_ttl (counted from
             * the job start), and is skipped when the wait would leave under 10s; the 429 then
             * fails the turn as retryable instead of the worker being killed mid-request.
             */
            'max_retries' => (int) $env('CAPABILITIES_AI_ANTHROPIC_MAX_RETRIES', 2),
            /**
             * Per-request HTTP timeout in seconds (Laravel's client default is 30s, too short for
             * long replies). Must be below claim_ttl (the turn job timeout) or the anthropic
             * LlmClient refuses to build. One turn job makes a request per tool round, each capped
             * to what is left of claim_ttl (minus 2s); a round is refused, failing the turn as
             * retryable, only when under 10s are left. Raise claim_ttl for long multi-round turns.
             */
            'timeout' => (int) $env('CAPABILITIES_AI_ANTHROPIC_TIMEOUT', 110),
        ],
    ],

    /**
     * Escape hatch: allow progress.driver=array and llm.driver=fake outside testing.
     * CAPABILITIES_AI_ALLOW_UNSAFE=1|true|yes|on for local demos only; any other value
     * (0, no, off, …) stays closed. Default closed (false).
     * Prefer redis progress + real LlmClient (or host binding) in any real deploy.
     */
    'allow_unsafe' => filter_var($env('CAPABILITIES_AI_ALLOW_UNSAFE', false), FILTER_VALIDATE_BOOLEAN),

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

    /**
     * Pressure valve: ceiling on queued + running turns across all conversations.
     * At the ceiling, new messages are refused (HTTP 429 rate_limited) with
     * nothing persisted or dispatched. 0 = unlimited (default).
     */
    'max_concurrent_turns' => (int) $env('CAPABILITIES_AI_MAX_CONCURRENT_TURNS', 0),

    /**
     * D-013: accepted chat messages (each starts an LLM turn) per authenticated user per
     * minute, via core's RateLimiter. Over the limit → HTTP 429 rate_limited, nothing
     * persisted or dispatched. 0 disables.
     */
    'turns_per_minute' => (int) $env('CAPABILITIES_AI_TURNS_PER_MINUTE', 20),

    /**
     * Longest accepted chat message `content`, in characters. Longer → HTTP 422
     * validation_failed, nothing persisted or dispatched. 0 = no cap.
     */
    'max_message_chars' => (int) $env('CAPABILITIES_AI_MAX_MESSAGE_CHARS', 32000),

    /** Max LLM rounds per turn; a turn still asking for tools after the last round fails (not retryable). */
    'max_tool_rounds' => (int) $env('CAPABILITIES_AI_MAX_TOOL_ROUNDS', 8),

    /**
     * Eloquent user model used to resolve conversation.user_id → bus actor.
     * Empty → fall back to auth.providers.users.model. Missing both fails closed.
     */
    'user_model' => $env('CAPABILITIES_AI_USER_MODEL', null),
];
