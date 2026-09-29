<?php

namespace Rawphp\CapabilitiesMessaging\Telegram;

use Psr\Log\LoggerInterface;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\Support\UpdateQueue;
use RuntimeException;

/**
 * Inbound Telegram webhook — verify secret, enqueue ProcessTelegramUpdate.
 *
 * Never invokes CapabilityRegistry or domain run() (D-007).
 * UpdateQueue is required (L-004) — no FakeQueue default outside tests.
 * Production container injects LaravelUpdateQueue; unit tests inject FakeQueue.
 * Rejections and queue failures go to the optional PSR-3 logger (D-019).
 */
final class TelegramWebhookController
{
    public const SECRET_HEADER = 'X-Telegram-Bot-Api-Secret-Token';

    public function __construct(
        private readonly MessagingConfig $config,
        private readonly UpdateQueue $queue,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Handle an inbound webhook request (unit-testable; no Laravel Request required).
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body  Telegram Update
     * @return array{ok: bool, status: int, queued: bool, error?: string}
     */
    public function handle(array $headers, array $body): array
    {
        try {
            $this->config->requireTelegramSecrets();
        } catch (RuntimeException $e) {
            $this->log('error', $e->getMessage(), ['phase' => 'secrets']);

            return ['ok' => false, 'status' => 503, 'queued' => false, 'error' => $e->getMessage()];
        }

        $provided = $this->extractSecret($headers);
        $expected = $this->config->webhookSecret();

        if ($provided === null || $provided === '' || $expected === null || ! hash_equals($expected, $provided)) {
            $this->log('warning', 'Invalid or missing webhook secret', ['phase' => 'verify_webhook_secret']);

            return ['ok' => false, 'status' => 401, 'queued' => false, 'error' => 'invalid_webhook_secret'];
        }

        if ($body === [] || (! isset($body['update_id']) && ! isset($body['message']) && ! isset($body['callback_query']))) {
            $this->log('warning', 'Forged or empty webhook body', ['phase' => 'body']);

            return ['ok' => false, 'status' => 400, 'queued' => false, 'error' => 'invalid_body'];
        }

        try {
            $this->queue->push(ProcessTelegramUpdate::class, ['update' => $body]);
        } catch (RuntimeException $e) {
            $this->log('error', $e->getMessage(), ['phase' => 'queue']);

            return ['ok' => false, 'status' => 500, 'queued' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'status' => 200, 'queued' => true];
    }

    public function queue(): UpdateQueue
    {
        return $this->queue;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function extractSecret(array $headers): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, self::SECRET_HEADER) === 0) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $this->logger?->log($level, $message, $context);
    }
}
