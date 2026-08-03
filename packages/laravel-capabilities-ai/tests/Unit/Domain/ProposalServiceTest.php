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

        public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
        {
            $this->invokes++;
            $this->lastName = $nameOrAlias;
            $this->lastInput = $input;

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

it('reject sets rejected without bus invoke', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalRecordingBus();
    $service = new ProposalService($bus);
    $out = $service->reject($proposal->ulid);
    expect($out->status)->toBe(Proposal::STATUS_REJECTED)
        ->and($bus->invokes)->toBe(0);
});
