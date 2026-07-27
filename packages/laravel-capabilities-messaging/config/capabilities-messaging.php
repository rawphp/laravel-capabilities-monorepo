<?php

/**
 * Scaffold — detailed channel config (tokens, webhook secrets, identity link mode).
 * Core does not ship Bot API env requirements.
 */
return [
    'telegram' => [
        'enabled' => env('CAPABILITIES_TELEGRAM', true),
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],
];
