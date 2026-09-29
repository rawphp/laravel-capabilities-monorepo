<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Schema\CatalogPresenter;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\CapabilitiesAi\Contracts\ToolCatalog;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\ProposalService;
use Rawphp\CapabilitiesAi\Domain\TurnService;
use Rawphp\CapabilitiesAi\Http\ChatController;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\AlwaysReadyIdempotency;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\ResolveConversationActor;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryActorLookup;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryConversationStore;
use Rawphp\CapabilitiesAi\Tests\Fakes\InMemoryTurnClaim;

class ChatControllerTestUser extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}

final class ChatControllerAuthUser implements Authenticatable
{
    public function __construct(private readonly int|string $id) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}

/**
 * @param  array<string, mixed>  $body
 */
function messageRequest(array $body, ?Authenticatable $user): Request
{
    $request = Request::create('/messages', 'POST', $body);
    $request->setUserResolver(static fn () => $user);

    return $request;
}

/**
 * Per-test in-memory rows, the turn claim over them, and host users (no database).
 *
 * @return object{store: InMemoryConversationStore, claim: InMemoryTurnClaim, users: InMemoryActorLookup}
 */
function httpWorld(bool $reset = false): object
{
    static $world = null;
    if ($reset || $world === null) {
        $store = new InMemoryConversationStore;
        $world = (object) ['store' => $store, 'claim' => new InMemoryTurnClaim($store), 'users' => new InMemoryActorLookup];
    }

    return $world;
}

beforeEach(function () {
    httpWorld(reset: true);
});

function httpConversationService(ArrayProgressStore $progress): ConversationService
{
    return new ConversationService(static fn ($j) => null, $progress, store: httpWorld()->store);
}

function httpTurnService(ArrayProgressStore $progress): TurnService
{
    return new TurnService($progress, httpWorld()->store, httpWorld()->claim);
}

function httpUser(string $name): ChatControllerTestUser
{
    return httpWorld()->users->add(new ChatControllerTestUser(['name' => $name]));
}

function httpProposal(Turn $turn, string $ulid): Proposal
{
    return httpWorld()->store->createProposal($turn, $ulid, 'action', [], 'demo.cap', null);
}

function httpRequestAs(?string $userId): Request
{
    $request = Request::create('/proposals', 'POST');
    $user = $userId === null ? null : new class($userId) implements Authenticatable
    {
        public function __construct(private readonly string $id) {}

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return $this->id;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): string
        {
            return '';
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return '';
        }
    };
    $request->setUserResolver(static fn () => $user);

    return $request;
}

function httpProposalService(CapabilityBus $bus): ProposalService
{
    return new ProposalService(
        $bus,
        new AlwaysReadyIdempotency,
        new ResolveConversationActor(lookup: httpWorld()->users),
        new class implements ToolCatalog
        {
            public function toolsForTurn(string $conversationUlid, string $turnUlid): array
            {
                return [['name' => 'demo.cap']];
            }
        },
        httpWorld()->store,
    );
}

function chatRequest(?string $userId, string $method = 'GET', array $params = []): Request
{
    $request = Request::create('/chat', $method, $params);
    $request->setUserResolver(static fn () => $userId === null ? null : new ChatControllerAuthUser($userId));

    return $request;
}

/**
 * ConversationService over the in-memory store: conversation routes need no database.
 *
 * @return array{0: ConversationService, 1: InMemoryConversationStore}
 */
function httpConversations(?callable $dispatch = null, int $maxConcurrentTurns = 0): array
{
    $store = httpWorld()->store;

    return [
        new ConversationService(
            $dispatch ?? static fn ($j) => null,
            new ArrayProgressStore,
            maxConcurrentTurns: $maxConcurrentTurns,
            store: $store,
        ),
        $store,
    ];
}

it('history returns 200 with messages', function () {
    [$conversations] = httpConversations();
    $ids = $conversations->createUserMessage('hello', userId: 'u1');
    $response = (new ChatController)->history(chatRequest('u1'), $ids['conversation_ulid'], $conversations);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['messages'][0]['content'])->toBe('hello');
});

