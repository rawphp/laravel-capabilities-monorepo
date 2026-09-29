<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Schema\CatalogPresenter;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesAi\Contracts\ToolCatalog;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\ProposalService;
use Rawphp\CapabilitiesAi\Domain\TurnService;
use Rawphp\CapabilitiesAi\Http\ChatController;
use Rawphp\CapabilitiesAi\Models\Conversation;
use Rawphp\CapabilitiesAi\Models\Message;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\AlwaysReadyIdempotency;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\ResolveConversationActor;

class ChatControllerTestUser extends Model
{
    protected $table = 'users';

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

function bootHttpSqlite(): ArrayProgressStore
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

    return new ArrayProgressStore;
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
        new ResolveConversationActor(ChatControllerTestUser::class),
        new class implements ToolCatalog
        {
            public function toolsForTurn(string $conversationUlid, string $turnUlid): array
            {
                return [['name' => 'demo.cap']];
            }
        },
    );
}

function chatRequest(?string $userId, string $method = 'GET', array $params = []): Request
{
    $request = Request::create('/chat', $method, $params);
    $request->setUserResolver(static fn () => $userId === null ? null : new ChatControllerAuthUser($userId));

    return $request;
}

it('history returns 200 with messages', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('hello', userId: 'u1');
    $response = (new ChatController)->history(chatRequest('u1'), $ids['conversation_ulid'], $conversations);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['messages'][0]['content'])->toBe('hello');
});

it('history returns 404 when missing', function () {
    bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, new ArrayProgressStore);
    $response = (new ChatController)->history(chatRequest('u1'), '01MISSINGCONV00000000000', $conversations);
    expect($response->getStatusCode())->toBe(404);
});

it('history returns 404 for another user\'s conversation', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('secret', userId: 'u1');
    $response = (new ChatController)->history(chatRequest('u2'), $ids['conversation_ulid'], $conversations);

    expect($response->getStatusCode())->toBe(404);
    expectChatErrorEnvelope($response->getData(true), 'not_found', 'Conversation not found');
});

it('storeMessage owns the conversation by the authenticated user, not the body user_id', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $request = chatRequest('u1', 'POST', ['content' => 'hi', 'user_id' => 'victim']);
    $response = (new ChatController)->storeMessage($request, $conversations);

    $ulid = $response->getData(true)['conversation_ulid'];
    expect($response->getStatusCode())->toBe(201)
        ->and(Conversation::query()->where('ulid', $ulid)->value('user_id'))->toBe('u1');
});

it('storeMessage returns 404 when appending to another user\'s conversation', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('mine', userId: 'u1');
    $request = chatRequest('u2', 'POST', ['content' => 'intrude', 'conversation_ulid' => $ids['conversation_ulid']]);

    expect((new ChatController)->storeMessage($request, $conversations)->getStatusCode())->toBe(404)
        ->and(Turn::query()->count())->toBe(1);
});

it('every chat route returns 401 without an authenticated user and touches nothing', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $turns = new TurnService($progress);
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
    }
    expect(Turn::query()->count())->toBe(1)
        ->and(Turn::query()->value('status'))->toBe(Turn::STATUS_QUEUED);
});

it('showTurn, cancelTurn and turnEvents return 404 for another user\'s turn', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('t', userId: 'u1');
    $turns = new TurnService($progress);
    $controller = new ChatController;
    $other = chatRequest('u2');

    expect($controller->showTurn($other, $ids['turn_ulid'], $turns)->getStatusCode())->toBe(404)
        ->and($controller->cancelTurn($other, $ids['turn_ulid'], $turns)->getStatusCode())->toBe(404)
        ->and($controller->turnEvents($other, $ids['turn_ulid'], $turns)->getStatusCode())->toBe(404)
        ->and(Turn::query()->where('ulid', $ids['turn_ulid'])->value('status'))->toBe(Turn::STATUS_QUEUED);
});

it('storeMessage creates a turn and appends to an existing conversation', function () {
    bootHttpSqlite();
    $dispatched = [];
    $conversations = new ConversationService(static function ($job) use (&$dispatched): void {
        $dispatched[] = $job;
    }, new ArrayProgressStore);
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
        ->and(Conversation::query()->count())->toBe(1)
        ->and(Turn::query()->count())->toBe(2)
        ->and($dispatched)->toHaveCount(2);
});

