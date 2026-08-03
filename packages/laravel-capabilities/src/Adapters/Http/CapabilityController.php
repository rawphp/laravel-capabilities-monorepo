<?php

namespace Rawphp\Capabilities\Adapters\Http;

use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Http\CallerDeriver;
use Rawphp\Capabilities\Http\DetectsCaller;
use Rawphp\Capabilities\Http\HttpAuthGate;
use Rawphp\Capabilities\Http\HttpRequestContext;
use Rawphp\Capabilities\Http\HttpResponse;
use Rawphp\Capabilities\Support\CapabilityResult;
use Throwable;

/**
 * ONE invoke/catalog HTTP API (D-009). Product CLI is a client of this controller.
 *
 * All mutation goes through {@see CapabilityBus::invoke()} — no dual path.
 */
final class CapabilityController
{
    use DetectsCaller;

    /** @var array<string, mixed>|null Last options passed to registry invoke (tests). */
    private ?array $lastInvokeOptions = null;

    /**
     * @param  array<string, mixed>  $clientsConfig  config('capabilities.clients')
     * @param  array<string, mixed>  $httpConfig  config('capabilities.surfaces.http')
     */
    public function __construct(
        private readonly CapabilityBus $registry,
        private readonly array $clientsConfig = [],
        private readonly array $httpConfig = [],
        private readonly ?HttpAuthGate $authGate = null,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function lastInvokeOptions(): ?array
    {
        return $this->lastInvokeOptions;
    }

    public function list(HttpRequestContext $request): HttpResponse
    {
        if ($deny = $this->denyIfUnauthenticated($request, 'list')) {
            return $deny;
        }

        $caller = $this->resolveCaller($request);
        $includeSchemas = (bool) ($request->query['include_schemas'] ?? false)
            || (isset($request->query['include']) && str_contains((string) $request->query['include'], 'schemas'));

        $envelope = $this->registry->catalog()->listEnvelope($includeSchemas, [
            'caller' => $caller['caller'],
        ]);

        return HttpResponse::ok($envelope, [
            'caller' => $caller['caller'],
            'derived_caller' => $caller['derived'],
        ], headers: $this->presentationHeaders($request));
    }

    public function describe(HttpRequestContext $request, string $name): HttpResponse
    {
        if ($deny = $this->denyIfUnauthenticated($request, 'describe')) {
            return $deny;
        }

        $caller = $this->resolveCaller($request);

        try {
            $detail = $this->registry->catalog()->describe($name);
        } catch (Throwable) {
            return HttpResponse::failure(
                'not_found',
                sprintf('Unknown capability "%s".', $name),
                meta: ['caller' => $caller['caller']],
                headers: $this->presentationHeaders($request),
                cliEnvelope: $request->wantsCliEnvelope(),
            );
        }

        return HttpResponse::ok($detail, [
            'caller' => $caller['caller'],
            'derived_caller' => $caller['derived'],
        ], headers: $this->presentationHeaders($request));
    }

    public function invoke(HttpRequestContext $request, string $name): HttpResponse
    {
        if ($deny = $this->denyIfUnauthenticated($request, 'invoke')) {
            return $deny;
        }

        if ($request->malformedJson) {
            return HttpResponse::failure(
                'validation_failed',
                'Malformed JSON body.',
                extra: ['violations' => [['field' => 'body', 'message' => 'invalid_json']]],
                headers: $this->presentationHeaders($request),
                cliEnvelope: $request->wantsCliEnvelope(),
            );
        }

        $caller = $this->resolveCaller($request);
        $input = $request->jsonBody ?? [];
        if (! is_array($input)) {
            $input = [];
        }

        $headerName = strtolower((string) ($this->httpConfig['idempotency_header'] ?? 'idempotency-key'));
        $idempotencyKey = $request->header($headerName) ?? $request->idempotencyKey();

        // Never trust body caller / X-Capabilities-Caller alone (D-022).
        $options = array_filter([
            'caller' => $caller['caller'],
            'actor' => $request->user,
            'idempotency_key' => $idempotencyKey,
        ], static fn ($v) => $v !== null);

        $this->lastInvokeOptions = $options;
        $result = $this->registry->invoke($name, $input, $options);

        return $this->wireResult($result, $request, $caller);
    }

    public function health(HttpRequestContext $request): HttpResponse
    {
        $gate = $this->authGate();
        $authType = $request->authKind ?? ($request->authenticated ? HttpAuthGate::AUTH_USER : HttpAuthGate::AUTH_NONE);
        if (! $gate->allowsHealth($authType, $request)) {
            return HttpResponse::failure(
                'unauthenticated',
                'Authentication required.',
                headers: $this->presentationHeaders($request),
                cliEnvelope: $request->wantsCliEnvelope(),
            );
        }

        $report = $this->registry->catalog()->health();

        return HttpResponse::ok($report, headers: $this->presentationHeaders($request));
    }

    /**
     * @return array{caller: string, derived: string, rejected: bool, reason: ?string}
     */
    public function resolveCaller(HttpRequestContext $request): array
    {
        $credential = $request->credential;
        if ($credential === [] && $request->authenticated) {
            // Authenticated HTTP with no mapped abilities → http (D-022).
            $credential = ['adapter' => 'http'];
        }

        return $this->callerDeriver($this->callerConfig())->resolve(
            $credential,
            $request->claimedCallerHeader(),
        );
    }

    /**
     * @return array{
     *     token_abilities?: array<string, string>,
     *     oauth?: array<string, string>,
     *     privilege_order?: list<string>,
     *     reject_upgrade_attempts?: bool
     * }
     */
    protected function callerConfig(): array
    {
        return [
            'token_abilities' => $this->clientsConfig['token_abilities'] ?? ['capabilities:cli' => 'cli'],
            'oauth' => $this->clientsConfig['oauth'] ?? [],
            'privilege_order' => $this->clientsConfig['privilege_order'] ?? CallerDeriver::DEFAULT_PRIVILEGE_ORDER,
            'reject_upgrade_attempts' => (bool) ($this->clientsConfig['reject_upgrade_attempts'] ?? false),
        ];
    }

    /**
     * @param  array{caller: string, derived: string, rejected: bool, reason: ?string}  $caller
     */
    private function wireResult(CapabilityResult $result, HttpRequestContext $request, array $caller): HttpResponse
    {
        $meta = $result->meta;
        $meta['caller'] = $caller['caller'];
        $meta['derived_caller'] = $caller['derived'];
        if ($caller['reason'] !== null) {
            $meta['caller_claim_reason'] = $caller['reason'];
        }

        $wired = $result->isOk()
            ? CapabilityResult::ok($result->data, $meta)
            : ($result->isApprovalRequired()
                ? CapabilityResult::approvalRequired(
                    (string) $result->approvalId(),
                    (string) ($result->error['message'] ?? 'Approval required'),
                    $meta,
                )
                : CapabilityResult::failure(
                    (string) $result->errorCode(),
                    (string) ($result->error['message'] ?? 'error'),
                    array_diff_key($result->error ?? [], array_flip(['code', 'message'])),
                    $meta,
                ));

        return HttpResponse::fromResult(
            $wired,
            cliEnvelope: $request->wantsCliEnvelope(),
            headers: $this->presentationHeaders($request),
        );
    }

    private function denyIfUnauthenticated(HttpRequestContext $request, string $routeKey): ?HttpResponse
    {
        $authType = $request->authKind ?? ($request->authenticated ? HttpAuthGate::AUTH_USER : HttpAuthGate::AUTH_NONE);
        if (! $this->authGate()->allows($routeKey, $authType, $request)) {
            return HttpResponse::failure(
                'unauthenticated',
                'Authentication required.',
                headers: $this->presentationHeaders($request),
                cliEnvelope: $request->wantsCliEnvelope(),
            );
        }

        return null;
    }

    private function authGate(): HttpAuthGate
    {
        return $this->authGate ?? new HttpAuthGate([
            'health_public' => (bool) ($this->httpConfig['health_public'] ?? false),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function presentationHeaders(HttpRequestContext $request): array
    {
        if ($request->wantsCliEnvelope()) {
            return ['Content-Type' => 'application/vnd.capabilities.cli+json'];
        }

        return ['Content-Type' => 'application/json'];
    }
}
