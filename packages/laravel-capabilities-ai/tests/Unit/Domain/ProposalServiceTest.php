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
use Rawphp\CapabilitiesAi\Contracts\IdempotencyReadiness;
use Rawphp\CapabilitiesAi\Domain\AcceptOutcome;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\ProposalService;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\AlwaysReadyIdempotency;
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

function seedPendingProposal(?string $target = 'demo.cap', array $payload = ['a' => 1]): Proposal
{
    $svc = new ConversationService(static fn ($j) => null, new ArrayProgressStore);
    $ids = $svc->createUserMessage('seed');
    $turn = Turn::query()->where('ulid', $ids['turn_ulid'])->firstOrFail();

    return Proposal::query()->create([
        'turn_id' => $turn->id,
        'conversation_id' => $turn->conversation_id,
        'ulid' => strtoupper(bin2hex(random_bytes(13))),
        'type' => 'action',
        'payload' => $payload,
        'target_capability' => $target,
        'status' => Proposal::STATUS_PENDING,
    ]);
}

/**
 * @param  callable(string, array): CapabilityResult|null  $handler
 */
function proposalBus(?callable $handler = null): object
{
    return new class($handler) implements CapabilityBus
    {
        public int $invokes = 0;

        public string $lastName = '';

        public array $lastInput = [];

        /** @var array<string, mixed> */
        public array $lastOptions = [];

        public function __construct(private mixed $handler) {}

        public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
        {
            $this->invokes++;
            $this->lastName = $nameOrAlias;
            $this->lastInput = $input;
            $this->lastOptions = $options;

            if (is_callable($this->handler)) {
                return ($this->handler)($nameOrAlias, $input, $options);
            }

            return CapabilityResult::ok();
        }

        public function catalog(): CatalogPresenter
        {
            throw new RuntimeException('unused');
        }
    };
}

function readiness(bool $ready): IdempotencyReadiness
{
    return new class($ready) implements IdempotencyReadiness
    {
        public function __construct(private bool $ready) {}

        public function isReady(): bool
        {
            return $this->ready;
        }
    };
}

it('accept invokes bus and returns accepted outcome', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus();
    $service = new ProposalService($bus, new AlwaysReadyIdempotency);
    $out = $service->accept($proposal->ulid);
    expect($out)->toBeInstanceOf(AcceptOutcome::class)
        ->and($out->kind)->toBe(AcceptOutcome::KIND_ACCEPTED)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_ACCEPTED)
        ->and($bus->invokes)->toBe(1)
        ->and($bus->lastName)->toBe('demo.cap')
        ->and($bus->lastInput)->toBe(['a' => 1]);
});

it('re-accept is idempotent without second bus invoke', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus();
    $service = new ProposalService($bus, new AlwaysReadyIdempotency);
    $service->accept($proposal->ulid);
    $second = $service->accept($proposal->ulid);
    expect($bus->invokes)->toBe(1)
        ->and($second->kind)->toBe(AcceptOutcome::KIND_ACCEPTED);
});

it('approval_required stays accepting and returns typed outcome', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus(static fn () => CapabilityResult::approvalRequired('apr_1', 'need human'));
    $service = new ProposalService($bus, new AlwaysReadyIdempotency);
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_APPROVAL_REQUIRED)
        ->and($out->approvalId)->toBe('apr_1')
        ->and($out->proposal->status)->toBe(Proposal::STATUS_ACCEPTING)
        ->and($out->httpStatus)->toBe(202);
});

it('retryable stays accepting', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus(static fn () => CapabilityResult::failure(
        'rate_limited',
        'slow down',
        ['retryable' => true, 'http_status' => 429],
    ));
    $service = new ProposalService($bus, new AlwaysReadyIdempotency);
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_RETRYABLE)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_ACCEPTING)
        ->and($out->httpStatus)->toBe(429);
});

it('terminal failed marks proposal failed', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus(static fn () => CapabilityResult::failure('domain_error', 'nope'));
    $service = new ProposalService($bus, new AlwaysReadyIdempotency);
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_FAILED)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_FAILED);
});

