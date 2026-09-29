<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Schema\CatalogPresenter;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesAi\Contracts\IdempotencyReadiness;
use Rawphp\CapabilitiesAi\Contracts\ToolCatalog;
use Rawphp\CapabilitiesAi\Domain\AcceptOutcome;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\ProposalService;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Support\AlwaysReadyIdempotency;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\ResolveConversationActor;
use Rawphp\CapabilitiesAi\Support\ToolSchemaHash;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryActorLookup;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryConversationStore;

/**
 * Minimal user model for ProposalService principal resolution unit tests (never persisted).
 */
class ProposalServiceTestUser extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}

/**
 * Per-test in-memory rows and host users (no database).
 *
 * @return object{store: InMemoryConversationStore, users: InMemoryActorLookup}
 */
function proposalWorld(bool $reset = false): object
{
    static $world = null;
    if ($reset || $world === null) {
        $world = (object) ['store' => new InMemoryConversationStore, 'users' => new InMemoryActorLookup];
    }

    return $world;
}

function proposalStore(): InMemoryConversationStore
{
    return proposalWorld()->store;
}

beforeEach(function () {
    proposalWorld(reset: true);
});

function proposalActors(): ResolveConversationActor
{
    return new ResolveConversationActor(lookup: proposalWorld()->users);
}

/**
 * Host tool profile fake; names are mutable so a test can narrow the profile after propose-time.
 *
 * @param  list<string>  $names
 */
function proposalTools(array $names = ['demo.cap']): object
{
    return new class($names) implements ToolCatalog
    {
        /** @var list<array{0: string, 1: string}> */
        public array $calls = [];

        /** @param  list<string>  $names */
        public function __construct(public array $names) {}

        public function toolsForTurn(string $conversationUlid, string $turnUlid): array
        {
            $this->calls[] = [$conversationUlid, $turnUlid];

            return array_map(static fn (string $name): array => ['name' => $name], $this->names);
        }
    };
}

function makeProposalService(
    CapabilityBus $bus,
    ?IdempotencyReadiness $idempotency = null,
    ?ToolCatalog $tools = null,
): ProposalService {
    return new ProposalService(
        $bus,
        $idempotency ?? new AlwaysReadyIdempotency,
        proposalActors(),
        $tools ?? proposalTools(),
        proposalStore(),
    );
}

