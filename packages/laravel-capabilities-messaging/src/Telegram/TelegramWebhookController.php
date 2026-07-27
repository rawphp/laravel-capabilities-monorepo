<?php

namespace Rawphp\CapabilitiesMessaging\Telegram;

use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\Support\FakeQueue;
use Rawphp\CapabilitiesMessaging\Support\UpdateQueue;
use RuntimeException;

/**
 * Inbound Telegram webhook — verify secret, enqueue ProcessTelegramUpdate.
 *
 * Never invokes CapabilityRegistry or domain run() (D-007).
 */
final class TelegramWebhookController
{
    public const SECRET_HEADER = 'X-Telegram-Bot-Api-Secret-Token';

    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $logs = [];

    private int $registryInvokeCount = 0;

    private readonly UpdateQueue $queue;

    public function __construct(
        private readonly MessagingConfig $config,
        ?UpdateQueue $queue = null,
    ) {
        $this->queue = $queue ?? new FakeQueue;
    }

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

    /**
     * Intentionally never call the registry from the webhook (guard for tests).
     */
    public function registryInvokeCount(): int
    {
        return $this->registryInvokeCount;
    }

    public function queue(): UpdateQueue
    {
        return $this->queue;
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function logs(): array
    {
        return $this->logs;
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
            if (strcasecmp((string) $key, 'x-telegram-bot-api-secret-token') === 0) {
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
        $this->logs[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }
}
