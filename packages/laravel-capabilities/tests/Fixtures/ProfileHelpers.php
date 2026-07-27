<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Tests\Support\SharedFakes;
use stdClass;

/**
 * Build multi-capability registries for D-008 profile selection tests.
 */
final class ProfileHelpers
{
    /**
     * Default named profiles matching spec config examples.
     *
     * @return array{agent: array<string, mixed>, mcp: array<string, mixed>}
     */
    public static function defaultToolSurfaceConfig(array $overrides = []): array
    {
        $base = [
            'agent' => [
                'profiles' => [
                    'billing' => ['create-invoice', 'void-invoice', 'list-invoices'],
                    'support' => ['list-invoices', 'get-customer'],
                ],
                'require_profile' => true,
                'max_tools_warn' => 32,
                'max_tools_hard' => 64,
                'max_tool_calls_per_turn' => 16,
            ],
            'mcp' => [
                'profiles' => [
                    'billing' => ['create-invoice', 'void-invoice', 'list-invoices'],
                    'support' => ['list-invoices', 'get-customer'],
                ],
                'require_profile' => true,
                'max_tools_warn' => 32,
                'max_tools_hard' => 64,
            ],
        ];

        return array_replace_recursive($base, $overrides);
    }

    /**
     * @param  array<string, mixed>  $opts
     * @return array{
     *     registry: CapabilityRegistry,
     *     fakes: SharedFakes,
     *     runs: array<string, stdClass>
     * }
     */
    public static function multiCapHarness(array $opts = []): array
    {
        $fakes = SharedFakes::create(authorize: (bool) ($opts['authorize'] ?? true));
        $authorizer = $opts['authorizer'] ?? $fakes->authorizer;

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
            authorizer: $authorizer,
            approvalStore: $fakes->approvals,
            idempotencyStore: $fakes->idempotency,
            auditWriter: $fakes->audit,
            rateLimiter: $fakes->rateLimiter,
            toolSurfaceConfig: self::defaultToolSurfaceConfig($opts['tool_surface'] ?? []),
        );

        $caps = $opts['caps'] ?? [
            ['name' => 'create-invoice', 'groups' => ['billing', 'finance'], 'tags' => ['billing', 'finance']],
            ['name' => 'void-invoice', 'groups' => ['billing'], 'tags' => ['billing']],
            ['name' => 'list-invoices', 'groups' => ['billing', 'support'], 'tags' => ['support', 'billing']],
            ['name' => 'get-customer', 'groups' => ['support'], 'tags' => ['support']],
            ['name' => 'delete-account', 'groups' => ['ops'], 'tags' => ['ops']],
        ];

        $runs = [];
        foreach ($caps as $cap) {
            $counter = new stdClass;
            $counter->value = 0;
            $runs[$cap['name']] = $counter;

            $builder = Capability::define($cap['name'])
                ->description($cap['description'] ?? $cap['name'])
                ->surfaces($cap['surfaces'] ?? ['agent', 'mcp', 'http', 'cli', 'job'])
                ->input(CreateInvoiceInput::class)
                ->output(CreateInvoiceResult::class)
                ->groups($cap['groups'] ?? [])
                ->tags($cap['tags'] ?? [])
                ->allowSystemCallers(true)
                ->run(function ($in) use ($counter) {
                    $counter->value++;

                    return new CreateInvoiceResult(invoice_id: 99);
                });

            if (array_key_exists('canDiscover', $cap)) {
                $builder->canDiscover($cap['canDiscover']);
            }

            $builder->register($registry);
        }

        return [
            'registry' => $registry,
            'fakes' => $fakes,
            'runs' => $runs,
        ];
    }

    /**
     * Register N numbered caps for max-tools matrix.
     */
    public static function registerN(CapabilityRegistry $registry, int $n, string $prefix = 'tool-'): void
    {
        for ($i = 0; $i < $n; $i++) {
            Capability::define($prefix.$i)
                ->description('tool '.$i)
                ->surfaces(['agent', 'mcp'])
                ->input(CreateInvoiceInput::class)
                ->output(CreateInvoiceResult::class)
                ->groups(['bulk'])
                ->allowSystemCallers(true)
                ->run(fn () => new CreateInvoiceResult(invoice_id: $i))
                ->register($registry);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function input(): array
    {
        return CatalogHelpers::input();
    }

    /**
     * @return array<string, mixed>
     */
    public static function options(string $caller = 'agent', array $extra = []): array
    {
        return CatalogHelpers::options($caller, $extra);
    }

    public static function denyAuthorizer(): StubAuthorizer
    {
        return StubAuthorizer::deny();
    }
}
