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
    public function __construct(
        private readonly ApprovalController $inner,
    ) {}

    public function accept(Request $request, string $id): JsonResponse
    {
        return IlluminateHttpBridge::toIlluminate(
            $this->inner->accept(IlluminateHttpBridge::fromIlluminate($request), $id),
        );
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        return IlluminateHttpBridge::toIlluminate(
            $this->inner->reject(IlluminateHttpBridge::fromIlluminate($request), $id),
        );
    }
}