it('storeMessage rejects invalid input with 422 before creating rows or dispatching', function (array $input, string $field) {
    bootHttpSqlite();
    $dispatched = 0;
    $conversations = new ConversationService(static function () use (&$dispatched): void {
        $dispatched++;
    }, new ArrayProgressStore);

    $response = (new ChatController)->storeMessage(chatRequest('u1', 'POST', $input), $conversations);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['errors'])->toHaveKey($field)
        ->and(Conversation::query()->count())->toBe(0)
        ->and(Message::query()->count())->toBe(0)
        ->and(Turn::query()->count())->toBe(0)
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
    bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, new ArrayProgressStore);

    $response = (new ChatController)->storeMessage(
        chatRequest('u1', 'POST', ['content' => 'hi', 'conversation_ulid' => str_repeat('0', 26)]),
        $conversations,
    );

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true)['error']['message'])->toBe('Conversation not found')
        ->and(Message::query()->count())->toBe(0);
});

it('showTurn and cancelTurn happy path', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('t', userId: 'u1');
    $turns = new TurnService($progress);

    $show = (new ChatController)->showTurn(chatRequest('u1'), $ids['turn_ulid'], $turns);
    expect($show->getStatusCode())->toBe(200)
        ->and($show->getData(true)['status'])->toBe(Turn::STATUS_QUEUED);

    $cancel = (new ChatController)->cancelTurn(chatRequest('u1'), $ids['turn_ulid'], $turns);
    expect($cancel->getStatusCode())->toBe(200)
        ->and($cancel->getData(true)['status'])->toBe(Turn::STATUS_CANCELLED);
});

it('cancelTurn returns 409 on illegal transition', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('done', userId: 'u1');
    Turn::query()->where('ulid', $ids['turn_ulid'])->update(['status' => Turn::STATUS_COMPLETED]);
    $response = (new ChatController)->cancelTurn(chatRequest('u1'), $ids['turn_ulid'], new TurnService($progress));
    expect($response->getStatusCode())->toBe(409);
});

it('turnEvents passes cursor and returns events', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('e', userId: 'u1');
    $progress->append($ids['turn_ulid'], ['kind' => 'token', 'data' => ['t' => 1]]);
    $request = chatRequest('u1', 'GET', ['cursor' => 0]);
    $response = (new ChatController)->turnEvents($request, $ids['turn_ulid'], new TurnService($progress));
    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['events'])->not->toBeEmpty();
});

it('destroyConversation 409 when active turns and 200 when closed', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('active', userId: 'u1');
    $controller = new ChatController;

    expect($controller->destroyConversation(chatRequest('u1'), $ids['conversation_ulid'], $conversations)->getStatusCode())
        ->toBe(409);

    Turn::query()->where('ulid', $ids['turn_ulid'])->update(['status' => Turn::STATUS_COMPLETED]);
    expect($controller->destroyConversation(chatRequest('u2'), $ids['conversation_ulid'], $conversations)->getStatusCode())
        ->toBe(404);

    $ok = $controller->destroyConversation(chatRequest('u1'), $ids['conversation_ulid'], $conversations);
    expect($ok->getStatusCode())->toBe(200)
        ->and($ok->getData(true)['status'])->toBe('closed')
        ->and($ok->getData(true)['closed'] ?? null)->toBeTrue();
});

