<?php

namespace Rawphp\Capabilities\Adapters\Artisan;

use Rawphp\Capabilities\Approval\ResumeSchedulePlan;

/**
 * Pure Artisan ops command registration plan (REQ-024).
 *
 * Provider maps this onto $this->commands() when surfaces.artisan.enabled.
 */
final class ArtisanCommandRegistrar
{
    /**
     * @param  array{enabled?: bool}  $artisanConfig
     * @return list<array{signature: string, class: class-string, role: string, caller: string}>
     */
    public static function definitions(array $artisanConfig = []): array
    {
        $out = [];
        foreach (ArtisanCommandTable::commands($artisanConfig) as $row) {
            $out[] = [
                'signature' => (string) $row['signature'],
                'class' => $row['class'],
                'role' => (string) $row['role'],
                'caller' => (string) $row['caller'],
            ];
        }

        return $out;
    }

    /**
     * Ops invoke surface commands (`surfaces.artisan.enabled` owns this table).
     *
     * @param  array{enabled?: bool}  $artisanConfig
     * @return list<class-string>
     */
    public static function classes(array $artisanConfig = []): array
    {
        return ArtisanCommandTable::commandClasses($artisanConfig);
    }

    /**
     * Package infrastructure commands, registered whatever the ops surface flag says:
     * the discovery cache pair (L-015) and the approval crash-recovery sweep whenever
     * `approval.*` schedules it (L-108 / L-014).
     *
     * @param  array<string, mixed>  $approvalConfig  `config('capabilities.approval')`
     * @return list<class-string>
     */
    public static function infrastructure(array $approvalConfig = []): array
    {
        $classes = [CacheCapabilitiesCommand::class, ClearCapabilitiesCommand::class];
        if (ResumeSchedulePlan::fromConfig($approvalConfig) !== null) {
            $classes[] = ResumeApprovalsCommand::class;
        }

        return $classes;
    }

    /**
     * Everything the provider registers: {@see classes} plus {@see infrastructure}, each once.
     *
     * @param  array{enabled?: bool}  $artisanConfig
     * @param  array<string, mixed>  $approvalConfig
     * @return list<class-string>
     */
    public static function all(array $artisanConfig = [], array $approvalConfig = []): array
    {
        return array_values(array_unique([...self::classes($artisanConfig), ...self::infrastructure($approvalConfig)]));
    }

    /**
     * @param  array{enabled?: bool}  $artisanConfig
     * @return list<string>
     */
    public static function signatures(array $artisanConfig = []): array
    {
        return array_column(self::definitions($artisanConfig), 'signature');
    }
}