it('history returns 404 when missing', function () {
    [$conversations] = httpConversations();
    $response = (new ChatController)->history(chatRequest('u1'), '01MISSINGCONV00000000000', $conversations);
    expect($response->getStatusCode())->toBe(404);
});

it('history returns 404 for another user\'s conversation', function () {
    [$conversations] = httpConversations();
    $ids = $conversations->createUserMessage('secret', userId: 'u1');
    $response = (new ChatController)->history(chatRequest('u2'), $ids['conversation_ulid'], $conversations);

    expect($response->getStatusCode())->toBe(404);
    expectChatErrorEnvelope($response->getData(true), 'not_found', 'Conversation not found');
});

it('storeMessage owns the conversation by the authenticated user, not the body user_id', function () {
    [$conversations, $store] = httpConversations();
    $request = chatRequest('u1', 'POST', ['content' => 'hi', 'user_id' => 'victim']);
    $response = (new ChatController)->storeMessage($request, $conversations);

    $ulid = $response->getData(true)['conversation_ulid'];
    expect($response->getStatusCode())->toBe(201)
        ->and($store->conversation($ulid)->user_id)->toBe('u1');
});

it('storeMessage returns 404 when appending to another user\'s conversation', function () {
    [$conversations, $store] = httpConversations();
    $ids = $conversations->createUserMessage('mine', userId: 'u1');
    $request = chatRequest('u2', 'POST', ['content' => 'intrude', 'conversation_ulid' => $ids['conversation_ulid']]);

    expect((new ChatController)->storeMessage($request, $conversations)->getStatusCode())->toBe(404)
        ->and($store->turns)->toHaveCount(1);
});

it('every chat route returns 401 without an authenticated user and touches nothing', function () {
    [$conversations, $store] = httpConversations();
    $turns = httpTurnService(new ArrayProgressStore);
    $ids = $conversations->createUserMessage('owned', userId: 'u1');
    $controller = new ChatController;
    $anon = chatRequest(null, 'POST', ['content' => 'x']);

    $responses = [
        $controller->history($anon, $ids['conversation_ulid'], $conversations),
        $controller->storeMessage($anon, $conversations),
        $controller->showTurn($anon, $ids['turn_ulid'], $turns),
        $controller->cancelTurn($anon, $ids['turn_ulid'], $turns),
        $controller->turnEvents($anon, $ids['turn_ulid'], $turns),
        $controller->destroyConversation($anon, $ids['conversation_ulid'], $conversations),
    ];

    foreach ($responses as $response) {
        expect($response->getStatusCode())->toBe(401);
        expectChatErrorEnvelope($response->getData(true), 'unauthenticated', 'Unauthenticated');
    }
    expect($store->turns)->toHaveCount(1)
        ->and($store->turns[0]->status)->toBe(Turn::STATUS_QUEUED)
        ->and($store->conversations[0]->status)->toBe('open');
});

it('showTurn, cancelTurn and turnEvents return 404 for another user\'s turn', function () {
    $progress = new ArrayProgressStore;
    $conversations = httpConversationService($progress);
    $ids = $conversations->createUserMessage('t', userId: 'u1');
    $turns = httpTurnService($progress);
    $controller = new ChatController;
    $other = chatRequest('u2');

    expect($controller->showTurn($other, $ids['turn_ulid'], $turns)->getStatusCode())->toBe(404)
        ->and($controller->cancelTurn($other, $ids['turn_ulid'], $turns)->getStatusCode())->toBe(404)
        ->and($controller->turnEvents($other, $ids['turn_ulid'], $turns)->getStatusCode())->toBe(404)
        ->and(httpWorld()->store->turn($ids['turn_ulid'])->status)->toBe(Turn::STATUS_QUEUED);
});

