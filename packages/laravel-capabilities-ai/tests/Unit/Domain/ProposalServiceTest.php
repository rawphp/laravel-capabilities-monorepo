<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
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
use Rawphp\CapabilitiesAi\Support\ResolveConversationActor;

/**
 * Minimal user model for ProposalService principal resolution unit tests.
 */
class ProposalServiceTestUser extends Model
{
    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = [];
}

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
    Schema::create('users', function ($table): void {
        $table->increments('id');
        $table->string('name')->nullable();
    });
}

function proposalActors(): ResolveConversationActor
{
    return new ResolveConversationActor(ProposalServiceTestUser::class);
}

function makeProposalService(CapabilityBus $bus, ?IdempotencyReadiness $idempotency = null): ProposalService
{
    return new ProposalService(
        $bus,
        $idempotency ?? new AlwaysReadyIdempotency,
        proposalActors(),
    );
}

function seedPendingProposal(?string $target = 'demo.cap', array $payload = ['a' => 1], bool $withUser = true): Proposal
{
    $userId = null;
    if ($withUser) {
        $user = ProposalServiceTestUser::query()->create(['name' => 'proposal-user']);
        $userId = (string) $user->id;
    }

    $svc = new ConversationService(static fn ($j) => null, new ArrayProgressStore);
    $ids = $svc->createUserMessage('seed', userId: $userId);
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
    $service = makeProposalService($bus);
    $out = $service->accept($proposal->ulid);
    expect($out)->toBeInstanceOf(AcceptOutcome::class)
        ->and($out->kind)->toBe(AcceptOutcome::KIND_ACCEPTED)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_ACCEPTED)
        ->and($bus->invokes)->toBe(1)
        ->and($bus->lastName)->toBe('demo.cap')
        ->and($bus->lastInput)->toBe(['a' => 1])
        ->and($bus->lastOptions['caller'] ?? null)->toBe(ResolveConversationActor::CALLER_JOB)
        ->and($bus->lastOptions['actor'] ?? null)->toBeInstanceOf(ProposalServiceTestUser::class)
        ->and($bus->lastOptions['idempotency_key'] ?? null)->toBe('proposal:'.$proposal->ulid);
});

it('accept fails closed when conversation has no user_id', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal(withUser: false);
    $bus = proposalBus();
    $service = makeProposalService($bus);
    expect(fn () => $service->accept($proposal->ulid))
        ->toThrow(RuntimeException::class, 'user_id');
    expect($bus->invokes)->toBe(0);
});

it('re-accept is idempotent without second bus invoke', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus();
    $service = makeProposalService($bus);
    $service->accept($proposal->ulid);
    $second = $service->accept($proposal->ulid);
    expect($bus->invokes)->toBe(1)
        ->and($second->kind)->toBe(AcceptOutcome::KIND_ACCEPTED);
});

it('approval_required stays accepting and returns typed outcome', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus(static fn () => CapabilityResult::approvalRequired('apr_1', 'need human'));
    $service = makeProposalService($bus);
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
    $service = makeProposalService($bus);
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_RETRYABLE)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_ACCEPTING)
        ->and($out->httpStatus)->toBe(429);
});

it('terminal failed marks proposal failed', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus(static fn () => CapabilityResult::failure('domain_error', 'nope'));
    $service = makeProposalService($bus);
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_FAILED)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_FAILED);
});

it('hard refuse marks proposal failed with refuse outcome', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus(static fn () => CapabilityResult::failure('forbidden', 'no access'));
    $service = makeProposalService($bus);
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_REFUSE)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_FAILED)
        ->and($out->httpStatus)->toBe(403);
});

it('fail-closed when idempotency not ready without invoking bus', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus();
    $service = makeProposalService($bus, readiness(false));
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
    $service = makeProposalService($bus, $flip);
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
    $service = makeProposalService($bus);
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_REFUSE)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_FAILED)
        ->and($bus->invokes)->toBe(0);
});

it('reject sets rejected without bus invoke', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus();
    $service = makeProposalService($bus);
    $out = $service->reject($proposal->ulid);
    expect($out->status)->toBe(Proposal::STATUS_REJECTED)
        ->and($bus->invokes)->toBe(0);
});

it('reject is idempotent when already rejected', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $service = makeProposalService(proposalBus());
    $service->reject($proposal->ulid);
    $again = $service->reject($proposal->ulid);
    expect($again->status)->toBe(Proposal::STATUS_REJECTED);
});

it('reject refuses accepting accepted failed expired', function (string $status) {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $proposal->status = $status;
    $proposal->save();
    $service = makeProposalService(proposalBus());
    expect(fn () => $service->reject($proposal->ulid))
        ->toThrow(RuntimeException::class, "cannot be rejected (status={$status})");
})->with([
    Proposal::STATUS_ACCEPTING,
    Proposal::STATUS_ACCEPTED,
    Proposal::STATUS_FAILED,
    Proposal::STATUS_EXPIRED,
]);

it('accept passes D-005 idempotency_key proposal:{ulid}', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus();
    $service = makeProposalService($bus);
    $service->accept($proposal->ulid);
    expect($bus->lastOptions)->toHaveKey('idempotency_key')
        ->and($bus->lastOptions['idempotency_key'])->toBe('proposal:'.$proposal->ulid);
});

it('resume from accepting re-drive still passes D-005 proposal:{ulid} key', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $proposal->status = Proposal::STATUS_ACCEPTING;
    $proposal->save();

    $bus = proposalBus();
    $service = makeProposalService($bus);
    $out = $service->accept($proposal->ulid);

    expect($out->kind)->toBe(AcceptOutcome::KIND_ACCEPTED)
        ->and($bus->invokes)->toBe(1)
        ->and($bus->lastOptions)->toHaveKey('idempotency_key')
        ->and($bus->lastOptions['idempotency_key'])->toBe('proposal:'.$proposal->ulid);
});

it('terminal failed sets last_error', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $bus = proposalBus(static fn () => CapabilityResult::failure('domain_error', 'nope'));
    $service = makeProposalService($bus);
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
    $service = makeProposalService($bus);
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
    $service = makeProposalService($bus);
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_ACCEPTED)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_ACCEPTED)
        ->and($out->proposal->last_error)->toBeNull();
});

it('CAS claim: concurrent second accept after peer accepted is idempotent (no double invoke)', function () {
    bootProposalSqlite();
    $p2 = seedPendingProposal();
    $bus2 = proposalBus();
    $svc = makeProposalService($bus2);
    $first = $svc->accept($p2->ulid);
    expect($first->kind)->toBe(AcceptOutcome::KIND_ACCEPTED)->and($bus2->invokes)->toBe(1);
    $second = $svc->accept($p2->ulid);
    expect($second->kind)->toBe(AcceptOutcome::KIND_ACCEPTED)->and($bus2->invokes)->toBe(1);
});

it('accept rejected returns refuse outcome without throw', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $proposal->status = Proposal::STATUS_REJECTED;
    $proposal->save();
    $bus = proposalBus();
    $out = (makeProposalService($bus))->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_REFUSE)
        ->and($out->httpStatus)->toBe(409)
        ->and($bus->invokes)->toBe(0);
});

it('accept expired returns refuse outcome without throw', function () {
    bootProposalSqlite();
    $proposal = seedPendingProposal();
    $proposal->status = Proposal::STATUS_EXPIRED;
    $proposal->save();
    $bus = proposalBus();
    $out = (makeProposalService($bus))->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_REFUSE)
        ->and($out->httpStatus)->toBe(410)
        ->and($bus->invokes)->toBe(0);
});
