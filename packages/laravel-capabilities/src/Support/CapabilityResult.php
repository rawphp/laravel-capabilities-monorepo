<?php

namespace Rawphp\Capabilities\Support;

/**
 * Wire / internal result envelope (RES-001, D-018).
 *
 * Success: ok + data + meta.
 * Failure / approval_required: ok=false + error envelope fields.
 * Arrays are only used at wire edges; domain output DTOs sit in {@see $data}.
 */
final class CapabilityResult
{
    /**
     * @param  array<string, mixed>|null  $error
     * @param  array<string, mixed>  $meta
     */
    private function __construct(
        public readonly bool $ok,
        public readonly mixed $data = null,
        public readonly ?array $error = null,
        public readonly array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function ok(mixed $data = null, array $meta = []): self
    {
        return new self(
            ok: true,
            data: $data,
            error: null,
            meta: $meta,
        );
    }

    /**
     * Alias for {@see ok()} used by registry/schema paths.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = null, array $meta = []): self
    {
        return self::ok($data, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function approvalRequired(
        string $approvalId,
        ?string $message = null,
        array $meta = [],
    ): self {
        $wire = ErrorCodeMap::wireFields('approval_required');

        return new self(
            ok: false,
            data: null,
            error: [
                'code' => 'approval_required',
                'message' => $message ?? 'Approval required',
                'violations' => [],
                'approval_id' => $approvalId,
                'request_id' => $meta['request_id'] ?? null,
                'retryable' => $wire['retryable'],
                'http_status' => $wire['http_status'],
                'cli_exit' => $wire['cli_exit'],
            ],
            meta: $meta,
        );
    }

    /**
     * @param  array<string, mixed>  $extra  Merged into the error envelope (violations, retryable, …)
     * @param  array<string, mixed>  $meta
     */
    public static function failure(
        string $code,
        string $message,
        array $extra = [],
        array $meta = [],
    ): self {
        $wire = ErrorCodeMap::wireFields($code);
        $error = array_merge([
            'code' => $code,
            'message' => $message,
            'violations' => [],
            'approval_id' => null,
            'request_id' => $meta['request_id'] ?? ($extra['request_id'] ?? null),
            'retryable' => $wire['retryable'],
            'http_status' => $wire['http_status'],
            'cli_exit' => $wire['cli_exit'],
        ], $extra);

        $error['code'] = $code;
        $error['message'] = $message;
        // Defaults from map unless caller overrides retryable explicitly in $extra.
        if (! array_key_exists('retryable', $extra)) {
            $error['retryable'] = $wire['retryable'];
        }
        if (! array_key_exists('http_status', $extra)) {
            $error['http_status'] = $wire['http_status'];
        }
        if (! array_key_exists('cli_exit', $extra)) {
            $error['cli_exit'] = $wire['cli_exit'];
        }

        return new self(
            ok: false,
            data: null,
            error: $error,
            meta: $meta,
        );
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function isFailed(): bool
    {
        return ! $this->ok && ! $this->isApprovalRequired();
    }

    public function isApprovalRequired(): bool
    {
        return ($this->error['code'] ?? null) === 'approval_required';
    }

    public function isReplay(): bool
    {
        return (bool) ($this->meta['idempotent_replay'] ?? false);
    }

    public function approvalId(): ?string
    {
        $id = $this->error['approval_id'] ?? null;

        return is_string($id) ? $id : null;
    }

    public function errorCode(): ?string
    {
        $code = $this->error['code'] ?? null;

        return is_string($code) ? $code : null;
    }

    /**
     * @return array{ok: bool, data?: mixed, error?: array<string, mixed>, meta: array<string, mixed>}
     */
    public function toArray(): array
    {
        $payload = [
            'ok' => $this->ok,
            'meta' => $this->meta,
        ];

        if ($this->ok) {
            $payload['data'] = $this->data;
        } else {
            $payload['error'] = $this->error;
        }

        return $payload;
    }

    public function assertOk(): self
    {
        if (! $this->ok) {
            $this->failAssert(sprintf(
                'Failed asserting that result is ok (code=%s).',
                $this->errorCode() ?? 'null',
            ));
        }

        return $this;
    }

    public function assertFailed(?string $code = null): self
    {
        if ($this->ok || $this->isApprovalRequired()) {
            $this->failAssert('Failed asserting that result is a failure.');
        }

        if ($code !== null && $this->errorCode() !== $code) {
            $this->failAssert(sprintf(
                'Failed asserting that result error code is "%s" (got "%s").',
                $code,
                $this->errorCode() ?? 'null',
            ));
        }

        return $this;
    }

    public function assertForbidden(): self
    {
        return $this->assertFailed('forbidden');
    }

    public function assertConflict(): self
    {
        return $this->assertFailed('conflict');
    }

    public function assertExpired(): self
    {
        return $this->assertFailed('expired');
    }

    public function assertApprovalRequired(): self
    {
        if (! $this->isApprovalRequired()) {
            $this->failAssert(sprintf(
                'Failed asserting that result is approval_required (code=%s).',
                $this->errorCode() ?? 'null',
            ));
        }

        return $this;
    }

    public function assertReplay(): self
    {
        if (! $this->isReplay()) {
            $this->failAssert('Failed asserting that result is an idempotent replay.');
        }

        return $this;
    }

    private function failAssert(string $message): never
    {
        throw new CapabilityResultAssertionException($message);
    }
}