it('storeMessage creates a turn and appends to an existing conversation', function () {
    $dispatched = [];
    [$conversations, $store] = httpConversations(static function ($job) use (&$dispatched): void {
        $dispatched[] = $job;
    });
    $controller = new ChatController;

    $first = $controller->storeMessage(chatRequest('u1', 'POST', ['content' => 'hi']), $conversations);
    expect($first->getStatusCode())->toBe(201);
    $conversationUlid = $first->getData(true)['conversation_ulid'];

    $second = $controller->storeMessage(
        chatRequest('u1', 'POST', ['content' => 'again', 'conversation_ulid' => $conversationUlid]),
        $conversations,
    );
    expect($second->getStatusCode())->toBe(201)
        ->and($second->getData(true)['conversation_ulid'])->toBe($conversationUlid)
        ->and($store->conversations)->toHaveCount(1)
        ->and($store->turns)->toHaveCount(2)
        ->and($dispatched)->toHaveCount(2);
});

it('storeMessage rejects invalid input with 422 before creating rows or dispatching', function (array $input, string $field) {
    $dispatched = 0;
    [$conversations, $store] = httpConversations(static function () use (&$dispatched): void {
        $dispatched++;
    });

    $response = (new ChatController)->storeMessage(chatRequest('u1', 'POST', $input), $conversations);

    $body = $response->getData(true);
    expect($response->getStatusCode())->toBe(422)
        ->and($body['ok'])->toBeFalse()
        ->and($body['error']['code'])->toBe('validation_failed')
        ->and($body['error']['message'])->toBe('The given data was invalid.')
        ->and($body['error']['http_status'])->toBe(422)
        ->and(array_column($body['error']['violations'], 'field'))->toBe([$field])
        ->and($body['error']['violations'][0]['message'])->toBeString()->not->toBe('')
        ->and($body)->not->toHaveKeys(['message', 'errors'])
        ->and($store->conversations)->toBe([])
        ->and($store->messages)->toBe([])
        ->and($store->turns)->toBe([])
        ->and($dispatched)->toBe(0);
})->with([
    'missing content' => [[], 'content'],
    'empty content' => [['content' => ''], 'content'],
    'whitespace content' => [['content' => "  \n\t"], 'content'],
    'non-string content' => [['content' => ['x']], 'content'],
    'malformed conversation_ulid' => [['content' => 'hi', 'conversation_ulid' => 'not-a-ulid'], 'conversation_ulid'],
    'short conversation_ulid' => [['content' => 'hi', 'conversation_ulid' => str_repeat('A', 25)], 'conversation_ulid'],
    'lowercase conversation_ulid' => [['content' => 'hi', 'conversation_ulid' => str_repeat('a', 26)], 'conversation_ulid'],
    'non-string conversation_ulid' => [['content' => 'hi', 'conversation_ulid' => ['x']], 'conversation_ulid'],
]);

it('storeMessage returns 404 for a well-formed but unknown conversation_ulid', function () {
    [$conversations, $store] = httpConversations();

    $response = (new ChatController)->storeMessage(
        chatRequest('u1', 'POST', ['content' => 'hi', 'conversation_ulid' => str_repeat('0', 26)]),
        $conversations,
    );

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true)['error']['message'])->toBe('Conversation not found')
        ->and($store->messages)->toBe([]);
});

it('showTurn and cancelTurn happy path', function () {
    $progress = new ArrayProgressStore;
    $conversations = httpConversationService($progress);
    $ids = $conversations->createUserMessage('t', userId: 'u1');
    $turns = httpTurnService($progress);

    $show = (new ChatController)->showTurn(chatRequest('u1'), $ids['turn_ulid'], $turns);
    expect($show->getStatusCode())->toBe(200)
        ->and($show->getData(true)['status'])->toBe(Turn::STATUS_QUEUED);

    $cancel = (new ChatController)->cancelTurn(chatRequest('u1'), $ids['turn_ulid'], $turns);
    expect($cancel->getStatusCode())->toBe(200)
        ->and($cancel->getData(true)['status'])->toBe(Turn::STATUS_CANCELLED);
});

it('cancelTurn returns 409 on illegal transition', function () {
    $progress = new ArrayProgressStore;
    $conversations = httpConversationService($progress);
    $ids = $conversations->createUserMessage('done', userId: 'u1');
    httpWorld()->store->turn($ids['turn_ulid'])->status = Turn::STATUS_COMPLETED;
    $response = (new ChatController)->cancelTurn(chatRequest('u1'), $ids['turn_ulid'], httpTurnService($progress));
    expect($response->getStatusCode())->toBe(409);
});

