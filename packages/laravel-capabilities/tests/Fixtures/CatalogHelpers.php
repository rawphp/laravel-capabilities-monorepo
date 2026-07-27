<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Schema\CatalogPresenter;
use Rawphp\Capabilities\Tests\Support\SharedFakes;

/**
 * Shared builders for Catalog / Naming / list-describe unit tests.
 */
final class CatalogHelpers
{
    /**
     * @param  array<string, mixed>  $opts
     * @return array{
     *     registry: CapabilityRegistry,
     *     catalog: CatalogPresenter,
     *     fakes: SharedFakes,
     *     name: string
     * }
     */
    public static function harness(array $opts = []): array
    {
        $fakes = SharedFakes::create();
        $registry = new CapabilityRegistry(
            globallyEnabledSurfaces: array_merge([
                'agent' => true,
                'mcp' => true,
                'http' => true,
                'cli' => true,
                'job' => true,
                'artisan' => true,
                'messaging' => false,
            ], $opts['surfaces'] ?? []),
            authorizer: $fakes->authorizer,
            approvalStore: $fakes->approvals,
            idempotencyStore: $fakes->idempotency,
            auditWriter: $fakes->audit,
            rateLimiter: $fakes->rateLimiter,
            toolSurfaceConfig: $opts['tool_surface'] ?? [],
        );

        if (isset($opts['health_overrides']) && is_array($opts['health_overrides'])) {
            $registry->withSurfaceHealthOverrides($opts['health_overrides']);
        }

        $name = $opts['name'] ?? 'create-invoice';
        $builder = Capability::define($name)
            ->description($opts['description'] ?? 'Create an invoice for a customer.')
            ->surfaces($opts['cap_surfaces'] ?? ['agent', 'mcp', 'http', 'cli', 'job'])
            ->input($opts['input'] ?? CreateInvoiceInput::class)
            ->output($opts['output'] ?? CreateInvoiceResult::class)
            ->idempotent($opts['idempotent'] ?? 'optional')
            ->schemaVersion($opts['schema_version'] ?? '1')
            ->aliases($opts['aliases'] ?? [])
            ->groups($opts['groups'] ?? ['billing'])
            ->tags($opts['tags'] ?? ['finance'])
            ->allowSystemCallers(true)
            ->run($opts['run'] ?? fn ($in) => new CreateInvoiceResult(invoice_id: 42));

        if (array_key_exists('deprecated', $opts)) {
            $builder->deprecated((bool) $opts['deprecated']);
        }
        if (array_key_exists('deprecated_at', $opts)) {
            $builder->deprecatedAt($opts['deprecated_at']);
        }
        if (array_key_exists('successor', $opts)) {
            $builder->successor($opts['successor']);
        }
        if (array_key_exists('sunset_at', $opts)) {
            $builder->sunsetAt($opts['sunset_at']);
        }
        if (array_key_exists('readOnly', $opts)) {
            $builder->readOnly((bool) $opts['readOnly']);
        }
        if (array_key_exists('canDiscover', $opts)) {
            $builder->canDiscover($opts['canDiscover']);
        }

        $builder->register($registry);

        if (! empty($opts['extra_caps']) && is_array($opts['extra_caps'])) {
            foreach ($opts['extra_caps'] as $extra) {
                self::registerNamed($registry, $extra);
            }
        }

        return [
            'registry' => $registry,
            'catalog' => $registry->catalog(),
            'fakes' => $fakes,
            'name' => $name,
        ];
    }

    /**
     * @param  array<string, mixed>  $opts
     */
    public static function registerNamed(CapabilityRegistry $registry, array $opts): void
    {
        $name = (string) ($opts['name'] ?? 'extra-cap');
        $builder = Capability::define($name)
            ->description((string) ($opts['description'] ?? $name))
            ->surfaces($opts['surfaces'] ?? ['agent', 'mcp', 'http', 'cli', 'job'])
            ->input($opts['input'] ?? CreateInvoiceInput::class)
            ->output($opts['output'] ?? CreateInvoiceResult::class)
            ->idempotent($opts['idempotent'] ?? 'optional')
            ->schemaVersion($opts['schema_version'] ?? '1')
            ->aliases($opts['aliases'] ?? [])
            ->groups($opts['groups'] ?? [])
            ->tags($opts['tags'] ?? [])
            ->allowSystemCallers(true)
            ->run($opts['run'] ?? fn ($in) => new CreateInvoiceResult(invoice_id: 1));

        if (isset($opts['deprecated'])) {
            $builder->deprecated((bool) $opts['deprecated']);
        }
        if (isset($opts['deprecated_at'])) {
            $builder->deprecatedAt($opts['deprecated_at']);
        }
        if (isset($opts['successor'])) {
            $builder->successor($opts['successor']);
        }
        if (isset($opts['sunset_at'])) {
            $builder->sunsetAt($opts['sunset_at']);
        }
        if (array_key_exists('canDiscover', $opts)) {
            $builder->canDiscover($opts['canDiscover']);
        }

        $builder->register($registry);
    }

    /**
     * @return array<string, mixed>
     */
    public static function input(): array
    {
        return [
            'customer_id' => 1,
            'amount_cents' => 100,
            'currency' => 'USD',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function options(string $caller = 'http', array $extra = []): array
    {
        return array_merge([
            'caller' => $caller,
            'actor' => PipelineHelpers::userActor(1),
            'request_id' => 'req-catalog-1',
        ], $extra);
    }
}
