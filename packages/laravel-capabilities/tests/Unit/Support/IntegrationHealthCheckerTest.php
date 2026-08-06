<?php

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\Authorizer;
use Rawphp\Capabilities\Support\IntegrationHealthChecker;
use Rawphp\Capabilities\Support\IntegrationHealthReport;

/**
 * @param  list<string>  $bound
 * @return callable(class-string): bool
 */
function ihBound(array $bound = []): callable
{
    $set = array_fill_keys($bound, true);

    return static fn (string $abstract): bool => isset($set[$abstract]);
}

/**
 * @param  array<string, mixed>  $surfaces
 * @return array<string, mixed>
 */
function ihCapabilities(array $surfaces = []): array
{
    $defaults = [
        'agent' => ['enabled' => true],
        'mcp' => ['enabled' => true, 'profiles' => [], 'servers' => []],
        'http' => ['enabled' => true],
        'cli' => ['enabled' => true],
        'job' => ['enabled' => true],
        'artisan' => ['enabled' => true],
    ];

    return [
        'surfaces' => array_replace_recursive($defaults, $surfaces),
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ihAi(array $overrides = []): array
{
    return array_replace_recursive([
        'routes' => ['enabled' => false],
        'queue' => ['name' => null, 'connection' => null],
        'claim_ttl' => 120,
        'progress' => ['driver' => 'redis'],
        'proposals' => ['enabled' => true],
    ], $overrides);
}

/**
 * @return list<string>
 */
function ihCodes(IntegrationHealthReport $report): array
{
    return array_map(static fn (array $c): string => $c['code'], $report->checks);
}

/**
 * @return array{level: string, code: string, message: string}|null
 */
function ihFind(IntegrationHealthReport $report, string $code): ?array
{
    foreach ($report->checks as $check) {
        if ($check['code'] === $code) {
            return $check;
        }
    }

    return null;
}

function ihLevel(IntegrationHealthReport $report, string $code): ?string
{
    $check = ihFind($report, $code);

    return is_array($check) ? (string) $check['level'] : null;
}

function ihMessage(IntegrationHealthReport $report, string $code): ?string
{
    $check = ihFind($report, $code);

    return is_array($check) ? (string) $check['message'] : null;
}

it('bus-only when AI config null skips AI checks', function () {
    $report = (new IntegrationHealthChecker)->check(
        ihCapabilities(),
        null,
        ihBound([Authorizer::class]),
    );

    expect($report->mode)->toBe('bus-only')
        ->and(ihCodes($report))->not->toContain('ai_context_bound')
        ->and(ihCodes($report))->not->toContain('ai_tool_catalog_bound')
        ->and(ihCodes($report))->not->toContain('ai_claim_ttl')
        ->and($report->failed())->toBeFalse()
        ->and($report->exitCode())->toBe(0);
});

it('bus-only when routes off and queue empty skips AI checks', function () {
    $report = (new IntegrationHealthChecker)->check(
        ihCapabilities(),
        ihAi(['routes' => ['enabled' => false], 'queue' => ['name' => null]]),
        ihBound([Authorizer::class]),
    );

    expect($report->mode)->toBe('bus-only')
        ->and(ihCodes($report))->not->toContain('ai_context_bound')
        ->and($report->failed())->toBeFalse();
});

it('AI-chat via queue.name requires Context and ToolCatalog bound', function () {
    $report = (new IntegrationHealthChecker)->check(
        ihCapabilities(),
        ihAi(['queue' => ['name' => 'capabilities-ai']]),
        ihBound([Authorizer::class]),
    );

    expect($report->mode)->toBe('ai-chat')
        ->and(ihLevel($report, 'ai_context_bound'))->toBe('fail')
        ->and(ihLevel($report, 'ai_tool_catalog_bound'))->toBe('fail')
        ->and($report->failed())->toBeTrue()
        ->and($report->exitCode())->toBe(1);

    $ok = (new IntegrationHealthChecker)->check(
        ihCapabilities(),
        ihAi(['queue' => ['name' => 'capabilities-ai']]),
        ihBound([
            Authorizer::class,
            'Rawphp\\CapabilitiesAi\\Contracts\\ConversationContextProvider',
            'Rawphp\\CapabilitiesAi\\Contracts\\ToolCatalog',
        ]),
    );

    expect($ok->mode)->toBe('ai-chat')
        ->and(ihLevel($ok, 'ai_context_bound'))->toBe('ok')
        ->and(ihLevel($ok, 'ai_tool_catalog_bound'))->toBe('ok')
        ->and(ihLevel($ok, 'ai_claim_ttl'))->toBe('ok');
});

it('AI-chat via routes only with empty queue fails ai_queue_name_empty', function () {
    $report = (new IntegrationHealthChecker)->check(
        ihCapabilities(),
        ihAi([
            'routes' => ['enabled' => true],
            'queue' => ['name' => null],
        ]),
        ihBound([
            Authorizer::class,
            'Rawphp\\CapabilitiesAi\\Contracts\\ConversationContextProvider',
            'Rawphp\\CapabilitiesAi\\Contracts\\ToolCatalog',
        ]),
    );

    expect($report->mode)->toBe('ai-chat')
        ->and(ihLevel($report, 'ai_queue_name_empty'))->toBe('fail')
        ->and($report->failed())->toBeTrue()
        ->and($report->exitCode())->toBe(1);
});

it('AlwaysReady fails only when proposals enabled; skipped when proposals off', function () {
    $alwaysReady = static fn (): ?string => 'Rawphp\\CapabilitiesAi\\Support\\AlwaysReadyIdempotency';

    $fail = (new IntegrationHealthChecker)->check(
        ihCapabilities(),
        ihAi([
            'queue' => ['name' => 'capabilities-ai'],
            'proposals' => ['enabled' => true],
        ]),
        ihBound([
            Authorizer::class,
            'Rawphp\\CapabilitiesAi\\Contracts\\ConversationContextProvider',
            'Rawphp\\CapabilitiesAi\\Contracts\\ToolCatalog',
        ]),
        null,
        $alwaysReady,
    );

    expect(ihLevel($fail, 'ai_always_ready'))->toBe('fail')
        ->and($fail->failed())->toBeTrue();

    $skip = (new IntegrationHealthChecker)->check(
        ihCapabilities(),
        ihAi([
            'queue' => ['name' => 'capabilities-ai'],
            'proposals' => ['enabled' => false],
        ]),
        ihBound([
            Authorizer::class,
            'Rawphp\\CapabilitiesAi\\Contracts\\ConversationContextProvider',
            'Rawphp\\CapabilitiesAi\\Contracts\\ToolCatalog',
        ]),
        null,
        $alwaysReady,
    );

    expect(ihLevel($skip, 'ai_always_ready'))->toBe('skip')
        ->and($skip->failed())->toBeFalse();
});

it('authorizer skipped when all invoke surfaces disabled', function () {
    $report = (new IntegrationHealthChecker)->check(
        ihCapabilities([
            'agent' => ['enabled' => false],
            'mcp' => ['enabled' => false],
            'http' => ['enabled' => false],
            'cli' => ['enabled' => false],
            'job' => ['enabled' => false],
        ]),
        null,
        ihBound([]),
    );

    expect(ihLevel($report, 'authorizer_bound'))->toBe('skip')
        ->and($report->failed())->toBeFalse();
});

it('authorizer fails when unbound and an invoke surface is enabled', function () {
    $report = (new IntegrationHealthChecker)->check(
        ihCapabilities(['http' => ['enabled' => true]]),
        null,
        ihBound([]),
    );

    expect(ihLevel($report, 'authorizer_bound'))->toBe('fail')
        ->and($report->failed())->toBeTrue();
});

it('MCP non-empty plan with zero tools fails mcp_tools', function () {
    $report = (new IntegrationHealthChecker)->check(
        ihCapabilities([
            'mcp' => [
                'enabled' => true,
                'profiles' => ['lab' => ['create-invoice']],
            ],
        ]),
        null,
        ihBound([Authorizer::class]),
        static fn (): int => 0,
    );

    expect(ihLevel($report, 'mcp_tools'))->toBe('fail')
        ->and($report->failed())->toBeTrue();

    $ok = (new IntegrationHealthChecker)->check(
        ihCapabilities([
            'mcp' => [
                'enabled' => true,
                'profiles' => ['lab' => ['create-invoice']],
            ],
        ]),
        null,
        ihBound([Authorizer::class]),
        static fn (): int => 2,
    );

    expect(ihLevel($ok, 'mcp_tools'))->toBe('ok')
        ->and($ok->failed())->toBeFalse();
});

it('MCP empty plan skips mcp_tools', function () {
    $report = (new IntegrationHealthChecker)->check(
        ihCapabilities([
            'mcp' => ['enabled' => true, 'profiles' => [], 'servers' => []],
        ]),
        null,
        ihBound([Authorizer::class]),
        static fn (): int => 0,
    );

    expect(ihLevel($report, 'mcp_tools'))->toBe('skip');
});

it('AI-chat fails on array progress driver', function () {
    $report = (new IntegrationHealthChecker)->check(
        ihCapabilities(),
        ihAi([
            'queue' => ['name' => 'capabilities-ai'],
            'progress' => ['driver' => 'array'],
        ]),
        ihBound([
            Authorizer::class,
            'Rawphp\\CapabilitiesAi\\Contracts\\ConversationContextProvider',
            'Rawphp\\CapabilitiesAi\\Contracts\\ToolCatalog',
        ]),
    );

    expect(ihLevel($report, 'ai_progress_array'))->toBe('fail')
        ->and($report->failed())->toBeTrue();
});

it('AI-chat fails when claim_ttl is not positive', function () {
    $report = (new IntegrationHealthChecker)->check(
        ihCapabilities(),
        ihAi([
            'queue' => ['name' => 'capabilities-ai'],
            'claim_ttl' => 0,
        ]),
        ihBound([
            Authorizer::class,
            'Rawphp\\CapabilitiesAi\\Contracts\\ConversationContextProvider',
            'Rawphp\\CapabilitiesAi\\Contracts\\ToolCatalog',
        ]),
    );

    expect(ihLevel($report, 'ai_claim_ttl'))->toBe('fail')
        ->and($report->failed())->toBeTrue();
});

it('mcp_register fails when tool count callback throws', function () {
    $report = (new IntegrationHealthChecker)->check(
        ihCapabilities([
            'mcp' => [
                'enabled' => true,
                'profiles' => ['lab' => ['create-invoice']],
            ],
        ]),
        null,
        ihBound([Authorizer::class]),
        static function (): int {
            throw new RuntimeException('peer down');
        },
    );

    expect(ihLevel($report, 'mcp_register'))->toBe('fail')
        ->and(ihMessage($report, 'mcp_register'))->toContain('peer down')
        ->and($report->failed())->toBeTrue();
});

it('report failed is true only for fail levels', function () {
    $report = new IntegrationHealthReport('bus-only', [
        ['level' => 'warn', 'code' => 'w', 'message' => 'w'],
        ['level' => 'ok', 'code' => 'o', 'message' => 'o'],
        ['level' => 'skip', 'code' => 's', 'message' => 's'],
    ]);

    expect($report->failed())->toBeFalse()
        ->and($report->exitCode())->toBe(0);

    $fail = new IntegrationHealthReport('bus-only', [
        ['level' => 'fail', 'code' => 'f', 'message' => 'f'],
    ]);

    expect($fail->failed())->toBeTrue()
        ->and($fail->exitCode())->toBe(1);
});