it('turnEvents passes cursor and returns events', function () {
    $progress = new ArrayProgressStore;
    $conversations = httpConversationService($progress);
    $ids = $conversations->createUserMessage('e', userId: 'u1');
    $progress->append($ids['turn_ulid'], ['kind' => 'token', 'data' => ['t' => 1]]);
    $request = chatRequest('u1', 'GET', ['cursor' => 0]);
    $response = (new ChatController)->turnEvents($request, $ids['turn_ulid'], httpTurnService($progress));
    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['events'])->not->toBeEmpty();
});

it('destroyConversation 409 when active turns and 200 when closed', function () {
    [$conversations, $store] = httpConversations();
    $ids = $conversations->createUserMessage('active', userId: 'u1');
    $controller = new ChatController;

    expect($controller->destroyConversation(chatRequest('u1'), $ids['conversation_ulid'], $conversations)->getStatusCode())
        ->toBe(409);

    $store->turn($ids['turn_ulid'])->status = Turn::STATUS_COMPLETED;
    expect($controller->destroyConversation(chatRequest('u2'), $ids['conversation_ulid'], $conversations)->getStatusCode())
        ->toBe(404);

    $ok = $controller->destroyConversation(chatRequest('u1'), $ids['conversation_ulid'], $conversations);
    expect($ok->getStatusCode())->toBe(200)
        ->and($ok->getData(true)['status'])->toBe('closed')
        ->and($ok->getData(true)['closed'] ?? null)->toBeTrue();
});

it('storeMessage returns 201 with ids, then a 429 rate_limited envelope at the turn ceiling', function () {
    [$conversations] = httpConversations(maxConcurrentTurns: 1);
    $controller = new ChatController;

    $created = $controller->storeMessage(messageRequest(['content' => 'one'], new ChatControllerAuthUser('u1')), $conversations);
    expect($created->getStatusCode())->toBe(201)
        ->and($created->getData(true))->toHaveKey('turn_ulid');

    $busy = $controller->storeMessage(messageRequest(['content' => 'two'], new ChatControllerAuthUser('u1')), $conversations);
    $body = $busy->getData(true);
    expect($busy->getStatusCode())->toBe(429)
        ->and($body['ok'])->toBeFalse()
        ->and($body['error']['code'])->toBe('rate_limited')
        ->and($body['error']['retryable'])->toBeTrue()
        ->and($body['error']['http_status'])->toBe(429)
        ->and($body['error']['message'])->toContain('retry later')
        ->and($body)->not->toHaveKeys(['outcome', 'message', 'turn_ulid']);
});

it('controller source delegates without Eloquent creates', function () {
    $src = file_get_contents(dirname(__DIR__, 3).'/src/Http/ChatController.php') ?: '';
    expect($src)->toContain('TurnService')
        ->and($src)->toContain('ConversationService')
        ->and($src)->not->toContain('::query()->create')
        ->and($src)->not->toContain('::query()->update')
        ->and($src)->not->toContain('::query()->where');
});