it('hard refuse marks proposal failed with refuse outcome', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus(static fn () => CapabilityResult::failure('forbidden', 'no access'));
    $service = new ProposalService($bus, new AlwaysReadyIdempotency);
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_REFUSE)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_FAILED)
        ->and($out->httpStatus)->toBe(403);
});

it('fail-closed when idempotency not ready without invoking bus', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus();
    $service = new ProposalService($bus, readiness(false));
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_FAILED)
        ->and($bus->invokes)->toBe(0)
        ->and($out->httpStatus)->toBe(503);
});

it('readiness is evaluated live at accept time', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus();
    $flip = new class implements IdempotencyReadiness
    {
        public bool $ready = false;

        public function isReady(): bool
        {
            return $this->ready;
        }
    };
    $service = new ProposalService($bus, $flip);
    $blocked = $service->accept($proposal->ulid);
    expect($blocked->kind)->toBe(AcceptOutcome::KIND_FAILED)->and($bus->invokes)->toBe(0);

    $flip->ready = true;
    $ok = $service->accept($proposal->ulid);
    expect($ok->kind)->toBe(AcceptOutcome::KIND_ACCEPTED)->and($bus->invokes)->toBe(1);
});

it('missing target_capability is refuse after claim', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal(target: '');
    $bus = proposalBus();
    $service = new ProposalService($bus, new AlwaysReadyIdempotency);
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_REFUSE)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_FAILED)
        ->and($bus->invokes)->toBe(0);
});

it('reject sets rejected without bus invoke', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus();
    $service = new ProposalService($bus, new AlwaysReadyIdempotency);
    $out = $service->reject($proposal->ulid);
    expect($out->status)->toBe(Proposal::STATUS_REJECTED)
        ->and($bus->invokes)->toBe(0);
});

it('accept passes D-005 idempotency_key proposal:{ulid}', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus();
    $service = new ProposalService($bus, new AlwaysReadyIdempotency);
    $service->accept($proposal->ulid);
    expect($bus->lastOptions)->toHaveKey('idempotency_key')
        ->and($bus->lastOptions['idempotency_key'])->toBe('proposal:'.$proposal->ulid);
});

it('terminal failed sets last_error', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus(static fn () => CapabilityResult::failure('domain_error', 'nope'));
    $service = new ProposalService($bus, new AlwaysReadyIdempotency);
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_FAILED)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_FAILED)
        ->and($out->proposal->last_error)->toContain('domain_error')
        ->and($out->proposal->last_error)->toContain('nope');
});

it('isRetryable without explicit error retryable flag stays accepting', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    // rate_limited defaults retryable via ErrorCodeMap / isRetryable(); do not pass retryable key
    $bus = proposalBus(static fn () => CapabilityResult::failure('rate_limited', 'slow'));
    $service = new ProposalService($bus, new AlwaysReadyIdempotency);
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_RETRYABLE)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_ACCEPTING)
        ->and($out->proposal->last_error)->toBeNull();
});

it('success clears last_error on accepted', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $proposal->status = Proposal::STATUS_ACCEPTING;
    $proposal->last_error = 'stale: leftover';
    $proposal->save();

    $bus = proposalBus();
    $service = new ProposalService($bus, new AlwaysReadyIdempotency);
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_ACCEPTED)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_ACCEPTED)
        ->and($out->proposal->last_error)->toBeNull();
});

it('CAS claim: concurrent second accept after peer accepted is idempotent (no double invoke)', function () {
    bootProposalSqlite();
    $p2 = seedPendingProposal();
    $bus2 = proposalBus();
    $svc = new ProposalService($bus2, new AlwaysReadyIdempotency);
    $first = $svc->accept($p2->ulid);
    expect($first->kind)->toBe(AcceptOutcome::KIND_ACCEPTED)->and($bus2->invokes)->toBe(1);
    $second = $svc->accept($p2->ulid);
    expect($second->kind)->toBe(AcceptOutcome::KIND_ACCEPTED)->and($bus2->invokes)->toBe(1);
});
