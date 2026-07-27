<?php

namespace Rawphp\Capabilities\Facades;

use Illuminate\Support\Facades\Facade;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Registry\CapabilityDefinitionBuilder;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;

/**
 * Laravel facade over the capability registry choke point.
 *
 * @method static CapabilityResult invoke(string $nameOrAlias, array $input = [], array $options = [])
 * @method static CapabilityDefinitionBuilder define(string $name)
 * @method static array aiTools(string|array|null $profile = null)
 * @method static array aiMetaTools(string|array|null $profile = null)
 * @method static array mcpTools(string|array|null $profile = null)
 * @method static array mcpMetaTools(string|array|null $profile = null)
 * @method static ApprovalManager approvals()
 * @method static mixed audit()
 * @method static CapabilityRegistry fake()
 * @method static bool assertParity(string $name, array $options = [])
 * @method static bool assertSchemaSnapshot(string $name, array|string|null $expected = null, ?string $snapshotDirectory = null)

 * @method static bool assertCannotInvokeAcrossTenant(array|string|null $nameOrOpts = null, array $input = [], ?string $foreignTenant = null)
 * @method static bool assertScopeResolvedTo(?string $tenantId)
 * @method static bool assertLastScopeTenant(?string $tenantId)
 * @method static void register(\Rawphp\Capabilities\Registry\CapabilityDefinition $definition)
 * @method static bool has(string $nameOrAlias)
 * @method static \Rawphp\Capabilities\Registry\CapabilityDefinition get(string $nameOrAlias)
 *
 * @see CapabilityRegistry
 */
final class Capability extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'capabilities.registry';
    }

    /**
     * Bind a registry instance for unit tests without a full container.
     */
    public static function swapRegistry(CapabilityRegistry $registry): void
    {
        static::swap($registry);
        static::resolved(static fn () => null);
    }
}