function seedPendingProposal(?string $target = 'demo.cap', array $payload = ['a' => 1], bool $withUser = true, ?string $schemaHash = null): Proposal
{
    $userId = null;
    if ($withUser) {
        $user = proposalWorld()->users->add(new ProposalServiceTestUser(['name' => 'proposal-user']));
        $userId = (string) $user->id;
    }

    $svc = new ConversationService(static fn ($j) => null, new ArrayProgressStore, store: proposalStore());
    $ids = $svc->createUserMessage('seed', userId: $userId);

    return proposalStore()->createProposal(
        proposalStore()->turn($ids['turn_ulid']),
        strtoupper(bin2hex(random_bytes(13))),
        'action',
        $payload,
        $target,
        $schemaHash,
    );
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

it('accept refuses a target narrowed out of the tool profile after propose-time without invoking the bus', function () {
    $proposal = seedPendingProposal();
    $tools = proposalTools(['demo.cap', 'demo.other']);
    $tools->names = ['demo.other'];
    $bus = proposalBus();
    $out = makeProposalService($bus, tools: $tools)->accept($proposal->ulid);
    $turn = proposalStore()->proposal($proposal->ulid)->turn;
    $conversation = proposalStore()->proposal($proposal->ulid)->conversation;

    expect($out->kind)->toBe(AcceptOutcome::KIND_REFUSE)
        ->and($out->httpStatus)->toBe(403)
        ->and($out->error['code'] ?? null)->toBe('capability_not_in_profile')
        ->and($out->proposal->status)->toBe(Proposal::STATUS_FAILED)
        ->and($out->proposal->last_error)->toStartWith('capability_not_in_profile:')
        ->and($bus->invokes)->toBe(0)
        ->and($tools->calls)->toBe([[$conversation->ulid, $turn->ulid]]);
});

it('accept refuses a proposal whose target input schema changed since creation without invoking the bus', function () {
    $proposal = seedPendingProposal(schemaHash: ToolSchemaHash::of(['parameters' => ['type' => 'object']]));
    $bus = proposalBus();
    $out = makeProposalService($bus)->accept($proposal->ulid);

    expect($out->kind)->toBe(AcceptOutcome::KIND_REFUSE)
        ->and($out->httpStatus)->toBe(409)
        ->and($out->error['code'] ?? null)->toBe('conflict')
        ->and($out->error['reason'] ?? null)->toBe('schema_changed')
        ->and($out->error['retryable'] ?? null)->toBeFalse()
        ->and($out->message)->toContain('input schema changed')
        ->and($out->proposal->status)->toBe(Proposal::STATUS_FAILED)
        ->and($out->proposal->last_error)->toStartWith('conflict:')
        ->and($bus->invokes)->toBe(0);
});

it('accept invokes when the stamped schema hash still matches the live tool schema', function () {
    $proposal = seedPendingProposal(schemaHash: ToolSchemaHash::of(['name' => 'demo.cap']));
    $bus = proposalBus();
    $out = makeProposalService($bus)->accept($proposal->ulid);

    expect($out->kind)->toBe(AcceptOutcome::KIND_ACCEPTED)
        ->and($bus->invokes)->toBe(1);
});

it('accept fails closed with not_in_profile when no ToolCatalog is bound', function () {
    $proposal = seedPendingProposal();
    $bus = proposalBus();
    $service = new ProposalService($bus, new AlwaysReadyIdempotency, proposalActors(), store: proposalStore());
    $out = $service->accept($proposal->ulid);

    expect($out->kind)->toBe(AcceptOutcome::KIND_REFUSE)
        ->and($out->error['code'] ?? null)->toBe('capability_not_in_profile')
        ->and($bus->invokes)->toBe(0);
});

it('accept fails closed with not_in_profile when the proposal turn row is gone', function () {
    $proposal = seedPendingProposal();
    $proposal->turn_id = 999999;
    $tools = proposalTools();
    $bus = proposalBus();
    $out = makeProposalService($bus, tools: $tools)->accept($proposal->ulid);

    expect($out->kind)->toBe(AcceptOutcome::KIND_REFUSE)
        ->and($out->error['code'] ?? null)->toBe('capability_not_in_profile')
        ->and($tools->calls)->toBe([])
        ->and($bus->invokes)->toBe(0);
});

it('accept fails closed when conversation has no user_id', function () {
    $proposal = seedPendingProposal(withUser: false);
    $bus = proposalBus();
    $service = makeProposalService($bus);
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_REFUSE)
        ->and($out->httpStatus)->toBe(403)
        ->and($out->error['code'] ?? null)->toBe('forbidden')
        ->and($out->message)->toContain('user_id')
        ->and($out->proposal->status)->toBe(Proposal::STATUS_FAILED)
        ->and($bus->invokes)->toBe(0);
});

it('accept marks failed (not stuck accepting) when conversation user was deleted after proposal', function () {
    $proposal = seedPendingProposal();
    proposalWorld()->users->users = [];
    $bus = proposalBus();
    $service = makeProposalService($bus);

    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_REFUSE)
        ->and($out->httpStatus)->toBe(403)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_FAILED)
        ->and($out->proposal->last_error)->toContain('does not resolve to a user')
        ->and($bus->invokes)->toBe(0);

    $again = $service->accept($proposal->ulid);
    expect($again->kind)->toBe(AcceptOutcome::KIND_FAILED)
        ->and($bus->invokes)->toBe(0);
});

it('actor resolver misconfiguration leaves proposal accepting for re-drive after config fix', function () {
    $proposal = seedPendingProposal();
    $bus = proposalBus();
    $service = new ProposalService($bus, new AlwaysReadyIdempotency, new ResolveConversationActor('NoSuchUserModel'), proposalTools(), proposalStore());

    expect(fn () => $service->accept($proposal->ulid))
        ->toThrow(RuntimeException::class, 'does not exist');
    expect(proposalStore()->proposal($proposal->ulid)->status)->toBe(Proposal::STATUS_ACCEPTING)
        ->and($bus->invokes)->toBe(0);

    $fixed = makeProposalService($bus)->accept($proposal->ulid);
    expect($fixed->kind)->toBe(AcceptOutcome::KIND_ACCEPTED)
        ->and($bus->invokes)->toBe(1);
});

it('re-accept is idempotent without second bus invoke', function () {
    $proposal = seedPendingProposal();
    $bus = proposalBus();
    $service = makeProposalService($bus);
    $service->accept($proposal->ulid);
    $second = $service->accept($proposal->ulid);
    expect($bus->invokes)->toBe(1)
        ->and($second->kind)->toBe(AcceptOutcome::KIND_ACCEPTED);
});

it('approval_required stays accepting and returns typed outcome', function () {
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
    $proposal = seedPendingProposal();
    $bus = proposalBus(static fn () => CapabilityResult::failure('domain_error', 'nope'));
    $service = makeProposalService($bus);
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_FAILED)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_FAILED);
});

