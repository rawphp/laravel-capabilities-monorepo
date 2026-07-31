<?php

namespace Rawphp\Capabilities\Adapters\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rawphp\Capabilities\Http\IlluminateHttpBridge;

/**
 * Laravel-facing thin wrapper around {@see CapabilityController}.
 *
 * Accepts Illuminate Request (container-injectable), maps via
 * {@see IlluminateHttpBridge}, returns JsonResponse for the HTTP kernel (L-001).
 */
final class IlluminateCapabilityController
{
    public function __construct(
        private readonly CapabilityController $inner,
    ) {}

    public function list(Request $request): JsonResponse
    {
        return IlluminateHttpBridge::toIlluminate(
            $this->inner->list(IlluminateHttpBridge::fromIlluminate($request)),
        );
    }

    public function describe(Request $request, string $name): JsonResponse
    {
        return IlluminateHttpBridge::toIlluminate(
            $this->inner->describe(IlluminateHttpBridge::fromIlluminate($request), $name),
        );
    }

    public function invoke(Request $request, string $name): JsonResponse
    {
        return IlluminateHttpBridge::toIlluminate(
            $this->inner->invoke(IlluminateHttpBridge::fromIlluminate($request), $name),
        );
    }

    public function health(Request $request): JsonResponse
    {
        return IlluminateHttpBridge::toIlluminate(
            $this->inner->health(IlluminateHttpBridge::fromIlluminate($request)),
        );
    }
}