it('storeMessage returns 201 with ids, then 429 retryable at the turn ceiling', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress, maxConcurrentTurns: 1);
    $controller = new ChatController;

    $created = $controller->storeMessage(messageRequest(['content' => 'one'], new ChatControllerAuthUser('u1')), $conversations);
    expect($created->getStatusCode())->toBe(201)
        ->and($created->getData(true))->toHaveKey('turn_ulid');

    $busy = $controller->storeMessage(messageRequest(['content' => 'two'], new ChatControllerAuthUser('u1')), $conversations);
    expect($busy->getStatusCode())->toBe(429)
        ->and($busy->getData(true)['outcome'])->toBe('retryable')
        ->and($busy->getData(true)['message'])->toContain('retry later')
        ->and($busy->getData(true))->not->toHaveKey('turn_ulid');
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
    $progress = bootHttpSqlite();
    $user = ChatControllerTestUser::query()->create(['name' => 'http-user']);
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('p', userId: (string) $user->id);
    $turn = Turn::query()->where('ulid', $ids['turn_ulid'])->firstOrFail();

    $makeProposal = static function (string $suffix) use ($turn): Proposal {
        return Proposal::query()->create([
            'turn_id' => $turn->id,
            'conversation_id' => $turn->conversation_id,
            'ulid' => 'PROP'.strtoupper($suffix).bin2hex(random_bytes(6)),
            'type' => 'action',
            'payload' => [],
            'target_capability' => 'demo.cap',
            'status' => Proposal::STATUS_PENDING,
        ]);
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
    $rejected->save();
    $rej = $controller->acceptProposal(
        $asOwner,
        $rejected->ulid,
        httpProposalService($busOk),
    );
    expect($rej->getStatusCode())->toBe(409)
        ->and($rej->getData(true)['outcome'])->toBe('refuse');

    $orphan = $makeProposal('orp');
    ChatControllerTestUser::query()->whereKey($user->id)->delete();
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
        ->and($body['error'])->toHaveKeys(['approval_id', 'request_id', 'retryable', 'http_status', 'cli_exit'])
        ->and($body)->not->toHaveKey('message');
}

it('not-found branches use the D-018 not_found envelope', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $turns = new TurnService($progress);
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
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('busy', userId: 'u1');
    $controller = new ChatController;

    $destroy = $controller->destroyConversation(chatRequest('u1'), $ids['conversation_ulid'], $conversations);
    expect($destroy->getStatusCode())->toBe(409);
    expectChatErrorEnvelope(
        $destroy->getData(true),
        'conflict',
        "Conversation {$ids['conversation_ulid']} has queued or running turns",
    );

    Turn::query()->where('ulid', $ids['turn_ulid'])->update(['status' => Turn::STATUS_COMPLETED]);
    $cancel = $controller->cancelTurn(chatRequest('u1'), $ids['turn_ulid'], new TurnService($progress));
    expect($cancel->getStatusCode())->toBe(409);
    expectChatErrorEnvelope(
        $cancel->getData(true),
        'conflict',
        "Turn {$ids['turn_ulid']} cannot be cancelled (status=".Turn::STATUS_COMPLETED.')',
    );
});

it('storeMessage owns the conversation as the authenticated user and ignores body user_id', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);

    $response = (new ChatController)->storeMessage(
        messageRequest(['content' => 'hi', 'user_id' => '999'], new ChatControllerAuthUser(7)),
        $conversations,
    );

    expect($response->getStatusCode())->toBe(201);
    $conversation = Conversation::query()->where('ulid', $response->getData(true)['conversation_ulid'])->firstOrFail();
    expect((string) $conversation->user_id)->toBe('7');
});

it('storeMessage returns 401 without an authenticated user and creates nothing', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);

    $response = (new ChatController)->storeMessage(
        messageRequest(['content' => 'hi', 'user_id' => '7'], null),
        $conversations,
    );

    expect($response->getStatusCode())->toBe(401)
        ->and(Conversation::query()->count())->toBe(0)
        ->and(Message::query()->count())->toBe(0);
});

it('storeMessage returns 404 and appends no message for an int-id user on another user\'s conversation', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $owned = $conversations->createUserMessage('mine', userId: '7');
    $messagesBefore = Message::query()->count();

    $response = (new ChatController)->storeMessage(
        messageRequest(['content' => 'as you', 'conversation_ulid' => $owned['conversation_ulid']], new ChatControllerAuthUser(8)),
        $conversations,
    );

    expect($response->getStatusCode())->toBe(404)
        ->and(Message::query()->count())->toBe($messagesBefore);

    $ownerReply = (new ChatController)->storeMessage(
        messageRequest(['content' => 'still me', 'conversation_ulid' => $owned['conversation_ulid']], new ChatControllerAuthUser(7)),
        $conversations,
    );
    expect($ownerReply->getStatusCode())->toBe(201)
        ->and($ownerReply->getData(true)['conversation_ulid'])->toBe($owned['conversation_ulid']);
});