it('hard refuse marks proposal failed with refuse outcome', function () {
    $proposal = seedPendingProposal();
    $bus = proposalBus(static fn () => CapabilityResult::failure('forbidden', 'no access'));
    $service = makeProposalService($bus);
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_REFUSE)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_FAILED)
        ->and($out->httpStatus)->toBe(403);
});

it('fail-closed when idempotency not ready without invoking bus', function () {
    $proposal = seedPendingProposal();
    $bus = proposalBus();
    $service = makeProposalService($bus, readiness(false));
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_FAILED)
        ->and($bus->invokes)->toBe(0)
        ->and($out->httpStatus)->toBe(503);
});

it('readiness is evaluated live at accept time', function () {
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
    $proposal = seedPendingProposal(target: '');
    $bus = proposalBus();
    $service = makeProposalService($bus);
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_REFUSE)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_FAILED)
        ->and($bus->invokes)->toBe(0);
});

it('reject sets rejected without bus invoke', function () {
    $proposal = seedPendingProposal();
    $bus = proposalBus();
    $service = makeProposalService($bus);
    $out = $service->reject($proposal->ulid);
    expect($out->status)->toBe(Proposal::STATUS_REJECTED)
        ->and($bus->invokes)->toBe(0);
});

it('reject is idempotent when already rejected', function () {
    $proposal = seedPendingProposal();
    $service = makeProposalService(proposalBus());
    $service->reject($proposal->ulid);
    $again = $service->reject($proposal->ulid);
    expect($again->status)->toBe(Proposal::STATUS_REJECTED);
});

it('reject refuses accepting accepted failed expired', function (string $status) {
    $proposal = seedPendingProposal();
    $proposal->status = $status;
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
    $proposal = seedPendingProposal();
    $bus = proposalBus();
    $service = makeProposalService($bus);
    $service->accept($proposal->ulid);
    expect($bus->lastOptions)->toHaveKey('idempotency_key')
        ->and($bus->lastOptions['idempotency_key'])->toBe('proposal:'.$proposal->ulid);
});

it('resume from accepting re-drive still passes D-005 proposal:{ulid} key', function () {
    $proposal = seedPendingProposal();
    $proposal->status = Proposal::STATUS_ACCEPTING;

    $bus = proposalBus();
    $service = makeProposalService($bus);
    $out = $service->accept($proposal->ulid);

    expect($out->kind)->toBe(AcceptOutcome::KIND_ACCEPTED)
        ->and($bus->invokes)->toBe(1)
        ->and($bus->lastOptions)->toHaveKey('idempotency_key')
        ->and($bus->lastOptions['idempotency_key'])->toBe('proposal:'.$proposal->ulid);
});

it('terminal failed sets last_error', function () {
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
    $proposal = seedPendingProposal();
    $proposal->status = Proposal::STATUS_ACCEPTING;
    $proposal->last_error = 'stale: leftover';

    $bus = proposalBus();
    $service = makeProposalService($bus);
    $out = $service->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_ACCEPTED)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_ACCEPTED)
        ->and($out->proposal->last_error)->toBeNull();
});

it('CAS claim: concurrent second accept after peer accepted is idempotent (no double invoke)', function () {
    $p2 = seedPendingProposal();
    $bus2 = proposalBus();
    $svc = makeProposalService($bus2);
    $first = $svc->accept($p2->ulid);
    expect($first->kind)->toBe(AcceptOutcome::KIND_ACCEPTED)->and($bus2->invokes)->toBe(1);
    $second = $svc->accept($p2->ulid);
    expect($second->kind)->toBe(AcceptOutcome::KIND_ACCEPTED)->and($bus2->invokes)->toBe(1);
});

it('accept rejected returns refuse outcome without throw', function () {
    $proposal = seedPendingProposal();
    $proposal->status = Proposal::STATUS_REJECTED;
    $bus = proposalBus();
    $out = (makeProposalService($bus))->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_REFUSE)
        ->and($out->httpStatus)->toBe(409)
        ->and($bus->invokes)->toBe(0);
});

it('accept expired returns refuse outcome without throw', function () {
    $proposal = seedPendingProposal();
    $proposal->status = Proposal::STATUS_EXPIRED;
    $bus = proposalBus();
    $out = (makeProposalService($bus))->accept($proposal->ulid);
    expect($out->kind)->toBe(AcceptOutcome::KIND_REFUSE)
        ->and($out->httpStatus)->toBe(410)
        ->and($bus->invokes)->toBe(0);
});

