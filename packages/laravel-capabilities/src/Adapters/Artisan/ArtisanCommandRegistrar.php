<?php

namespace Rawphp\Capabilities\Adapters\Artisan;

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
     * @param  array{enabled?: bool}  $artisanConfig
     * @return list<class-string>
     */
    public static function classes(array $artisanConfig = []): array
    {
        return ArtisanCommandTable::commandClasses($artisanConfig);
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
