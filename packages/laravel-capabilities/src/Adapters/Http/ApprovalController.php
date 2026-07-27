<?php

namespace Rawphp\Capabilities\Adapters\Http;

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Http\HttpAuthGate;
use Rawphp\Capabilities\Http\HttpRequestContext;
use Rawphp\Capabilities\Http\HttpResponse;
use Rawphp\Capabilities\Support\SystemActor;

/**
 * HTTP approval accept/reject surface — shared by UI, CLI, and API (D-006 / D-009).
 *
 * Maps to {@see ApprovalManager}; never reimplements domain run outside registry.
 */
final class ApprovalController
{
    /**
     * @param  array<string, mixed>  $httpConfig
     */
    public function __construct(
        private readonly ApprovalManager $approvals,
        private readonly array $httpConfig = [],
        private readonly ?HttpAuthGate $authGate = null,
    ) {}

    public function accept(HttpRequestContext $request, string $id): HttpResponse
    {
        if ($deny = $this->denyIfUnauthenticated($request, 'approval_accept')) {
            return $deny;
        }

        if ($block = $this->denySystemActor($request)) {
            return $block;
        }

        $approver = $request->user;
        if ($approver === null) {
            return HttpResponse::failure('unauthenticated', 'Authentication required.');
        }

        $result = $this->approvals->accept($id, $approver, [
            'reason' => is_array($request->jsonBody) ? ($request->jsonBody['reason'] ?? null) : null,
        ]);

        return HttpResponse::fromResult($result, cliEnvelope: $request->wantsCliEnvelope());
    }

    public function reject(HttpRequestContext $request, string $id): HttpResponse
    {
        if ($deny = $this->denyIfUnauthenticated($request, 'approval_reject')) {
            return $deny;
        }

        if ($block = $this->denySystemActor($request)) {
            return $block;
        }

        $approver = $request->user;
        if ($approver === null) {
            return HttpResponse::failure('unauthenticated', 'Authentication required.');
        }

        $reason = is_array($request->jsonBody) ? ($request->jsonBody['reason'] ?? null) : null;
        $result = $this->approvals->reject($id, $approver, is_string($reason) ? $reason : null);

        return HttpResponse::fromResult($result, cliEnvelope: $request->wantsCliEnvelope());
    }

    private function denyIfUnauthenticated(HttpRequestContext $request, string $routeKey): ?HttpResponse
    {
        $authType = $request->authKind ?? ($request->authenticated ? HttpAuthGate::AUTH_USER : HttpAuthGate::AUTH_NONE);
        $gate = $this->authGate ?? new HttpAuthGate([
            'health_public' => (bool) ($this->httpConfig['health_public'] ?? false),
        ]);

        if (! $gate->allows($routeKey, $authType, $request)) {
            return HttpResponse::failure('unauthenticated', 'Authentication required.');
        }

        return null;
    }

    private function denySystemActor(HttpRequestContext $request): ?HttpResponse
    {
        if ($request->user instanceof SystemActor) {
            return HttpResponse::failure(
                'forbidden',
                'SystemActor cannot accept or reject approvals.',
            );
        }

        return null;
    }
}
