<?php

// D-008 tool-surface boundary events reach the host's durable AuditWriter (D-010). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Support\FailingAuditWriter;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryAuditWriter;
use Rawphp\Capabilities\Tests\Fixtures\ProfileHelpers;

function overWarnMcpHarness(): array
{
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [],
        'tool_surface' => [
            'mcp' => [
                'profiles' => ['bulk' => array_map(fn ($i) => 'tool-'.$i, range(0, 32))],
                'require_profile' => true,
                'max_tools_warn' => 32,
                'max_tools_hard' => 64,
            ],
        ],
    ]);
    ProfileHelpers::registerN($h['registry'], 33);

    return $h;
}

it('happy: unfiltered tool request writes tool_surface.unfiltered_refused audit entry [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness([
        'tool_surface' => ['agent' => ['require_profile' => false]],
    ]);

    expect($h['registry']->aiTools(null))->toBeEmpty();

    $entries = $h['fakes']->audit->all();
    expect($entries)->toHaveCount(1)
        ->and($entries[0]['event'])->toBe('tool_surface.unfiltered_refused')
        ->and($entries[0]['payload'])->toBe(['surface' => 'agent'])
        ->and($entries[0]['recorded_at'])->toBe('2026-01-01T00:00:00+00:00')
        ->and($h['registry']->logs())->toHaveCount(1);
});

it('happy: profile above max_tools_warn writes tool_surface.warn_threshold_exceeded audit entry [D-008]', function () {
    $h = overWarnMcpHarness();

    expect($h['registry']->mcpTools('bulk'))->toHaveCount(33);

    $entries = $h['fakes']->audit->all();
    expect($entries)->toHaveCount(1)
        ->and($entries[0]['event'])->toBe('tool_surface.warn_threshold_exceeded')
        ->and($entries[0]['payload'])->toBe(['surface' => 'mcp', 'count' => 33, 'warn' => 32, 'profile' => 'bulk']);
});

it('edge: profile within max_tools_warn writes no audit entry [D-008]', function () {
    $h = ProfileHelpers::multiCapHarness();

    expect($h['registry']->aiTools('billing'))->not->toBeEmpty()
        ->and($h['fakes']->audit->all())->toBe([]);
});

it('edge: tool-surface events follow a writer swapped in after construction [D-008]', function () {
    $h = overWarnMcpHarness();
    $late = new InMemoryAuditWriter(new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')));
    $h['registry']->withAuditWriter($late);

    $h['registry']->mcpTools('bulk');

    expect($late->all())->toHaveCount(1)
        ->and($h['fakes']->audit->all())->toBe([]);
});

it('edge: no audit writer bound keeps observation log only [D-008]', function () {
    $h = overWarnMcpHarness();
    $h['registry']->withAuditWriter(null);

    expect($h['registry']->mcpTools('bulk'))->toHaveCount(33)
        ->and($h['registry']->logs())->toHaveCount(1);
});

it('edge: audit disabled skips tool-surface audit writes [D-008]', function () {
    $h = overWarnMcpHarness();
    $h['registry']->withAuditConfig(['enabled' => false]);

    $h['registry']->mcpTools('bulk');

    expect($h['fakes']->audit->all())->toBe([]);
});

it('fail: audit writer failure never blocks the tool list and logs a warning [D-008] [D-010]', function () {
    $h = overWarnMcpHarness();
    $h['registry']->withAuditWriter(new FailingAuditWriter('disk full'));

    expect($h['registry']->mcpTools('bulk'))->toHaveCount(33);

    $messages = array_column($h['registry']->logs(), 'message');
    expect($messages)->toContain('Audit failed for tool_surface.warn_threshold_exceeded: disk full');
});
