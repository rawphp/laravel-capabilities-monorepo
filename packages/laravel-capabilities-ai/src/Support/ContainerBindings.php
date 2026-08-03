<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use InvalidArgumentException;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\CapabilitiesAi\Contracts\ConversationContextProvider;
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Contracts\ToolCatalog;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\ProposalService;
use Rawphp\CapabilitiesAi\Domain\TurnClaim;
use Rawphp\CapabilitiesAi\Domain\TurnRunner;
use Rawphp\CapabilitiesAi\Domain\TurnService;
use RuntimeException;

/**
 * Declarative container binding plan for the AI package (UR-017 / ORI-645).
 *
 * Pure function of capabilities-ai config — unit tests assert without a full Laravel app.
 * Unknown drivers fail closed (throw). Host-bound LlmClient / ProgressStore must not be overwritten
 * by the service provider (bound() guard lives there).
 */
final class ContainerBindings
{
    public const LLM_DRIVERS = ['fake', 'anthropic'];

    public const PROGRESS_DRIVERS = ['array', 'redis'];

    /**
     * @param  array<string, mixed>|null  $config  capabilities-ai config slice
     * @return array{
     *     bindings: array<string, class-string>,
     *     drivers: array{
     *         llm: array{requested: string, resolved: string, concrete: class-string},
     *         progress: array{requested: string, resolved: string, concrete: class-string}
     *     }
     * }
     */
    public static function resolve(?array $config = null): array
    {
        $config = $config ?? [];

        $llmRequested = (string) (($config['llm']['driver'] ?? null) ?: 'fake');
        $llm = self::resolveLlmDriver($llmRequested);

        $progressRequested = (string) (($config['progress']['driver'] ?? null) ?: 'array');
        $progress = self::resolveProgressDriver($progressRequested);

        return [
            'bindings' => [
                LlmClient::class => $llm['concrete'],
                ProgressStore::class => $progress['concrete'],
                TurnClaim::class => TurnClaim::class,
                TurnRunner::class => TurnRunner::class,
                TurnService::class => TurnService::class,
                ConversationService::class => ConversationService::class,
                ProposalService::class => ProposalService::class,
            ],
            'drivers' => [
                'llm' => $llm,
                'progress' => $progress,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $config
     * @return array<string, class-string>
     */
    public static function plan(?array $config = null): array
    {
        return self::resolve($config)['bindings'];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function makeLlmClient(array $config): LlmClient
    {
        $driver = (string) (($config['llm']['driver'] ?? null) ?: 'fake');
        $resolved = self::resolveLlmDriver($driver);

        return match ($resolved['resolved']) {
            'fake' => new FakeLlmClient,
            'anthropic' => new AnthropicLlmClient(
                apiKey: (string) ($config['llm']['anthropic']['api_key'] ?? ''),
                model: (string) ($config['llm']['anthropic']['model'] ?? 'claude-sonnet-4-6'),
                baseUrl: (string) ($config['llm']['anthropic']['base_url'] ?? 'https://api.anthropic.com'),
            ),
            default => throw new InvalidArgumentException("Unknown llm.driver [{$driver}]"),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  object|null  $redis  Redis client with rPush/lRange (ext-redis or predis-like)
     */
    public static function makeProgressStore(array $config, ?object $redis = null): ProgressStore
    {
        $driver = (string) (($config['progress']['driver'] ?? null) ?: 'array');
        $resolved = self::resolveProgressDriver($driver);

        return match ($resolved['resolved']) {
            'array' => new ArrayProgressStore,
            'redis' => self::makeRedisProgressStore($config, $redis),
            default => throw new InvalidArgumentException("Unknown progress.driver [{$driver}]"),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  object|null  $redis
     */
    private static function makeRedisProgressStore(array $config, ?object $redis): RedisProgressStore
    {
        if ($redis === null) {
            throw new RuntimeException(
                'progress.driver=redis requires a Redis client; host must provide connection (no silent array fallback)'
            );
        }

        $prefix = (string) ($config['progress']['redis_key_prefix'] ?? 'capabilities_ai:progress:');

        return new RedisProgressStore($redis, $prefix);
    }

    /**
     * @return array{requested: string, resolved: string, concrete: class-string}
     */
    private static function resolveLlmDriver(string $requested): array
    {
        $resolved = strtolower(trim($requested));
        if ($resolved === '') {
            $resolved = 'fake';
        }

        $concrete = match ($resolved) {
            'fake' => FakeLlmClient::class,
            'anthropic' => AnthropicLlmClient::class,
            default => throw new InvalidArgumentException(
                "Unknown llm.driver [{$requested}]; expected one of: ".implode(', ', self::LLM_DRIVERS)
            ),
        };

        return [
            'requested' => $requested,
            'resolved' => $resolved,
            'concrete' => $concrete,
        ];
    }

    /**
     * @return array{requested: string, resolved: string, concrete: class-string}
     */
    private static function resolveProgressDriver(string $requested): array
    {
        $resolved = strtolower(trim($requested));
        if ($resolved === '') {
            $resolved = 'array';
        }

        $concrete = match ($resolved) {
            'array' => ArrayProgressStore::class,
            'redis' => RedisProgressStore::class,
            default => throw new InvalidArgumentException(
                "Unknown progress.driver [{$requested}]; expected one of: ".implode(', ', self::PROGRESS_DRIVERS)
            ),
        };

        return [
            'requested' => $requested,
            'resolved' => $resolved,
            'concrete' => $concrete,
        ];
    }

    /**
     * Build TurnRunner from already-resolved deps (optional host seams).
     *
     * @param  array<string, mixed>  $config
     */
    public static function makeTurnRunner(
        TurnClaim $claim,
        LlmClient $llm,
        ProgressStore $progress,
        array $config,
        ?ConversationContextProvider $context = null,
        ?ToolCatalog $tools = null,
        ?CapabilityBus $bus = null,
    ): TurnRunner {
        $maxRounds = (int) ($config['max_tool_rounds'] ?? 8);

        return new TurnRunner(
            claim: $claim,
            llm: $llm,
            context: $context,
            tools: $tools,
            bus: $bus,
            progress: $progress,
            maxToolRounds: $maxRounds > 0 ? $maxRounds : 8,
        );
    }

    /**
     * @param  callable(object): mixed  $dispatch
     */
    public static function makeConversationService(callable $dispatch, ProgressStore $progress): ConversationService
    {
        return new ConversationService($dispatch, $progress);
    }

    public static function makeProposalService(CapabilityBus $bus): ProposalService
    {
        return new ProposalService($bus);
    }

    public static function makeTurnService(ProgressStore $progress): TurnService
    {
        return new TurnService($progress);
    }
}
