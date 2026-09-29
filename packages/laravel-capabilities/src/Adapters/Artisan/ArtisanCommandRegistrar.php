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
     * Command classes to register. The ops invoke surface (`surfaces.artisan.enabled`) owns
     * the table; the approval crash-recovery sweep is approval infrastructure and is added
     * whenever `approval.*` schedules it, even with that surface off (L-108 / L-014).
     *
     * @param  array{enabled?: bool}  $artisanConfig
     * @param  array<string, mixed>|null  $approvalConfig  `config('capabilities.approval')`; null = do not consider
     * @return list<class-string>
     */
    public static function classes(array $artisanConfig = [], ?array $approvalConfig = null): array
    {
        $classes = ArtisanCommandTable::commandClasses($artisanConfig);
        if ($approvalConfig !== null
            && ResumeSchedulePlan::fromConfig($approvalConfig) !== null
            && ! in_array(ResumeApprovalsCommand::class, $classes, true)) {
            $classes[] = ResumeApprovalsCommand::class;
        }

        return $classes;
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
