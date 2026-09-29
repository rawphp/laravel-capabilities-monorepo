<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Illuminate\Support\Carbon;
use Rawphp\Capabilities\Observability\InMemoryMetrics;
use Rawphp\CapabilitiesAi\Console\ReapStaleTurnsCommand;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\StaleTurnReaper;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryConversationStore;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryTurnClaim;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function reapCommandStore(bool $reset = false): InMemoryConversationStore
{
    static $store = null;
    if ($reset || $store === null) {
        $store = new InMemoryConversationStore;
    }

    return $store;
}

beforeEach(function () {
    reapCommandStore(reset: true);
});

/**
 * Minimal config container; turn rows live in the in-memory store (no database).
 *
 * @param  array<string, mixed>  $aiConfig
 */
function bootReapCommandApp(array $aiConfig): Container
{
    $app = new Container;
    $app->instance('config', new class(['capabilities-ai' => $aiConfig])
    {
        /** @param  array<string, mixed>  $items */
        public function __construct(private array $items) {}

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->items[$key] ?? $default;
        }
    });

    return $app;
}

function seedReapCommandTurn(Carbon $now, string $status, int $ageSeconds): void
{
    $ids = (new ConversationService(static fn ($j) => null, new ArrayProgressStore, store: reapCommandStore()))
        ->createUserMessage('reap cmd');
    $stamp = $now->copy()->subSeconds($ageSeconds);

    reapCommandStore()->turn($ids['turn_ulid'])->forceFill($status === Turn::STATUS_RUNNING
        ? ['status' => Turn::STATUS_RUNNING, 'claimed_at' => $stamp, 'started_at' => $stamp, 'updated_at' => $stamp]
        : ['created_at' => $stamp, 'updated_at' => $stamp]);
}

/**
 * @return array{0: ReapStaleTurnsCommand, 1: BufferedOutput}
 */
function makeReapCommand(Container $app): array
{
    $command = new ReapStaleTurnsCommand;
    $command->setLaravel($app);
    $buffer = new BufferedOutput;
    $command->setOutput(new OutputStyle(new ArrayInput([]), $buffer));

    return [$command, $buffer];
}

afterEach(function () {
    Carbon::setTestNow();
});

it('emits reaped turn counts per status to the Metrics contract', function () {
    $app = bootReapCommandApp([
        'claim_ttl' => 120,
        'reaper' => ['stale_queued_minutes' => 30, 'stale_running_grace_seconds' => 60],
    ]);
    $now = Carbon::parse('2026-08-07 12:00:00');
    Carbon::setTestNow($now);

    seedReapCommandTurn($now, Turn::STATUS_QUEUED, 31 * 60);
    seedReapCommandTurn($now, Turn::STATUS_QUEUED, 45 * 60);
    seedReapCommandTurn($now, Turn::STATUS_RUNNING, 200);

    $metrics = new InMemoryMetrics;
    [$command, $buffer] = makeReapCommand($app);

    $exit = $command->handle(new StaleTurnReaper(new ArrayProgressStore, new InMemoryTurnClaim(reapCommandStore())), $metrics);

    expect($exit)->toBe(Command::SUCCESS)
        ->and($metrics->get(ReapStaleTurnsCommand::METRIC_REAPED, ['status' => 'queued']))->toBe(2)
        ->and($metrics->get(ReapStaleTurnsCommand::METRIC_REAPED, ['status' => 'running']))->toBe(1)
        ->and(ReapStaleTurnsCommand::METRIC_REAPED)->toBe('capabilities_ai_reaped_turns_total')
        ->and($buffer->fetch())->toContain('reaped queued=2 running=1');
});

it('emits zero-valued series when nothing is stale so dashboards see the run', function () {
    $app = bootReapCommandApp([]);
    Carbon::setTestNow(Carbon::parse('2026-08-07 12:00:00'));

    $metrics = new InMemoryMetrics;
    [$command, $buffer] = makeReapCommand($app);

    $command->handle(new StaleTurnReaper(new ArrayProgressStore, new InMemoryTurnClaim(reapCommandStore())), $metrics);

    expect($metrics->emissions())->toBe([
        ['name' => 'capabilities_ai_reaped_turns_total', 'labels' => ['status' => 'queued'], 'by' => 0],
        ['name' => 'capabilities_ai_reaped_turns_total', 'labels' => ['status' => 'running'], 'by' => 0],
    ])->and($buffer->fetch())->toContain('reaped queued=0 running=0');
});
