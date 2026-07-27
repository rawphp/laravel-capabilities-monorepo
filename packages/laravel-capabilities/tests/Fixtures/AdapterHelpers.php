<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Adapters\Ai\AiToolAdapterV1;
use Rawphp\Capabilities\Adapters\Mcp\McpAuthProfileResolver;
use Rawphp\Capabilities\Adapters\Mcp\McpToolAdapterV1;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Support\SharedFakes;
use stdClass;

/**
 * Harness for AI/MCP adapter unit tests (mocks only — no live peers).
 */
final class AdapterHelpers
{
    /**
     * @param  array<string, mixed>  $opts
     * @return array{
     *     registry: CapabilityRegistry,
     *     fakes: SharedFakes,
     *     runs: array<string, stdClass>,
     *     probe: PeerVersionProbe,
     *     ai: AiToolAdapterV1,
     *     mcp: McpToolAdapterV1,
     *     auth: McpAuthProfileResolver,
     *     user: object
     * }
     */
    public static function harness(array $opts = []): array
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
            rateLimiter: $opts['rate_limiter'] ?? $fakes->rateLimiter,
            toolSurfaceConfig: ProfileHelpers::defaultToolSurfaceConfig($opts['tool_surface'] ?? []),
            rateLimitConfig: array_merge([
                'enabled' => true,
                'defaults' => ['per_minute' => 10_000, 'per_capability_per_minute' => 10_000],
                'agent_turn' => ['max_tool_calls' => (int) ($opts['max_tool_calls'] ?? 16)],
            ], $opts['rate_limit'] ?? []),
        );

        $caps = $opts['caps'] ?? [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'allowSystemCallers' => true],
            ['name' => 'void-invoice', 'groups' => ['billing'], 'allowSystemCallers' => true],
            ['name' => 'list-invoices', 'groups' => ['billing', 'support'], 'allowSystemCallers' => true],
            ['name' => 'get-customer', 'groups' => ['support'], 'allowSystemCallers' => true],
            ['name' => 'delete-account', 'groups' => ['ops'], 'allowSystemCallers' => true],
        ];

        $runs = [];
        foreach ($caps as $cap) {
            $counter = new stdClass;
            $counter->value = 0;
            $runs[$cap['name']] = $counter;

            $builder = Capability::define($cap['name'])
                ->description($cap['description'] ?? $cap['name'])
                ->surfaces($cap['surfaces'] ?? ['agent', 'mcp', 'http', 'cli', 'job'])
                ->input($cap['input'] ?? CreateInvoiceInput::class)
                ->output($cap['output'] ?? CreateInvoiceResult::class)
                ->groups($cap['groups'] ?? [])
                ->tags($cap['tags'] ?? [])
                ->allowSystemCallers($cap['allowSystemCallers'] ?? true)
                ->idempotent($cap['idempotent'] ?? 'optional')
                ->run($cap['run'] ?? function ($in) use ($counter) {
                    $counter->value++;

                    return new CreateInvoiceResult(invoice_id: 99);
                });

            if (isset($cap['approvalPolicy'])) {
                $builder->approvalPolicy($cap['approvalPolicy']);
            }
            if (isset($cap['rateLimit'])) {
                $builder->rateLimit($cap['rateLimit']);
            }

            $builder->register($registry);
        }

        $peers = $opts['peers'] ?? [
            PeerVersionProbe::PEER_AI => true,
            PeerVersionProbe::PEER_MCP => true,
        ];
        $probe = $opts['probe'] ?? PeerVersionProbe::fake($peers);

        $authConfig = array_merge([
            'default_profile' => 'user_pat',
            'allow_integration_credentials' => false,
            'integration_actors' => [
                'mcp-billing-service' => 'billing-bot',
            ],
            'audit_client_id' => true,
        ], $opts['mcp_auth'] ?? []);

        $auth = new McpAuthProfileResolver($authConfig);

        $ai = new AiToolAdapterV1(
            registry: $registry,
            probe: $probe,
            surfaceEnabled: (bool) ($opts['agent_enabled'] ?? true),
            requireCompatiblePeer: (bool) ($opts['require_peer'] ?? true),
        );

        $mcp = new McpToolAdapterV1(
            registry: $registry,
            probe: $probe,
            authResolver: $auth,
            surfaceEnabled: (bool) ($opts['mcp_enabled'] ?? true),
            requireCompatiblePeer: (bool) ($opts['require_peer'] ?? true),
        );

        return [
            'registry' => $registry,
            'fakes' => $fakes,
            'runs' => $runs,
            'probe' => $probe,
            'ai' => $ai,
            'mcp' => $mcp,
            'auth' => $auth,
            'user' => PipelineHelpers::userActor(42),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function input(array $extra = []): array
    {
        return array_merge(CatalogHelpers::input(), $extra);
    }

    public static function user(int|string $id = 42): object
    {
        return PipelineHelpers::userActor($id);
    }

    public static function system(string $name = 'billing-bot'): SystemActor
    {
        return SystemActor::named($name);
    }

    public static function denyAuthorizer(): StubAuthorizer
    {
        return StubAuthorizer::deny();
    }
}
