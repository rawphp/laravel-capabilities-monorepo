<?php

/**
 * Messaging routes — registered when telegram channel is enabled.
 * Secrets validated on first request, not at boot (D-021).
 */

use Illuminate\Support\Facades\Route;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramWebhookController;

Route::post('/capabilities/messaging/telegram/webhook', function () {
    // Thin HTTP edge — controller is unit-tested with arrays; container resolves real instance in app.
    /** @var TelegramWebhookController $controller */
    $controller = app(TelegramWebhookController::class);
    $headers = [];
    foreach (request()->headers->all() as $key => $values) {
        $headers[$key] = $values[0] ?? '';
    }
    $body = request()->all();
    $result = $controller->handle($headers, is_array($body) ? $body : []);

    return response()->json(
        ['ok' => $result['ok'], 'error' => $result['error'] ?? null],
        $result['status'],
    );
})->name('capabilities.messaging.telegram.webhook');