function seedHttpProposal(?string $ownerId): Proposal
{
    $conversations = new ConversationService(static fn ($j) => null, new ArrayProgressStore);
    $ids = $conversations->createUserMessage('p', userId: $ownerId);
    $turn = Turn::query()->where('ulid', $ids['turn_ulid'])->firstOrFail();

    return Proposal::query()->create([
        'turn_id' => $turn->id,
        'conversation_id' => $turn->conversation_id,
        'ulid' => 'PROP'.strtoupper(bin2hex(random_bytes(8))),
        'type' => 'action',
        'payload' => [],
        'target_capability' => 'demo.cap',
        'status' => Proposal::STATUS_PENDING,
    ]);
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
    bootHttpSqlite();
    $owner = ChatControllerTestUser::query()->create(['name' => 'owner']);
    $proposal = seedHttpProposal((string) $owner->id);
    $bus = countingBus();
    $controller = new ChatController;

    expect($controller->acceptProposal(httpRequestAs(null), $proposal->ulid, httpProposalService($bus))->getStatusCode())->toBe(401)
        ->and($controller->rejectProposal(httpRequestAs(null), $proposal->ulid, httpProposalService($bus))->getStatusCode())->toBe(401)
        ->and($bus->invokes)->toBe(0)
        ->and($proposal->fresh()->status)->toBe(Proposal::STATUS_PENDING);
});

it('acceptProposal and rejectProposal return 404 for another user\'s proposal without invoking the bus or changing status', function () {
    bootHttpSqlite();
    $owner = ChatControllerTestUser::query()->create(['name' => 'owner']);
    $intruder = ChatControllerTestUser::query()->create(['name' => 'intruder']);
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
        ->and($proposal->fresh()->status)->toBe(Proposal::STATUS_PENDING);
});

it('acceptProposal and rejectProposal return 404 for an ownerless conversation\'s proposal', function () {
    bootHttpSqlite();
    $user = ChatControllerTestUser::query()->create(['name' => 'someone']);
    $proposal = seedHttpProposal(null);
    $bus = countingBus();
    $controller = new ChatController;
    $asUser = httpRequestAs((string) $user->id);

    expect($controller->acceptProposal($asUser, $proposal->ulid, httpProposalService($bus))->getStatusCode())->toBe(404)
        ->and($controller->rejectProposal($asUser, $proposal->ulid, httpProposalService($bus))->getStatusCode())->toBe(404)
        ->and($bus->invokes)->toBe(0)
        ->and($proposal->fresh()->status)->toBe(Proposal::STATUS_PENDING);
});

it('rejectProposal lets the owner reject, maps missing to 404 and non-pending to 409', function () {
    bootHttpSqlite();
    $owner = ChatControllerTestUser::query()->create(['name' => 'owner']);
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
    $accepted->save();
    expect($controller->rejectProposal($asOwner, $accepted->ulid, httpProposalService($bus))->getStatusCode())->toBe(409);
});

it('acceptProposal and rejectProposal map a proposal deleted after the owner check to 404', function () {
    bootHttpSqlite();
    $owner = ChatControllerTestUser::query()->create(['name' => 'owner']);
    $controller = new ChatController;
    $asOwner = httpRequestAs((string) $owner->id);
    // Delete the row right after the ownership probe so the service lookup races a host delete.
    Proposal::getConnectionResolver()->connection()->listen(static function ($query): void {
        if (str_starts_with($query->sql, 'select exists')) {
            Proposal::query()->delete();
        }
    });

    expect($controller->acceptProposal($asOwner, seedHttpProposal((string) $owner->id)->ulid, httpProposalService(countingBus()))->getStatusCode())->toBe(404)
        ->and($controller->rejectProposal($asOwner, seedHttpProposal((string) $owner->id)->ulid, httpProposalService(countingBus()))->getStatusCode())->toBe(404);
});
