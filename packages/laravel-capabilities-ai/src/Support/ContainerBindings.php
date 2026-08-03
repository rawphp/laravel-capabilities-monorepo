<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Closure;
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
 * Single match site per driver family (driver → factory). resolve() reports the
 * same resolution for tests; make* calls the factories.
 */
final class ContainerBindings
{
    public const LLM_DRIVERS = ['fake', 'anthropic'];

    public const PROGRESS_DRIVERS = ['array', 'redis'];

    /**
     * @param  array<string, mixed>|null  $config  capabilities-ai config slice
     * @return array{
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
        $progressRequested = (string) (($config['progress']['driver'] ?? null) ?: 'array');

        return [
            'drivers' => [
                'llm' => self::describeLlmDriver($llmRequested),
                'progress' => self::describeProgressDriver($progressRequested),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function makeLlmClient(array $config): LlmClient
    {
        $driver = (string) (($config['llm']['driver'] ?? null) ?: 'fake');
        $resolved = self::normalizeDriver($driver, self::LLM_DRIVERS, 'llm.driver');

        return match ($resolved) {
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
        $resolved = self::normalizeDriver($driver, self::PROGRESS_DRIVERS, 'progress.driver');

        return match ($resolved) {
            'array' => new ArrayProgressStore,
            'redis' => self::makeRedisProgressStore($config, $redis),
            default => throw new InvalidArgumentException("Unknown progress.driver [{$driver}]"),
        };
    }

    /**
     * @param  array<string, mixed>  $config
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
    private static function describeLlmDriver(string $requested): array
    {
        $resolved = self::normalizeDriver($requested, self::LLM_DRIVERS, 'llm.driver');

        return [
            'requested' => $requested,
            'resolved' => $resolved,
            'concrete' => match ($resolved) {
                'fake' => FakeLlmClient::class,
                'anthropic' => AnthropicLlmClient::class,
                default => throw new InvalidArgumentException("Unknown llm.driver [{$requested}]"),
            },
        ];
    }

    /**
     * @return array{requested: string, resolved: string, concrete: class-string}
     */
    private static function describeProgressDriver(string $requested): array
    {
        $resolved = self::normalizeDriver($requested, self::PROGRESS_DRIVERS, 'progress.driver');

        return [
            'requested' => $requested,
            'resolved' => $resolved,
            'concrete' => match ($resolved) {
                'array' => ArrayProgressStore::class,
                'redis' => RedisProgressStore::class,
                default => throw new InvalidArgumentException("Unknown progress.driver [{$requested}]"),
            },
        ];
    }

    /**
     * Single normalization + allowlist site for driver strings.
     *
     * @param  list<string>  $allowed
     */
    private static function normalizeDriver(string $requested, array $allowed, string $label): string
    {
        $resolved = strtolower(trim($requested));
        if ($resolved === '') {
            $resolved = $allowed[0];
        }

        if (! in_array($resolved, $allowed, true)) {
            throw new InvalidArgumentException(
                "Unknown {$label} [{$requested}]; expected one of: ".implode(', ', $allowed)
            );
        }

        return $resolved;
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
    public static function makeConversationService(
        callable $dispatch,
        ProgressStore $progress,
        int $jobTimeoutSeconds = 120,
    ): ConversationService {
        return new ConversationService($dispatch, $progress, $jobTimeoutSeconds);
    }

    /**
     * @param  ?callable(): bool  $idempotencyStoreReady  Live probe at accept time; null = fail closed
     */
    public static function makeProposalService(
        CapabilityBus $bus,
        ?callable $idempotencyStoreReady = null,
    ): ProposalService {
        $probe = $idempotencyStoreReady === null
            ? null
            : Closure::fromCallable($idempotencyStoreReady);

        return new ProposalService($bus, $probe);
    }

    public static function makeTurnService(ProgressStore $progress): TurnService
    {
        return new TurnService($progress);
    }
}
