<?php

namespace Rawphp\Capabilities\Adapters\Artisan;

/**
 * Declarative Artisan command table for optional in-server ops (D-002 / D-016).
 *
 * Artisan is **ops only** — never the product CLI (that is the Go client, caller=cli).
 * Pure / unit-testable: no Illuminate Console Kernel required.
 */
final class ArtisanCommandTable
{
    /** Ops role — must never claim product CLI. */
    public const ROLE = 'ops';

    public const CALLER = 'artisan';

    public const CMD_RUN = 'capability:run';

    /**
     * @param  array{enabled?: bool}  $artisanConfig  config('capabilities.surfaces.artisan')
     */
    public static function isEnabled(array $artisanConfig = []): bool
    {
        return (bool) ($artisanConfig['enabled'] ?? true);
    }

    /**
     * @param  array{enabled?: bool}  $artisanConfig
     * @return list<array{
     *     key: string,
     *     signature: string,
     *     description: string,
     *     caller: string,
     *     role: string,
     *     class: class-string
     * }>
     */
    public static function commands(array $artisanConfig = []): array
    {
        if (! self::isEnabled($artisanConfig)) {
            return [];
        }

        return [
            [
                'key' => 'run',
                'signature' => 'capability:run {name} {--acting-as=} {--system=} {--tenant=} {--input=}',
                'description' => 'Invoke a capability in-process as an operator (requires --acting-as or --system for mutations).',
                'caller' => self::CALLER,
                'role' => self::ROLE,
                'class' => RunCapabilityCommand::class,
            ],
        ];
    }

    /**
     * Command classes safe for ServiceProvider::$this->commands().
     *
     * @param  array{enabled?: bool}  $artisanConfig
     * @return list<class-string>
     */
    public static function commandClasses(array $artisanConfig = []): array
    {
        $classes = [];
        foreach (self::commands($artisanConfig) as $row) {
            $class = $row['class'] ?? null;
            if (is_string($class) && $class !== '') {
                $classes[] = $class;
            }
        }

        return array_values(array_unique($classes));
    }
}