it('acceptProposal maps accepted / approval / retry / failed / refuse / unresolved actor without uncaught exceptions', function () {
    $user = httpUser('http-user');
    $conversations = httpConversationService(new ArrayProgressStore);
    $ids = $conversations->createUserMessage('p', userId: (string) $user->id);
    $turn = httpWorld()->store->turn($ids['turn_ulid']);

    $makeProposal = static function (string $suffix) use ($turn): Proposal {
        return httpProposal($turn, 'PROP'.strtoupper($suffix).bin2hex(random_bytes(6)));
    };

    $busOk = new class implements CapabilityBus
    {
        public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
        {
            return CapabilityResult::ok();
        }

        public function catalog(): CatalogPresenter
        {
            throw new RuntimeException('unused');
        }
    };

    $controller = new ChatController;
    $asOwner = httpRequestAs((string) $user->id);
    $ok = $controller->acceptProposal(
        $asOwner,
        $makeProposal('ok')->ulid,
        httpProposalService($busOk),
    );
    expect($ok->getStatusCode())->toBe(200)
        ->and($ok->getData(true)['outcome'])->toBe('accepted');

    $busApr = new class implements CapabilityBus
    {
        public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
        {
            return CapabilityResult::approvalRequired('a1');
        }

        public function catalog(): CatalogPresenter
        {
            throw new RuntimeException('unused');
        }
    };
    $apr = $controller->acceptProposal(
        $asOwner,
        $makeProposal('apr')->ulid,
        httpProposalService($busApr),
    );
    expect($apr->getStatusCode())->toBe(202)
        ->and($apr->getData(true)['outcome'])->toBe('approval_required')
        ->and($apr->getData(true)['status'])->toBe(Proposal::STATUS_ACCEPTING);

    $busRetry = new class implements CapabilityBus
    {
        public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
        {
            return CapabilityResult::failure('rate_limited', 'later', ['retryable' => true]);
        }

        public function catalog(): CatalogPresenter
        {
            throw new RuntimeException('unused');
        }
    };
    $retry = $controller->acceptProposal(
        $asOwner,
        $makeProposal('rty')->ulid,
        httpProposalService($busRetry),
    );
    expect($retry->getStatusCode())->toBe(429)
        ->and($retry->getData(true)['outcome'])->toBe('retryable');

    $busFail = new class implements CapabilityBus
    {
        public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
        {
            return CapabilityResult::failure('domain_error', 'bad');
        }

        public function catalog(): CatalogPresenter
        {
            throw new RuntimeException('unused');
        }
    };
    $fail = $controller->acceptProposal(
        $asOwner,
        $makeProposal('fai')->ulid,
        httpProposalService($busFail),
    );
    expect($fail->getStatusCode())->toBe(422)
        ->and($fail->getData(true)['outcome'])->toBe('failed');

    $busRefuse = new class implements CapabilityBus
    {
        public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
        {
            return CapabilityResult::failure('forbidden', 'no');
        }

        public function catalog(): CatalogPresenter
        {
            throw new RuntimeException('unused');
        }
    };
    $refuse = $controller->acceptProposal(
        $asOwner,
        $makeProposal('ref')->ulid,
        httpProposalService($busRefuse),
    );
    expect($refuse->getStatusCode())->toBe(403)
        ->and($refuse->getData(true)['outcome'])->toBe('refuse');

    $rejected = $makeProposal('rjd');
    $rejected->status = Proposal::STATUS_REJECTED;
    $rej = $controller->acceptProposal(
        $asOwner,
        $rejected->ulid,
        httpProposalService($busOk),
    );
    expect($rej->getStatusCode())->toBe(409)
        ->and($rej->getData(true)['outcome'])->toBe('refuse');

    $orphan = $makeProposal('orp');
    httpWorld()->users->remove($user);
    $unresolved = $controller->acceptProposal(
        $asOwner,
        $orphan->ulid,
        httpProposalService($busOk),
    );
    expect($unresolved->getStatusCode())->toBe(403)
        ->and($unresolved->getData(true)['outcome'])->toBe('refuse')
        ->and($unresolved->getData(true)['status'])->toBe(Proposal::STATUS_FAILED)
        ->and($unresolved->getData(true)['error']['code'])->toBe('forbidden');

    $missing = $controller->acceptProposal(
        $asOwner,
        'PROPDOESNOTEXIST0001',
        httpProposalService($busOk),
    );
    expect($missing->getStatusCode())->toBe(404)
        ->and($missing->getData(true)['ok'])->toBeFalse()
        ->and($missing->getData(true)['error']['code'])->toBe('not_found')
        ->and($missing->getData(true)['error']['message'])->toBe('Proposal not found');
});

/**
 * @param  array<string, mixed>  $body
 */
