<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Schema\CatalogPresenter;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\ProposalService;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;

function bootProposalSqlite(): void
{
    $capsule = new Capsule;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $capsule->setEventDispatcher(new EventDispatcher(new Container));
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    $app = new Container;
    $app->instance('db', $capsule->getDatabaseManager());
    Facade::setFacadeApplication($app);
    Schema::swap($capsule->getConnection()->getSchemaBuilder());
    $files = glob(dirname(__DIR__, 3).'/database/migrations/*.php') ?: [];
    sort($files);
    foreach ($files as $file) {
        (require $file)->up();
    }
}

function seedPendingProposal(): Proposal
{
    $svc = new ConversationService(static fn ($j) => null, new ArrayProgressStore);
    $ids = $svc->createUserMessage('seed');
    $turn = Turn::query()->where('ulid', $ids['turn_ulid'])->firstOrFail();

    return Proposal::query()->create([
        'turn_id' => $turn->id,
        'conversation_id' => $turn->conversation_id,
        'ulid' => strtoupper(bin2hex(random_bytes(13))),
        'type' => 'action',
        'payload' => ['a' => 1],
        'target_capability' => 'demo.cap',
        'status' => Proposal::STATUS_PENDING,
    ]);
}

function proposalRecordingBus(): object
{
    return new class implements CapabilityBus
    {
        public int $invokes = 0;

        public string $lastName = '';

        public array $lastInput = [];

        /** @var array<string, mixed> */
        public array $lastOptions = [];

        public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
        {
            $this->invokes++;
            $this->lastName = $nameOrAlias;
            $this->lastInput = $input;
            $this->lastOptions = $options;

            return CapabilityResult::ok();
        }

        public function catalog(): CatalogPresenter
        {
            throw new RuntimeException('unused');
        }
    };
}

it('accept invokes bus with target_capability and payload and sets accepted', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalRecordingBus();
    $service = new ProposalService($bus);
    $out = $service->accept($proposal->ulid);
    expect($out->status)->toBe(Proposal::STATUS_ACCEPTED)
        ->and($bus->invokes)->toBe(1)
        ->and($bus->lastName)->toBe('demo.cap')
        ->and($bus->lastInput)->toBe(['a' => 1]);
});

it('re-accept is idempotent without second bus invoke', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalRecordingBus();
    $service = new ProposalService($bus);
    $service->accept($proposal->ulid);
    $service->accept($proposal->ulid);
    expect($bus->invokes)->toBe(1);
});

it('accept claims pending→accepting before bus invoke', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $statuses = [];
    $bus = new class($statuses) implements CapabilityBus
    {
        public int $invokes = 0;

        /** @param list<string> $statuses */
        public function __construct(private array &$statuses) {}

        public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
        {
            $this->invokes++;
            $row = Proposal::query()->where('target_capability', $nameOrAlias)->first();
            $this->statuses[] = (string) ($row?->status ?? '');

            return CapabilityResult::ok();
        }

        public function catalog(): CatalogPresenter
        {
            throw new RuntimeException('unused');
        }
    };
    $service = new ProposalService($bus);
    $out = $service->accept($proposal->ulid);
    expect($out->status)->toBe(Proposal::STATUS_ACCEPTED)
        ->and($bus->invokes)->toBe(1)
        ->and($statuses)->toBe([Proposal::STATUS_ACCEPTING]);
});

it('resume from accepting re-invokes once then marks accepted', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $proposal->status = Proposal::STATUS_ACCEPTING;
    $proposal->save();
    $bus = proposalRecordingBus();
    $service = new ProposalService($bus);
    $out = $service->accept($proposal->ulid);
    expect($out->status)->toBe(Proposal::STATUS_ACCEPTED)
        ->and($bus->invokes)->toBe(1)
        ->and($bus->lastOptions['idempotency_key'] ?? null)->toBe('proposal:'.$proposal->ulid);
});

it('reject sets rejected without bus invoke', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalRecordingBus();
    $service = new ProposalService($bus);
    $out = $service->reject($proposal->ulid);
    expect($out->status)->toBe(Proposal::STATUS_REJECTED)
        ->and($bus->invokes)->toBe(0);
});