it('ownedBy is true only for the owner of the proposal conversation', function () {
    $proposal = seedPendingProposal();
    $ownerId = (string) proposalStore()->proposal($proposal->ulid)->conversation?->user_id;
    $service = makeProposalService(proposalBus());

    expect($service->ownedBy($proposal->ulid, $ownerId))->toBeTrue()
        ->and($service->ownedBy($proposal->ulid, $ownerId.'9'))->toBeFalse()
        ->and($service->ownedBy('PROPDOESNOTEXIST0001', $ownerId))->toBeFalse();
});

it('ownedBy is false for a proposal whose conversation has no owner', function () {
    $proposal = seedPendingProposal(withUser: false);

    expect(makeProposalService(proposalBus())->ownedBy($proposal->ulid, ''))->toBeFalse();
});

/**
 * Swap in a store where a peer wins the next CAS into $targetStatus: the proposal is moved
 * to $peerStatus and the transition reports lost (false), exactly once.
 */
function proposalRaceTo(string $targetStatus, string $peerStatus): InMemoryConversationStore
{
    $store = new class($targetStatus, $peerStatus) extends InMemoryConversationStore
    {
        private bool $raced = false;

        public function __construct(private string $targetStatus, private string $peerStatus) {}

        public function transitionProposal(string $proposalUlid, string $fromStatus, array $attributes): bool
        {
            if (! $this->raced && ($attributes['status'] ?? null) === $this->targetStatus) {
                $this->raced = true;
                $this->proposal($proposalUlid)->status = $this->peerStatus;

                return false;
            }

            return parent::transitionProposal($proposalUlid, $fromStatus, $attributes);
        }
    };
    proposalWorld()->store = $store;

    return $store;
}

it('reject that loses the CAS to a concurrent reject returns the rejected row', function () {
    proposalRaceTo(Proposal::STATUS_REJECTED, Proposal::STATUS_REJECTED);
    $proposal = seedPendingProposal();

    expect(makeProposalService(proposalBus())->reject($proposal->ulid)->status)->toBe(Proposal::STATUS_REJECTED);
});

it('reject that loses the CAS to a concurrent accept claim refuses', function () {
    proposalRaceTo(Proposal::STATUS_REJECTED, Proposal::STATUS_ACCEPTING);
    $proposal = seedPendingProposal();

    expect(fn () => makeProposalService(proposalBus())->reject($proposal->ulid))
        ->toThrow(RuntimeException::class, 'cannot be rejected (status=accepting)');
});

it('accept that loses the pending claim to a peer that already accepted does not invoke again', function () {
    proposalRaceTo(Proposal::STATUS_ACCEPTING, Proposal::STATUS_ACCEPTED);
    $proposal = seedPendingProposal();
    $bus = proposalBus();

    $out = makeProposalService($bus)->accept($proposal->ulid);

    expect($out->kind)->toBe(AcceptOutcome::KIND_ACCEPTED)
        ->and($bus->invokes)->toBe(0);
});

it('accept whose accepted write loses to a peer that already recorded accepted still reports accepted', function () {
    proposalRaceTo(Proposal::STATUS_ACCEPTED, Proposal::STATUS_ACCEPTED);
    $proposal = seedPendingProposal();
    $bus = proposalBus();

    $out = makeProposalService($bus)->accept($proposal->ulid);

    expect($out->kind)->toBe(AcceptOutcome::KIND_ACCEPTED)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_ACCEPTED)
        ->and($bus->invokes)->toBe(1);
});

it('accept whose accepted write loses to a different terminal state fails loudly', function () {
    proposalRaceTo(Proposal::STATUS_ACCEPTED, Proposal::STATUS_EXPIRED);
    $proposal = seedPendingProposal();

    expect(fn () => makeProposalService(proposalBus())->accept($proposal->ulid))
        ->toThrow(RuntimeException::class, 'lost accept claim (status=expired)');
});

it('terminal failure whose failed write loses to a peer that already failed returns the failed outcome', function () {
    proposalRaceTo(Proposal::STATUS_FAILED, Proposal::STATUS_FAILED);
    $proposal = seedPendingProposal();
    $bus = proposalBus(static fn () => CapabilityResult::failure('domain_error', 'nope'));

    $out = makeProposalService($bus)->accept($proposal->ulid);

    expect($out->kind)->toBe(AcceptOutcome::KIND_FAILED)
        ->and($out->proposal->status)->toBe(Proposal::STATUS_FAILED);
});

it('terminal failure whose failed write loses to a peer accept fails loudly', function () {
    proposalRaceTo(Proposal::STATUS_FAILED, Proposal::STATUS_ACCEPTED);
    $proposal = seedPendingProposal();
    $bus = proposalBus(static fn () => CapabilityResult::failure('domain_error', 'nope'));

    expect(fn () => makeProposalService($bus)->accept($proposal->ulid))
        ->toThrow(RuntimeException::class, 'lost fail claim (status=accepted)');
});
