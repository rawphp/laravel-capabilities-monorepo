<?php

namespace Rawphp\Capabilities\Profiles;

use Rawphp\Capabilities\Registry\CapabilityDefinition;

/**
 * Resolves D-008 selection kinds: named profile, groups/tags, explicit only.
 *
 * Profiles reduce selection error; they never replace authorize().
 */
final class ProfileSelector
{
    /**
     * @param  array<string, list<string>>  $namedProfiles  profile name => capability names
     * @param  string|array<string, mixed>|list<string>|null  $selection
     * @return array{
     *     kind: string,
     *     allowlist: list<string>|null,
     *     groups: list<string>,
     *     tags: list<string>,
     *     only: list<string>,
     *     unscoped: bool
     * }
     */
    public function resolve(string|array|null $selection, array $namedProfiles = []): array
    {
        if ($selection === null || $selection === '' || $selection === []) {
            return [
                'kind' => 'none',
                'allowlist' => null,
                'groups' => [],
                'tags' => [],
                'only' => [],
                'unscoped' => true,
            ];
        }

        if (is_string($selection)) {
            if (str_starts_with($selection, 'groups:')) {
                $group = substr($selection, strlen('groups:'));

                return [
                    'kind' => 'groups',
                    'allowlist' => null,
                    'groups' => [$group],
                    'tags' => [],
                    'only' => [],
                    'unscoped' => false,
                ];
            }
            if (str_starts_with($selection, 'only:')) {
                $name = substr($selection, strlen('only:'));

                return [
                    'kind' => 'only',
                    'allowlist' => [$name],
                    'groups' => [],
                    'tags' => [],
                    'only' => [$name],
                    'unscoped' => false,
                ];
            }
            if (str_starts_with($selection, 'profile:')) {
                $selection = substr($selection, strlen('profile:'));
            }

            $names = $namedProfiles[$selection] ?? [$selection];

            return [
                'kind' => 'profile',
                'allowlist' => array_values($names),
                'groups' => [],
                'tags' => [],
                'only' => [],
                'unscoped' => false,
            ];
        }

        // Associative selection map
        if ($this->isAssoc($selection)) {
            $groups = array_values((array) ($selection['groups'] ?? []));
            $tags = array_values((array) ($selection['tags'] ?? []));
            $only = array_values((array) ($selection['only'] ?? []));
            $profile = $selection['profile'] ?? null;

            $allowlist = null;
            $kind = 'composite';

            if (is_string($profile) && $profile !== '') {
                $kind = 'profile';
                $allowlist = array_values($namedProfiles[$profile] ?? [$profile]);
            }

            if ($only !== []) {
                $kind = $allowlist === null ? 'only' : 'only+profile_conflict';
                // explicit only wins / intersects profile when both set
                $allowlist = $allowlist === null
                    ? $only
                    : array_values(array_intersect($allowlist, $only));
                if ($allowlist === []) {
                    $allowlist = $only;
                }
            }

            if ($groups !== [] || $tags !== []) {
                if ($allowlist === null && $only === []) {
                    $kind = 'groups';
                }
            }

            return [
                'kind' => $kind,
                'allowlist' => $allowlist,
                'groups' => $groups,
                'tags' => $tags,
                'only' => $only,
                'unscoped' => false,
            ];
        }

        // List of capability names = only
        return [
            'kind' => 'only',
            'allowlist' => array_values(array_map('strval', $selection)),
            'groups' => [],
            'tags' => [],
            'only' => array_values(array_map('strval', $selection)),
            'unscoped' => false,
        ];
    }

    /**
     * Whether a definition matches the resolved selection.
     *
     * @param  array{
     *     kind: string,
     *     allowlist: list<string>|null,
     *     groups: list<string>,
     *     tags: list<string>,
     *     only: list<string>,
     *     unscoped: bool
     * }  $resolved
     */
    public function matches(CapabilityDefinition $definition, array $resolved): bool
    {
        if ($resolved['unscoped']) {
            return false;
        }

        $allowlist = $resolved['allowlist'];
        $groups = $resolved['groups'];
        $tags = $resolved['tags'];

        if ($allowlist !== null) {
            $inAllowlist = in_array($definition->name, $allowlist, true)
                || array_intersect($definition->aliases, $allowlist) !== [];
            if (! $inAllowlist && $groups === [] && $tags === []) {
                return false;
            }
            if ($inAllowlist) {
                return true;
            }
        }

        if ($groups !== [] && array_intersect($definition->groups, $groups) !== []) {
            return true;
        }

        if ($tags !== [] && array_intersect($definition->tags, $tags) !== []) {
            return true;
        }

        // When only groups/tags selection without allowlist match
        if ($allowlist === null && ($groups !== [] || $tags !== [])) {
            return false;
        }

        return false;
    }

    /**
     * @param  array<mixed>  $arr
     */
    private function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
