<?php

namespace Rawphp\Capabilities\Adapters\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rawphp\Capabilities\Http\IlluminateHttpBridge;

/**
 * Laravel-facing thin wrapper around {@see ApprovalController} (L-001).
 */
final class IlluminateApprovalController
{
    /**
     * @param  array<string, string>  $tokenAbilityMap  `clients.token_abilities` for authKind (L-110)
     */
    public function __construct(
        private readonly ApprovalController $inner,
        private readonly array $tokenAbilityMap = [],
    ) {}

    public function accept(Request $request, string $id): JsonResponse
    {
        return IlluminateHttpBridge::toIlluminate(
            $this->inner->accept(IlluminateHttpBridge::fromIlluminate($request, $this->tokenAbilityMap), $id),
        );
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        return IlluminateHttpBridge::toIlluminate(
            $this->inner->reject(IlluminateHttpBridge::fromIlluminate($request, $this->tokenAbilityMap), $id),
        );
    }
}
