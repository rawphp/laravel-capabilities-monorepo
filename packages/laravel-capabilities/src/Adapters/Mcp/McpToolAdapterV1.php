<?php

namespace Rawphp\Capabilities\Adapters\Mcp;

use Rawphp\Capabilities\Adapters\AdapterApi;
use Rawphp\Capabilities\Adapters\PeerIncompatibleException;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;
use Rawphp\Capabilities\Adapters\StructuredToolResponse;
use Rawphp\Capabilities\Adapters\ToolSelection;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;
use RuntimeException;

/**
 * AdapterApi V1 implementation for laravel/mcp — thin registry bridge (D-011 / D-023).
 */
final class McpToolAdapterV1 implements McpToolAdapter
{
    /** @var list<string> */
    private const SPOOF_KEYS = ['actor', 'user_id', 'caller', 'client_id', 'auth_profile', 'tenant_id'];

    private bool $registered = false;

    /** @var list<array<string, mixed>> */
    private array $registeredTools = [];

    private ?string $activeProfile = null;

    public function __construct(
        private readonly CapabilityRegistry $registry,
        private readonly PeerVersionProbe $probe,
        private readonly McpAuthProfileResolver $authResolver,
        private readonly bool $surfaceEnabled = true,
        private readonly bool $requireCompatiblePeer = true,
        private readonly int $adapterApi = AdapterApi::V1,
    ) {}

    public function supportsInstalledPeer(): bool
    {
        return $this->probe->supports(PeerVersionProbe::PEER_MCP);
    }

    public function adapterApiVersion(): int
    {
        return $this->adapterApi;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function register(ToolSelection|string|array $selection): array
    {
        if (! $this->surfaceEnabled) {
            $this->registered = false;
            $this->registeredTools = [];
            $this->activeProfile = null;

            return [];
        }

        $this->assertPeerOrThrow();

        $profile = $selection instanceof ToolSelection ? $selection->profile : $selection;

        try {
            $tools = $this->registry->mcpTools($profile);
        } catch (RuntimeException $e) {
            $this->registered = false;
            $this->registeredTools = [];
            throw $e;
        }

        $mapped = array_values(array_map(
            fn (array $tool): array => $this->mapToPeerTool($tool),
            $tools,
        ));

        $this->registered = true;
        $this->registeredTools = $mapped;
        $this->activeProfile = is_string($profile) ? $profile : null;

        return $mapped;
    }

    /**
     * Progressive disclosure listing still constrained by the registered profile (P2-007).
     *
     * @return list<array<string, mixed>>
     */
    public function listTools(): array
    {
        return $this->registeredTools;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function registeredTools(): array
    {
        return $this->registeredTools;
    }

    public function isRegistered(): bool
    {
        return $this->registered;
    }

    public function activeProfile(): ?string
    {
        return $this->activeProfile;
    }

    public function handle(
        string $name,
        array $input,
        McpCredential $credential,
        array $options = [],
    ): CapabilityResult {
        if (! $this->surfaceEnabled) {
            return CapabilityResult::failure(
                code: 'not_runnable',
                message: 'MCP surface is disabled.',
            );
        }

        $spoof = $this->detectSpoof($input);
        $clean = $this->stripSpoofKeys($input);

        if ($spoof !== []) {
            $code = in_array('actor', $spoof, true) || in_array('user_id', $spoof, true)
                ? 'actor_spoof_attempt'
                : 'forbidden';

            return CapabilityResult::failure(
                code: 'forbidden',
                message: 'Tool input must not claim actor/client identity (D-023).',
                extra: [
                    'spoof_keys' => $spoof,
                    'normalized_code' => $code === 'actor_spoof_attempt' ? 'actor_spoof_attempt' : 'actor_spoof_attempt',
                ],
            );
        }

        try {
            $resolved = $this->authResolver->resolve($credential);
        } catch (McpAuthException $e) {
            $code = str_contains($e->getMessage(), 'disabled')
                ? 'integration_disabled'
                : 'unauthenticated';

            return CapabilityResult::failure(
                code: $code === 'integration_disabled' ? 'forbidden' : 'unauthenticated',
                message: $e->getMessage(),
                extra: ['normalized_code' => $code === 'integration_disabled' ? 'integration_disabled' : 'unauthenticated'],
            );
        }

        // Caller always mcp; actor and mcp meta from credential resolver only (D-023).
        unset($options['caller'], $options['actor']);

        $invokeOptions = array_merge($options, [
            'caller' => 'mcp',
            'actor' => $resolved['actor'],
            'mcp' => $resolved['mcp'],
        ]);

        if (isset($resolved['tenant_id'])) {
            $invokeOptions['tenant_id'] = $resolved['tenant_id'];
        }

        if (isset($options['idempotency_key'])) {
            $invokeOptions['idempotency_key'] = $options['idempotency_key'];
        } elseif (isset($clean['idempotency_key'])) {
            $invokeOptions['idempotency_key'] = $clean['idempotency_key'];
            unset($clean['idempotency_key']);
        }

        $profile = $options['profile'] ?? $this->activeProfile;
        if ($profile !== null) {
            return $this->registry->runCapabilityInProfile(
                'mcp',
                $profile,
                $name,
                $clean,
                $invokeOptions,
            );
        }

        return $this->registry->invoke($name, $clean, $invokeOptions);
    }

    public function handleStructured(
        string $name,
        array $input,
        McpCredential $credential,
        array $options = [],
    ): array {
        $result = $this->handle($name, $input, $credential, $options);
        $normalize = isset($result->error['normalized_code'])
            ? (string) $result->error['normalized_code']
            : null;

        return StructuredToolResponse::fromResult($result, $normalize);
    }

    /**
     * @param  array<string, mixed>  $tool
     * @return array{name: string, description: string, input_schema: array<string, mixed>|null, source: string, adapter_api: int}
     */
    private function mapToPeerTool(array $tool): array
    {
        return [
            'name' => (string) $tool['name'],
            'description' => (string) ($tool['description'] ?? ''),
            'input_schema' => $tool['input_schema'] ?? null,
            'source' => 'registry',
            'adapter_api' => $this->adapterApi,
            'peer' => 'laravel/mcp',
        ];
    }

    private function assertPeerOrThrow(): void
    {
        if (! $this->requireCompatiblePeer) {
            return;
        }

        if (! $this->supportsInstalledPeer()) {
            if (! $this->probe->isInstalled(PeerVersionProbe::PEER_MCP)) {
                throw PeerIncompatibleException::missing(PeerVersionProbe::PEER_MCP, 'mcp');
            }
            throw PeerIncompatibleException::incompatible(
                PeerVersionProbe::PEER_MCP,
                'mcp',
                $this->probe->installedVersion(PeerVersionProbe::PEER_MCP),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    private function detectSpoof(array $input): array
    {
        $found = [];
        foreach (self::SPOOF_KEYS as $key) {
            if (array_key_exists($key, $input)) {
                $found[] = $key;
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function stripSpoofKeys(array $input): array
    {
        foreach (self::SPOOF_KEYS as $key) {
            unset($input[$key]);
        }

        return $input;
    }
}
