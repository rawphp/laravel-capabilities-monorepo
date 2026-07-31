<?php

namespace Rawphp\Capabilities\Http;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\ErrorCodeMap;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Wire HTTP response from a capability result / catalog payload (unit-test friendly).
 *
 * Implements {@see Responsable} so the Laravel kernel can emit this DTO when returned
 * from a route action (L-001). Prefer thin Illuminate wrappers +
 * {@see IlluminateHttpBridge::toIlluminate()} for typed controller return values.
 */
final class HttpResponse implements Responsable
{
    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly array $body,
        public readonly array $headers = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $headers
     */
    public static function ok(mixed $data = null, array $meta = [], int $status = 200, array $headers = []): self
    {
        return new self(
            status: $status,
            body: [
                'ok' => true,
                'data' => $data,
                'meta' => $meta,
            ],
            headers: $headers,
        );
    }

    /**
     * @param  array<string, string>  $headers
     */
    public static function fromResult(CapabilityResult $result, bool $cliEnvelope = false, array $headers = []): self
    {
        $body = $cliEnvelope
            ? CliJsonEnvelope::fromResult($result)
            : $result->toArray();

        if ($result->isOk()) {
            return new self(status: 200, body: $body, headers: $headers);
        }

        $code = $result->errorCode() ?? 'internal';
        $status = (int) ($result->error['http_status'] ?? ErrorCodeMap::httpStatus($code));

        return new self(status: $status, body: $body, headers: $headers);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $headers
     */
    public static function failure(
        string $code,
        string $message,
        array $extra = [],
        array $meta = [],
        array $headers = [],
        bool $cliEnvelope = false,
    ): self {
        return self::fromResult(
            CapabilityResult::failure($code, $message, $extra, $meta),
            $cliEnvelope,
            $headers,
        );
    }

    public function isOk(): bool
    {
        return ($this->body['ok'] ?? false) === true;
    }

    public function errorCode(): ?string
    {
        $code = $this->body['error']['code'] ?? null;

        return is_string($code) ? $code : null;
    }

    /**
     * Illuminate kernel entry — maps to JsonResponse with status/headers/body.
     *
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse|SymfonyResponse
    {
        return IlluminateHttpBridge::toIlluminate($this);
    }

    /**
     * Explicit conversion helper (same as bridge).
     */
    public function toIlluminate(): JsonResponse
    {
        return IlluminateHttpBridge::toIlluminate($this);
    }
}
