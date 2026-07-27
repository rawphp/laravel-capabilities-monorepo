<?php

namespace Rawphp\Capabilities\Adapters\Artisan;

use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\InvalidArtisanFlagsException;
use Rawphp\Capabilities\Support\MissingArtisanActorException;
use Rawphp\Capabilities\Support\MissingJobTenantException;
use Rawphp\Capabilities\Support\SystemActor;
use stdClass;

/**
 * In-process Artisan capability:run invoker (D-002 / D-016 / PIPE-008).
 *
 * Ops surface only — {@see isProductCli()} is always false. Product CLI is the
 * Go remote HTTP client (caller=cli). Mutating invokes require exactly one of
 * --acting-as (user id) or --system (SystemActor name); never null principal.
 */
final class ArtisanCapabilityInvoker
{
    public function __construct(
        private readonly CapabilityRegistry $registry,
    ) {}

    public static function isProductCli(): bool
    {
        return false;
    }

    public static function role(): string
    {
        return ArtisanCommandTable::ROLE;
    }

    public static function caller(): string
    {
        return ArtisanCommandTable::CALLER;
    }

    /**
     * Parse Artisan option bag (keys as CLI names: acting-as, system, tenant).
     *
     * @param  array<string, mixed>  $flags
     * @return array{acting_as: int|string|null, system: ?string, tenant: ?string}
     */
    public static function parseFlags(array $flags): array
    {
        $actingRaw = $flags['acting-as'] ?? $flags['acting_as'] ?? null;
        $systemRaw = $flags['system'] ?? null;
        $tenantRaw = $flags['tenant'] ?? null;

        $actingAs = self::normalizeActingAs($actingRaw);
        $system = self::normalizeOptionalString($systemRaw);
        $tenant = self::normalizeOptionalString($tenantRaw);

        if ($actingAs !== null && $system !== null) {
            throw InvalidArtisanFlagsException::bothActorFlags();
        }

        return [
            'acting_as' => $actingAs,
            'system' => $system,
            'tenant' => $tenant,
        ];
    }

    /**
     * @param  array{
     *     name: string,
     *     input?: array<string, mixed>,
     *     acting_as?: int|string|null,
     *     system?: string|null,
     *     tenant?: string|null,
     *     mutating?: bool,
     *     tenancy_required?: bool,
     *     globalSystem?: bool,
     *     user_resolver?: callable|null,
     *     idempotency_key?: string|null
     * }  $opts
     */
    public function run(array $opts): CapabilityResult
    {
        $name = (string) ($opts['name'] ?? '');
        $input = $opts['input'] ?? [];
        $actingAs = $opts['acting_as'] ?? null;
        $system = isset($opts['system']) ? self::normalizeOptionalString($opts['system']) : null;
        $tenant = isset($opts['tenant']) ? self::normalizeOptionalString($opts['tenant']) : null;
        $mutating = (bool) ($opts['mutating'] ?? true);

        if ($actingAs !== null && $actingAs !== '' && $system !== null) {
            throw InvalidArtisanFlagsException::bothActorFlags();
        }

        if ($mutating && ($actingAs === null || $actingAs === '') && $system === null) {
            throw MissingArtisanActorException::missing();
        }

        $actor = $this->resolveActor($actingAs, $system, $opts);
        $definition = $this->registry->has($name) ? $this->registry->get($name) : null;
        $globalSystem = (bool) ($opts['globalSystem'] ?? $definition?->globalSystem ?? false);
        $tenancyRequired = (bool) ($opts['tenancy_required'] ?? false);

        if ($actor instanceof SystemActor) {
            if ($definition !== null && ! $definition->allowsSystemCaller($actor)) {
                return CapabilityResult::failure(
                    code: 'forbidden',
                    message: sprintf('SystemActor "%s" is not allowed for capability "%s".', $actor->name, $name),
                );
            }

            if ($tenant === null && $tenancyRequired && ! $globalSystem) {
                throw MissingJobTenantException::forSystemActor($actor->name);
            }
        }

        // First-class tenant for SystemActor (P2-005) — ops flags, never wire input.
        $jobMeta = array_filter([
            'tenant_id' => $tenant,
            'name' => $name,
            'acting_as' => $actor instanceof SystemActor ? $actor->name : (string) ($actor->id ?? ''),
            'surface' => self::caller(),
        ], static fn ($v) => $v !== null && $v !== '');

        $invokeOptions = [
            'caller' => self::caller(),
            'actor' => $actor,
            'job' => $jobMeta,
            'tenant_id' => $tenant,
            'require_scope' => $tenancyRequired && ! $globalSystem,
            'global_system' => $globalSystem,
            'attributes' => array_filter([
                'tenant_id' => $tenant,
                'global_system' => $globalSystem,
                'tenancy_required' => $tenancyRequired,
                'require_scope' => $tenancyRequired && ! $globalSystem,
                'surface_role' => self::role(),
            ], static fn ($v) => $v !== null),
            'idempotency_key' => $opts['idempotency_key'] ?? null,
        ];

        return $this->registry->invoke($name, $input, $invokeOptions);
    }

    /**
     * @param  array<string, mixed>  $opts
     */
    private function resolveActor(int|string|null $actingAs, ?string $system, array $opts): object
    {
        if ($system !== null) {
            return SystemActor::named($system);
        }

        if ($actingAs === null || $actingAs === '') {
            throw MissingArtisanActorException::missing();
        }

        $resolver = $opts['user_resolver'] ?? null;
        if (is_callable($resolver)) {
            $user = $resolver($actingAs);
            if ($user === null) {
                throw new \RuntimeException(sprintf(
                    'User id "%s" not found for artisan --acting-as (D-002).',
                    (string) $actingAs,
                ));
            }

            return $user;
        }

        $user = new stdClass;
        $user->id = is_numeric($actingAs) ? (int) $actingAs : $actingAs;
        $user->name = 'artisan-user-'.$actingAs;
        if (isset($opts['tenant']) && is_string($opts['tenant']) && $opts['tenant'] !== '') {
            $user->current_tenant_id = $opts['tenant'];
        }

        return $user;
    }

    private static function normalizeActingAs(mixed $raw): int|string|null
    {
        if ($raw === null || $raw === '' || $raw === false) {
            return null;
        }

        if (is_int($raw)) {
            return $raw;
        }

        if (is_string($raw) && is_numeric($raw)) {
            return (int) $raw;
        }

        return is_string($raw) ? $raw : null;
    }

    private static function normalizeOptionalString(mixed $raw): ?string
    {
        if ($raw === null || $raw === '' || $raw === false) {
            return null;
        }

        return (string) $raw;
    }
}