function expectChatErrorEnvelope(array $body, string $code, string $message): void
{
    expect($body['ok'])->toBeFalse()
        ->and($body['meta'])->toBe([])
        ->and($body['error']['code'])->toBe($code)
        ->and($body['error']['message'])->toBe($message)
        ->and($body['error']['violations'])->toBe([])
        ->and($body['error']['retryable'])->toBeFalse()
        ->and($body['error'])->toHaveKeys(['approval_id', 'request_id', 'retryable', 'http_status', 'cli_exit'])
        ->and($body)->not->toHaveKey('message');
}

it('not-found branches use the D-018 not_found envelope', function () {
    $progress = new ArrayProgressStore;
    $conversations = httpConversationService($progress);
    $turns = httpTurnService($progress);
    $proposals = httpProposalService(new class implements CapabilityBus
    {
        public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
        {
            throw new RuntimeException('unused');
        }

        public function catalog(): CatalogPresenter
        {
            throw new RuntimeException('unused');
        }
    });
    $controller = new ChatController;
    $missingConv = '01MISSINGCONV00000000000';
    $missingTurn = '01MISSINGTURN00000000000';

    $cases = [
        [$controller->history(chatRequest('u1'), $missingConv, $conversations), 'Conversation not found'],
        [$controller->storeMessage(chatRequest('u1', 'POST', ['content' => 'x', 'conversation_ulid' => str_repeat('0', 26)]), $conversations), 'Conversation not found'],
        [$controller->destroyConversation(chatRequest('u1'), $missingConv, $conversations), 'Conversation not found'],
        [$controller->showTurn(chatRequest('u1'), $missingTurn, $turns), 'Turn not found'],
        [$controller->cancelTurn(chatRequest('u1'), $missingTurn, $turns), 'Turn not found'],
        [$controller->turnEvents(chatRequest('u1'), $missingTurn, $turns), 'Turn not found'],
        [$controller->rejectProposal(chatRequest('u1'), 'PROPDOESNOTEXIST0001', $proposals), 'Proposal not found'],
    ];

    foreach ($cases as [$response, $message]) {
        expect($response->getStatusCode())->toBe(404);
        expectChatErrorEnvelope($response->getData(true), 'not_found', $message);
    }
});

it('domain conflict branches use the D-018 conflict envelope', function () {
    $progress = new ArrayProgressStore;
    $conversations = httpConversationService($progress);
    $ids = $conversations->createUserMessage('busy', userId: 'u1');
    $controller = new ChatController;

    $destroy = $controller->destroyConversation(chatRequest('u1'), $ids['conversation_ulid'], $conversations);
    expect($destroy->getStatusCode())->toBe(409);
    expectChatErrorEnvelope(
        $destroy->getData(true),
        'conflict',
        "Conversation {$ids['conversation_ulid']} has queued or running turns",
    );

    httpWorld()->store->turn($ids['turn_ulid'])->status = Turn::STATUS_COMPLETED;
    $cancel = $controller->cancelTurn(chatRequest('u1'), $ids['turn_ulid'], httpTurnService($progress));
    expect($cancel->getStatusCode())->toBe(409);
    expectChatErrorEnvelope(
        $cancel->getData(true),
        'conflict',
        "Turn {$ids['turn_ulid']} cannot be cancelled (status=".Turn::STATUS_COMPLETED.')',
    );
});

it('storeMessage owns the conversation as the authenticated user and ignores body user_id', function () {
    [$conversations, $store] = httpConversations();

    $response = (new ChatController)->storeMessage(
        messageRequest(['content' => 'hi', 'user_id' => '999'], new ChatControllerAuthUser(7)),
        $conversations,
    );

    expect($response->getStatusCode())->toBe(201);
    expect($store->conversation($response->getData(true)['conversation_ulid'])->user_id)->toBe('7');
});

it('storeMessage returns 401 without an authenticated user and creates nothing', function () {
    [$conversations, $store] = httpConversations();

    $response = (new ChatController)->storeMessage(
        messageRequest(['content' => 'hi', 'user_id' => '7'], null),
        $conversations,
    );

    expect($response->getStatusCode())->toBe(401)
        ->and($store->conversations)->toBe([])
        ->and($store->messages)->toBe([]);
});

