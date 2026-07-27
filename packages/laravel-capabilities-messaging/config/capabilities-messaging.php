<?php

/**
 * Conversation surfaces config (Telegram first).
 * Secrets are validated on first webhook / setup / outbound notify (D-021), not at boot.
 */
return [
    'telegram' => [
        'enabled' => env('CAPABILITIES_TELEGRAM', true),
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'callback_secret' => env('TELEGRAM_CALLBACK_SECRET', env('TELEGRAM_WEBHOOK_SECRET')),
        'callback_ttl_seconds' => (int) env('TELEGRAM_CALLBACK_TTL_SECONDS', 900),
    ],

    /** Agent profile (D-008) — never dump full catalog. Required on first bot traffic. */
    'agent_profile' => env('CAPABILITIES_MESSAGING_AGENT_PROFILE', 'support'),

    'identity' => [
        /** code_link | allowlist */
        'mode' => env('CAPABILITIES_MESSAGING_IDENTITY_MODE', 'code_link'),
        'code_ttl_seconds' => (int) env('CAPABILITIES_MESSAGING_LINK_CODE_TTL', 600),
        /** @var list<array{telegram_user_id: string, laravel_user_id: string, tenant_id?: string}> */
        'allowlist' => [],
    ],

    /**
     * Skip deferred messaging secret checks in non-production CI only (D-021).
     * Forbidden in production — ignored / fails closed when APP_ENV=production.
     */
    'skip_boot_checks' => (bool) env('CAPABILITIES_SKIP_BOOT_CHECKS', false),
];
