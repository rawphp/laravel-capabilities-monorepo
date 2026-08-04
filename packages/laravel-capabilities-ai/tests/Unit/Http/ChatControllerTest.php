<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Schema\CatalogPresenter;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\ProposalService;
use Rawphp\CapabilitiesAi\Domain\TurnService;
use Rawphp\CapabilitiesAi\Http\ChatController;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\AlwaysReadyIdempotency;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;

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

    return new ArrayProgressStore;
}

it('history returns 200 with messages', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('hello');
    $response = (new ChatController)->history($ids['conversation_ulid'], $conversations);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['messages'][0]['content'])->toBe('hello');
});

it('history returns 404 when missing', function () {
    bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, new ArrayProgressStore);
    $response = (new ChatController)->history('01MISSINGCONV00000000000', $conversations);
    expect($response->getStatusCode())->toBe(404);
});

it('showTurn and cancelTurn happy path', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('t');
    $turns = new TurnService($progress);

    $show = (new ChatController)->showTurn($ids['turn_ulid'], $turns);
    expect($show->getStatusCode())->toBe(200)
        ->and($show->getData(true)['status'])->toBe(Turn::STATUS_QUEUED);

    $cancel = (new ChatController)->cancelTurn($ids['turn_ulid'], $turns);
    expect($cancel->getStatusCode())->toBe(200)
        ->and($cancel->getData(true)['status'])->toBe(Turn::STATUS_CANCELLED);
});

it('cancelTurn returns 409 on illegal transition', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('done');
    Turn::query()->where('ulid', $ids['turn_ulid'])->update(['status' => Turn::STATUS_COMPLETED]);
    $response = (new ChatController)->cancelTurn($ids['turn_ulid'], new TurnService($progress));
    expect($response->getStatusCode())->toBe(409);
});

it('turnEvents passes cursor and returns events', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('e');
    $progress->append($ids['turn_ulid'], ['kind' => 'token', 'data' => ['t' => 1]]);
    $request = Request::create('/events', 'GET', ['cursor' => 0]);
    $response = (new ChatController)->turnEvents($request, $ids['turn_ulid'], new TurnService($progress));
    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['events'])->not->toBeEmpty();
});

it('destroyConversation 409 when active turns and 200 when closed', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('active');
    $controller = new ChatController;

    expect($controller->destroyConversation($ids['conversation_ulid'], $conversations)->getStatusCode())
        ->toBe(409);

    Turn::query()->where('ulid', $ids['turn_ulid'])->update(['status' => Turn::STATUS_COMPLETED]);
    $ok = $controller->destroyConversation($ids['conversation_ulid'], $conversations);
    expect($ok->getStatusCode())->toBe(200)
        ->and($ok->getData(true)['status'])->toBe('closed')
        ->and($ok->getData(true)['closed'] ?? null)->toBeTrue();
});

it('controller source delegates without Eloquent creates', function () {
    $src = file_get_contents(dirname(__DIR__, 3).'/src/Http/ChatController.php') ?: '';
    expect($src)->toContain('TurnService')
        ->and($src)->toContain('ConversationService')
        ->and($src)->not->toContain('::query()->create')
        ->and($src)->not->toContain('::query()->update')
        ->and($src)->not->toContain('::query()->where');
});

it('acceptProposal maps accepted / approval / retry / failed / refuse without uncaught exceptions', function () {
    $progress = bootHttpSqlite();
    $conversations = new ConversationService(static fn ($j) => null, $progress);
    $ids = $conversations->createUserMessage('p');
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
    $ok = $controller->acceptProposal(
        $makeProposal('ok')->ulid,
        new ProposalService($busOk, new AlwaysReadyIdempotency),
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
        $makeProposal('apr')->ulid,
        new ProposalService($busApr, new AlwaysReadyIdempotency),
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
        $makeProposal('rty')->ulid,
        new ProposalService($busRetry, new AlwaysReadyIdempotency),
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
        $makeProposal('fai')->ulid,
        new ProposalService($busFail, new AlwaysReadyIdempotency),
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
        $makeProposal('ref')->ulid,
        new ProposalService($busRefuse, new AlwaysReadyIdempotency),
    );
    expect($refuse->getStatusCode())->toBe(403)
        ->and($refuse->getData(true)['outcome'])->toBe('refuse');

    $rejected = $makeProposal('rjd');
    $rejected->status = Proposal::STATUS_REJECTED;
    $rejected->save();
    $rej = $controller->acceptProposal(
        $rejected->ulid,
        new ProposalService($busOk, new AlwaysReadyIdempotency),
    );
    expect($rej->getStatusCode())->toBe(409)
        ->and($rej->getData(true)['outcome'])->toBe('refuse');

    $missing = $controller->acceptProposal(
        'PROPDOESNOTEXIST0001',
        new ProposalService($busOk, new AlwaysReadyIdempotency),
    );
    expect($missing->getStatusCode())->toBe(404)
        ->and($missing->getData(true)['message'])->toBe('Proposal not found');
});