it('storeMessage returns 404 and appends no message for an int-id user on another user\'s conversation', function () {
    [$conversations, $store] = httpConversations();
    $owned = $conversations->createUserMessage('mine', userId: '7');

    $response = (new ChatController)->storeMessage(
        messageRequest(['content' => 'as you', 'conversation_ulid' => $owned['conversation_ulid']], new ChatControllerAuthUser(8)),
        $conversations,
    );

    expect($response->getStatusCode())->toBe(404)
        ->and($store->messages)->toHaveCount(1);

    $ownerReply = (new ChatController)->storeMessage(
        messageRequest(['content' => 'still me', 'conversation_ulid' => $owned['conversation_ulid']], new ChatControllerAuthUser(7)),
        $conversations,
    );
    expect($ownerReply->getStatusCode())->toBe(201)
        ->and($ownerReply->getData(true)['conversation_ulid'])->toBe($owned['conversation_ulid']);
});

function seedHttpProposal(?string $ownerId): Proposal
{
    $ids = httpConversationService(new ArrayProgressStore)->createUserMessage('p', userId: $ownerId);

    return httpProposal(httpWorld()->store->turn($ids['turn_ulid']), 'PROP'.strtoupper(bin2hex(random_bytes(8))));
}

function countingBus(): CapabilityBus
{
    return new class implements CapabilityBus
    {
        public int $invokes = 0;

        public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
        {
            $this->invokes++;

            return CapabilityResult::ok();
        }

        public function catalog(): CatalogPresenter
        {
            throw new RuntimeException('unused');
        }
    };
}

it('acceptProposal and rejectProposal return 401 without an authenticated user and leave the proposal pending', function () {
    $owner = httpUser('owner');
    $proposal = seedHttpProposal((string) $owner->id);
    $bus = countingBus();
    $controller = new ChatController;

    expect($controller->acceptProposal(httpRequestAs(null), $proposal->ulid, httpProposalService($bus))->getStatusCode())->toBe(401)
        ->and($controller->rejectProposal(httpRequestAs(null), $proposal->ulid, httpProposalService($bus))->getStatusCode())->toBe(401)
        ->and($bus->invokes)->toBe(0)
        ->and(httpWorld()->store->proposal($proposal->ulid)->status)->toBe(Proposal::STATUS_PENDING);
});

it('acceptProposal and rejectProposal return 404 for another user\'s proposal without invoking the bus or changing status', function () {
    $owner = httpUser('owner');
    $intruder = httpUser('intruder');
    $proposal = seedHttpProposal((string) $owner->id);
    $bus = countingBus();
    $controller = new ChatController;
    $asIntruder = httpRequestAs((string) $intruder->id);

    $accept = $controller->acceptProposal($asIntruder, $proposal->ulid, httpProposalService($bus));
    $reject = $controller->rejectProposal($asIntruder, $proposal->ulid, httpProposalService($bus));

    expect($accept->getStatusCode())->toBe(404)
        ->and($accept->getData(true)['error']['message'])->toBe('Proposal not found')
        ->and($reject->getStatusCode())->toBe(404)
        ->and($reject->getData(true)['error']['message'])->toBe('Proposal not found')
        ->and($bus->invokes)->toBe(0)
        ->and(httpWorld()->store->proposal($proposal->ulid)->status)->toBe(Proposal::STATUS_PENDING);
});

it('acceptProposal and rejectProposal return 404 for an ownerless conversation\'s proposal', function () {
    $user = httpUser('someone');
    $proposal = seedHttpProposal(null);
    $bus = countingBus();
    $controller = new ChatController;
    $asUser = httpRequestAs((string) $user->id);

    expect($controller->acceptProposal($asUser, $proposal->ulid, httpProposalService($bus))->getStatusCode())->toBe(404)
        ->and($controller->rejectProposal($asUser, $proposal->ulid, httpProposalService($bus))->getStatusCode())->toBe(404)
        ->and($bus->invokes)->toBe(0)
        ->and(httpWorld()->store->proposal($proposal->ulid)->status)->toBe(Proposal::STATUS_PENDING);
});

