<?php

namespace Rawphp\Capabilities\Adapters\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rawphp\Capabilities\Http\IlluminateHttpBridge;

/**
 * Laravel-facing thin wrapper around {@see AuthController} (L-001).
 */
final class IlluminateAuthController
{
    public function __construct(
        private readonly AuthController $inner,
    ) {}

    public function token(Request $request): JsonResponse
    {
        return IlluminateHttpBridge::toIlluminate(
            $this->inner->token(IlluminateHttpBridge::fromIlluminate($request)),
        );
    }

    public function device(Request $request): JsonResponse
    {
        return IlluminateHttpBridge::toIlluminate(
            $this->inner->device(IlluminateHttpBridge::fromIlluminate($request)),
        );
    }

    public function oauthCallback(Request $request): JsonResponse
    {
        return IlluminateHttpBridge::toIlluminate(
            $this->inner->oauthCallback(IlluminateHttpBridge::fromIlluminate($request)),
        );
    }
}
