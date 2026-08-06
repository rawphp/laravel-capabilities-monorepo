<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use InvalidArgumentException;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\CapabilitiesAi\Contracts\ConversationContextProvider;
use Rawphp\CapabilitiesAi\Contracts\IdempotencyReadiness;
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Contracts\ToolCatalog;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\ProposalService;
use Rawphp\CapabilitiesAi\Domain\TurnClaim;
use Rawphp\CapabilitiesAi\Domain\TurnRunner;
use Rawphp\CapabilitiesAi\Domain\TurnService;
use Rawphp\CapabilitiesAi\Package;
use RuntimeException;

/**
 * Declarative container binding plan for the AI package.
 *
 * Single match site per driver family: {@see LLM_DRIVERS} / {@see PROGRESS_DRIVERS}
 * are the only maps — resolve() and make* share them. Unknown drivers fail closed.
 * Host-bound LlmClient / ProgressStore must not be overwritten by the SP (bound() guard).
 */
final class ContainerBindings
{
    /**
     * Canonical LLM driver → concrete class map (one edit site to add a driver).
     *
     * @var array<string, class-string<LlmClient>>
     */
    public const LLM_DRIVERS = [
        'fake' => FakeLlmClient::class,
        'anthropic' => AnthropicLlmClient::class,
    ];

    /**
     * Canonical progress driver → concrete class map.
     *
     * @var array<string, class-string<ProgressStore>>
     */
    public const PROGRESS_DRIVERS = [
        'array' => ArrayProgressStore::class,
        'redis' => RedisProgressStore::class,
    ];

    /**
     * Driver resolution only (production uses make* factories; no class-string plan map).
     *
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
        $llm = self::resolveLlmDriver($llmRequested);

        $progressRequested = (string) (($config['progress']['driver'] ?? null) ?: 'array');
        $progress = self::resolveProgressDriver($progressRequested);

        return [
            'drivers' => [
                'llm' => $llm,
                'progress' => $progress,
            ],
        ];
    }

    /**
     * Fail closed on array progress / fake LLM outside testing unless allowUnsafe.
     *
     * Host-prebound LlmClient / ProgressStore skip this (SP only calls before make*).
     * Local demos: CAPABILITIES_AI_ALLOW_UNSAFE=1 (never production happy path).
     *
     * @param  array<string, mixed>  $config  capabilities-ai config slice
     */
    public static function assertSafeDrivers(array $config, bool $isTesting, bool $allowUnsafe): void
    {
        if ($isTesting || $allowUnsafe) {
            return;
        }

        $progress = strtolower((string) (($config['progress']['driver'] ?? null) ?: 'array'));
        $llm = strtolower((string) (($config['llm']['driver'] ?? null) ?: 'fake'));

        if ($progress === 'array') {
            throw new RuntimeException(
                'progress.driver=array is not allowed outside testing; set CAPABILITIES_AI_PROGRESS_DRIVER=redis or CAPABILITIES_AI_ALLOW_UNSAFE=1'
            );
        }

        if ($llm === 'fake') {
            throw new RuntimeException(
                'llm.driver=fake is not allowed outside testing; bind a real LlmClient or set CAPABILITIES_AI_ALLOW_UNSAFE=1'
            );
        }
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
                maxTokens: (int) ($config['llm']['anthropic']['max_tokens'] ?? 64000),
            ),
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
    private static function resolveLlmDriver(string $requested): array
    {
        $resolved = strtolower(trim($requested));
        if ($resolved === '') {
            $resolved = 'fake';
        }

        if (! isset(self::LLM_DRIVERS[$resolved])) {
            throw new InvalidArgumentException(
                "Unknown llm.driver [{$requested}]; expected one of: ".implode(', ', array_keys(self::LLM_DRIVERS))
            );
        }

        return [
            'requested' => $requested,
            'resolved' => $resolved,
            'concrete' => self::LLM_DRIVERS[$resolved],
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

        if (! isset(self::PROGRESS_DRIVERS[$resolved])) {
            throw new InvalidArgumentException(
                "Unknown progress.driver [{$requested}]; expected one of: ".implode(', ', array_keys(self::PROGRESS_DRIVERS))
            );
        }

        return [
            'requested' => $requested,
            'resolved' => $resolved,
            'concrete' => self::PROGRESS_DRIVERS[$resolved],
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
        $userModel = $config['user_model'] ?? null;
        $actors = new ResolveConversationActor(
            is_string($userModel) && $userModel !== '' ? $userModel : null,
        );

        return new TurnRunner(
            claim: $claim,
            llm: $llm,
            context: $context,
            tools: $tools,
            bus: $bus,
            progress: $progress,
            maxToolRounds: $maxRounds > 0 ? $maxRounds : 8,
            actors: $actors,
            proposalsEnabled: (bool) ($config['proposals']['enabled'] ?? true),
        );
    }

    /**
     * @param  callable(object): mixed  $dispatch
     * @param  array<string, mixed>  $config  capabilities-ai config slice (optional proposals.enabled)
     */
    public static function makeConversationService(
        callable $dispatch,
        ProgressStore $progress,
        int $claimTtl = Package::DEFAULT_CLAIM_TTL,
        array $config = [],
    ): ConversationService {
        return new ConversationService(
            $dispatch,
            $progress,
            $claimTtl,
            proposalsEnabled: (bool) ($config['proposals']['enabled'] ?? true),
        );
    }

    public static function makeProposalService(
        CapabilityBus $bus,
        IdempotencyReadiness $idempotency,
        ?string $userModel = null,
    ): ProposalService {
        return new ProposalService(
            $bus,
            $idempotency,
            new ResolveConversationActor(
                is_string($userModel) && $userModel !== '' ? $userModel : null,
            ),
        );
    }

    public static function makeTurnService(ProgressStore $progress): TurnService
    {
        return new TurnService($progress);
    }

    /**
     * Clamp claim_ttl to a positive int; package default when missing/invalid.
     *
     * @param  array<string, mixed>  $config
     */
    public static function claimTtlFromConfig(array $config): int
    {
        $raw = $config['claim_ttl'] ?? Package::DEFAULT_CLAIM_TTL;
        $ttl = is_numeric($raw) ? (int) $raw : Package::DEFAULT_CLAIM_TTL;

        return $ttl > 0 ? $ttl : Package::DEFAULT_CLAIM_TTL;
    }
}
