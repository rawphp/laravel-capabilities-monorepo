<?php

namespace Rawphp\Capabilities\Adapters\Ai;

use Rawphp\Capabilities\Adapters\AdapterApi;
use Rawphp\Capabilities\Adapters\PeerIncompatibleException;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;
use Rawphp\Capabilities\Adapters\StructuredToolResponse;
use Rawphp\Capabilities\Adapters\ToolSelection;
use Rawphp\Capabilities\RateLimiting\AgentTurnBudget;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityResult;
use RuntimeException;

/**
 * AdapterApi V1 implementation for laravel/ai — thin registry bridge (D-011).
 *
 * Produces plain tool definition arrays (peer-shaped) without requiring live laravel/ai.
 */
final class AiToolAdapterV1 implements AiToolAdapter
{
    /** @var list<string> */
    private const SPOOF_KEYS = ['actor', 'user_id', 'caller', 'client_id', 'auth_profile'];

    private int $turnToolCalls = 0;

    private bool $registered = false;

    /** @var list<array<string, mixed>> */
    private array $registeredTools = [];

    public function __construct(
        private readonly CapabilityRegistry $registry,
        private readonly PeerVersionProbe $probe,
        private readonly bool $surfaceEnabled = true,
        private readonly bool $requireCompatiblePeer = true,
        private readonly ?AgentTurnBudget $turnBudget = null,
        private readonly int $adapterApi = AdapterApi::V1,
    ) {}

    public function supportsInstalledPeer(): bool
    {
        return $this->probe->supports(PeerVersionProbe::PEER_AI);
    }

    public function adapterApiVersion(): int
    {
        return $this->adapterApi;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toolsFor(ToolSelection|string|array $selection, ?CapabilityContext $ctx = null): array
    {
        if (! $this->surfaceEnabled) {
            return [];
        }

        $this->assertPeerOrThrow();

        $profile = $selection instanceof ToolSelection ? $selection->profile : $selection;
        $tools = $this->registry->aiTools($profile);

        return array_values(array_map(
            fn (array $tool): array => $this->mapToPeerTool($tool),
            $tools,
        ));
    }

    /**
     * Register tools for the agent surface. Never half-registers on peer failure.
     *
     * @return list<array<string, mixed>>
     */
    public function register(ToolSelection|string|array $selection): array
    {
        if (! $this->surfaceEnabled) {
            $this->registered = false;
            $this->registeredTools = [];

            return [];
        }

        try {
            $tools = $this->toolsFor($selection);
        } catch (PeerIncompatibleException $e) {
            $this->registered = false;
            $this->registeredTools = [];
            throw $e;
        } catch (RuntimeException $e) {
            // Refuse catch-all empty tool lists on unexpected adapter errors (D-011).
            $this->registered = false;
            $this->registeredTools = [];
            throw $e;
        }

        $this->registered = true;
        $this->registeredTools = $tools;

        return $tools;
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

    public function handle(string $name, array $input, object $actor, array $options = []): CapabilityResult
    {
        if (! $this->surfaceEnabled) {
            return CapabilityResult::failure(
                code: 'not_runnable',
                message: 'Agent surface is disabled.',
            );
        }

        $spoof = $this->detectSpoof($input);
        $clean = $this->stripSpoofKeys($input);

        if ($spoof) {
            return CapabilityResult::failure(
                code: 'forbidden',
                message: 'Tool input must not claim actor/caller identity (D-022).',
                extra: ['spoof_keys' => $spoof, 'normalized_code' => 'caller_spoof_attempt'],
            );
        }

        // Caller is always server-derived agent — never from model JSON (D-022).
        unset($options['caller']);
        $this->turnToolCalls++;

        $invokeOptions = array_merge($options, [
            'caller' => 'agent',
            'actor' => $actor,
            'agent_turn_tool_calls' => $this->turnToolCalls,
        ]);

        if (isset($options['messaging']) && is_array($options['messaging'])) {
            $invokeOptions['messaging'] = $options['messaging'];
        }

        if (isset($options['idempotency_key'])) {
            $invokeOptions['idempotency_key'] = $options['idempotency_key'];
        } elseif (isset($clean['idempotency_key'])) {
            $invokeOptions['idempotency_key'] = $clean['idempotency_key'];
            unset($clean['idempotency_key']);
        }

        $profile = $options['profile'] ?? null;
        if ($profile !== null) {
            return $this->registry->runCapabilityInProfile(
                'agent',
                $profile,
                $name,
                $clean,
                $invokeOptions,
            );
        }

        return $this->registry->invoke($name, $clean, $invokeOptions);
    }

    public function handleStructured(string $name, array $input, object $actor, array $options = []): array
    {
        $result = $this->handle($name, $input, $actor, $options);
        $normalize = null;
        if (($result->error['normalized_code'] ?? null) === 'caller_spoof_attempt') {
            $normalize = 'caller_spoof_attempt';
        }

        return StructuredToolResponse::fromResult($result, $normalize);
    }

    public function turnToolCalls(): int
    {
        return $this->turnToolCalls;
    }

    public function resetTurn(): void
    {
        $this->turnToolCalls = 0;
    }

    public function turnBudget(): AgentTurnBudget
    {
        if ($this->turnBudget !== null) {
            return $this->turnBudget;
        }

        return $this->registry->agentTurnBudget();
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
            'peer' => 'laravel/ai',
        ];
    }

    private function assertPeerOrThrow(): void
    {
        if (! $this->requireCompatiblePeer) {
            return;
        }

        if (! $this->supportsInstalledPeer()) {
            if (! $this->probe->isInstalled(PeerVersionProbe::PEER_AI)) {
                throw PeerIncompatibleException::missing(PeerVersionProbe::PEER_AI, 'agent');
            }
            throw PeerIncompatibleException::incompatible(
                PeerVersionProbe::PEER_AI,
                'agent',
                $this->probe->installedVersion(PeerVersionProbe::PEER_AI),
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
