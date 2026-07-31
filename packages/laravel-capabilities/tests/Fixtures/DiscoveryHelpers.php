<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\StubAuthorizer;

/**
 * Shared builders for Discovery/Schema unit tests.
 */
final class DiscoveryHelpers
{
    public static function registry(array $surfaces = [], array $validation = []): CapabilityRegistry
    {
        $defaults = [
            'agent' => true,
            'mcp' => true,
            'http' => true,
            'cli' => true,
            'job' => true,
            'artisan' => true,
            'messaging' => false,
        ];

        // Explicit allow for discovery/invoke probes (production default is deny — REQ-070).
        return new CapabilityRegistry(
            globallyEnabledSurfaces: array_merge($defaults, $surfaces),
            validationConfig: array_merge(['validate_output' => true], $validation),
            authorizer: StubAuthorizer::allow(),
        );
    }

    /**
     * Full fluent definition matching AttributedCreateInvoice metadata.
     */
    public static function fluentCreateInvoice(CapabilityRegistry $registry): CapabilityDefinition
    {
        return Capability::define('create-invoice')
            ->description('Create an invoice for a customer.')
            ->surfaces(['agent', 'mcp', 'http', 'cli'])
            ->input(CreateInvoiceInput::class)
            ->output(CreateInvoiceResult::class)
            ->aliases(['invoice.create'])
            ->groups(['billing'])
            ->tags(['finance'])
            ->readOnly(false)
            ->allowSystemCallers(['billing-worker'])
            ->globalSystem(false)
            ->approvalPolicy('requester_or_role')
            ->approvalTtlHours(24)
            ->rateLimit(['per_minute' => 10])
            ->idempotent('optional')
            ->audit(true)
            ->run(fn (CreateInvoiceInput $in) => new CreateInvoiceResult(invoice_id: 42))
            ->register($registry);
    }

    public static function mutatingWith(
        CapabilityRegistry $registry,
        string $name,
        array $overrides = [],
    ): CapabilityDefinition {
        $builder = Capability::define($name)
            ->description($overrides['description'] ?? 'test')
            ->surfaces($overrides['surfaces'] ?? ['http'])
            ->input($overrides['input'] ?? CreateInvoiceInput::class)
            ->output($overrides['output'] ?? CreateInvoiceResult::class);

        if (isset($overrides['aliases'])) {
            $builder->aliases($overrides['aliases']);
        }
        if (isset($overrides['deprecated'])) {
            $builder->deprecated($overrides['deprecated']);
        }
        if (array_key_exists('successor', $overrides)) {
            $builder->successor($overrides['successor']);
        }
        if (array_key_exists('sunset_at', $overrides)) {
            $builder->sunsetAt($overrides['sunset_at']);
        }
        if (isset($overrides['groups'])) {
            $builder->groups($overrides['groups']);
        }
        if (isset($overrides['tags'])) {
            $builder->tags($overrides['tags']);
        }
        if (isset($overrides['readOnly'])) {
            $builder->readOnly($overrides['readOnly']);
        }
        if (isset($overrides['allowSystemCallers'])) {
            $builder->allowSystemCallers($overrides['allowSystemCallers']);
        }
        if (isset($overrides['globalSystem'])) {
            $builder->globalSystem($overrides['globalSystem']);
        }
        if (array_key_exists('approvalPolicy', $overrides)) {
            $builder->approvalPolicy($overrides['approvalPolicy']);
        }
        if (array_key_exists('approvalTtlHours', $overrides)) {
            $builder->approvalTtlHours($overrides['approvalTtlHours']);
        }
        if (array_key_exists('rateLimit', $overrides)) {
            $builder->rateLimit($overrides['rateLimit']);
        }
        if (array_key_exists('idempotent', $overrides)) {
            $builder->idempotent($overrides['idempotent']);
        }
        if (array_key_exists('audit', $overrides)) {
            $builder->audit($overrides['audit']);
        }
        if (isset($overrides['run'])) {
            $builder->run($overrides['run']);
        } else {
            $builder->run(fn ($in) => new CreateInvoiceResult(invoice_id: 1));
        }

        return $builder->register($registry);
    }
}
