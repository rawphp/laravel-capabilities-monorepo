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
    /**
     * @param  array<string, string>  $tokenAbilityMap  `clients.token_abilities` for authKind (L-110)
     */
    public function __construct(
        private readonly CapabilityController $inner,
        private readonly array $tokenAbilityMap = [],
    ) {}

    public function list(Request $request): JsonResponse
    {
        return IlluminateHttpBridge::toIlluminate(
            $this->inner->list(IlluminateHttpBridge::fromIlluminate($request, $this->tokenAbilityMap)),
        );
    }

    public function describe(Request $request, string $name): JsonResponse
    {
        return IlluminateHttpBridge::toIlluminate(
            $this->inner->describe(IlluminateHttpBridge::fromIlluminate($request, $this->tokenAbilityMap), $name),
        );
    }

    public function invoke(Request $request, string $name): JsonResponse
    {
        return IlluminateHttpBridge::toIlluminate(
            $this->inner->invoke(IlluminateHttpBridge::fromIlluminate($request, $this->tokenAbilityMap), $name),
        );
    }

    public function health(Request $request): JsonResponse
    {
        return IlluminateHttpBridge::toIlluminate(
            $this->inner->health(IlluminateHttpBridge::fromIlluminate($request, $this->tokenAbilityMap)),
        );
    }
}
