<?php

namespace Rawphp\Capabilities\Adapters;

/**
 * Job / queue surface registration gate (SURF-003 / D-002).
 *
 * When surfaces.job.enabled is false, no RunCapability helpers are registered.
 * Pure / unit-testable — service provider maps this onto Laravel bus/job wiring.
 */
final class JobSurface
{
    /**
     * @param  array{enabled?: bool}  $jobConfig  config('capabilities.surfaces.job')
     */
    public static function isEnabled(array $jobConfig = []): bool
    {
        return (bool) ($jobConfig['enabled'] ?? true);
    }

    /**
     * Helpers exposed for queue / scheduler dispatch when the surface is enabled.
     *
     * @param  array{enabled?: bool}  $jobConfig
     * @return list<class-string|string>
     */
    public static function registeredHelpers(array $jobConfig = []): array
    {
        if (! self::isEnabled($jobConfig)) {
            return [];
        }

        return [
            RunCapabilityJob::class,
            'dispatch',
            'dispatchSync',
        ];
    }
}