it('rejectProposal lets the owner reject, maps missing to 404 and non-pending to 409', function () {
    $owner = httpUser('owner');
    $proposal = seedHttpProposal((string) $owner->id);
    $bus = countingBus();
    $controller = new ChatController;
    $asOwner = httpRequestAs((string) $owner->id);

    $ok = $controller->rejectProposal($asOwner, $proposal->ulid, httpProposalService($bus));
    expect($ok->getStatusCode())->toBe(200)
        ->and($ok->getData(true))->toBe(['ulid' => $proposal->ulid, 'status' => Proposal::STATUS_REJECTED])
        ->and($bus->invokes)->toBe(0);

    expect($controller->rejectProposal($asOwner, 'PROPDOESNOTEXIST0001', httpProposalService($bus))->getStatusCode())->toBe(404);

    $accepted = seedHttpProposal((string) $owner->id);
    $accepted->status = Proposal::STATUS_ACCEPTED;
    $conflict = $controller->rejectProposal($asOwner, $accepted->ulid, httpProposalService($bus));
    expect($conflict->getStatusCode())->toBe(409);
    expectChatErrorEnvelope(
        $conflict->getData(true),
        'conflict',
        "Proposal {$accepted->ulid} cannot be rejected (status=".Proposal::STATUS_ACCEPTED.')',
    );
});

it('acceptProposal and rejectProposal map a proposal deleted after the owner check to 404', function () {
    // Delete the row right after the ownership probe so the service lookup races a host delete.
    $racing = new class extends InMemoryConversationStore
    {
        public function proposalOwnedBy(string $proposalUlid, string $ownerId): bool
        {
            $owned = parent::proposalOwnedBy($proposalUlid, $ownerId);
            $this->proposals = [];

            return $owned;
        }
    };
    httpWorld()->store = $racing;
    httpWorld()->claim = new InMemoryTurnClaim($racing);
    $owner = httpUser('owner');
    $controller = new ChatController;
    $asOwner = httpRequestAs((string) $owner->id);

    expect($controller->acceptProposal($asOwner, seedHttpProposal((string) $owner->id)->ulid, httpProposalService(countingBus()))->getStatusCode())->toBe(404)
        ->and($controller->rejectProposal($asOwner, seedHttpProposal((string) $owner->id)->ulid, httpProposalService(countingBus()))->getStatusCode())->toBe(404);
});

it('storeMessage returns a 429 rate_limited envelope when the user is over turns_per_minute', function () {
    $limiter = new InMemoryRateLimiter;
    $limiter->hit('rl:ai:user:u1', 60);
    $dispatched = 0;
    $conversations = new ConversationService(static function () use (&$dispatched): void {
        $dispatched++;
    }, new ArrayProgressStore, turnLimiter: $limiter, turnsPerMinute: 1, store: new InMemoryConversationStore);

    $response = (new ChatController)->storeMessage(chatRequest('u1', 'POST', ['content' => 'hi']), $conversations);

    $body = $response->getData(true);
    expect($response->getStatusCode())->toBe(429)
        ->and($body['error']['code'])->toBe('rate_limited')
        ->and($body['error']['retryable'])->toBeTrue()
        ->and($dispatched)->toBe(0);
});

it('storeMessage returns 409 conflict for a closed conversation and creates nothing', function () {
    [$conversations, $store] = httpConversations();
    $ids = $conversations->createUserMessage('bye', userId: 'u1');
    $store->turn($ids['turn_ulid'])->status = Turn::STATUS_COMPLETED;
    $conversations->destroy($ids['conversation_ulid'], 'u1');

    $response = (new ChatController)->storeMessage(
        chatRequest('u1', 'POST', ['content' => 'again', 'conversation_ulid' => $ids['conversation_ulid']]),
        $conversations,
    );

    expect($response->getStatusCode())->toBe(409)
        ->and($store->turns)->toHaveCount(1)
        ->and($store->messages)->toHaveCount(1);
    expectChatErrorEnvelope($response->getData(true), 'conflict', "Conversation {$ids['conversation_ulid']} is closed");
});
