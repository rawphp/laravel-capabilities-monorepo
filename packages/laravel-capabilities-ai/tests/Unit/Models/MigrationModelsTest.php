<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Builder;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Models\Conversation;
use Rawphp\CapabilitiesAi\Models\Message;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Models\TableNames;
use Rawphp\CapabilitiesAi\Models\Turn;

/**
 * Boot Eloquent + Schema facades against sqlite :memory: for package migrations.
 */
function bootAiSqlite(): Capsule
{
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setEventDispatcher(new Dispatcher(new Container));
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    $app = new Container;
    $app->instance('db', $capsule->getDatabaseManager());
    Facade::setFacadeApplication($app);
    $app->singleton('db.schema', fn () => $capsule->getConnection()->getSchemaBuilder());
    // Schema facade resolves 'db.schema' or via db connection
    Schema::swap($capsule->getConnection()->getSchemaBuilder());

    return $capsule;
}

function runAiMigrations(): void
{
    $dir = dirname(__DIR__, 3).'/database/migrations';
    $files = glob($dir.'/*.php') ?: [];
    sort($files);
    foreach ($files as $file) {
        $migration = require $file;
        $migration->up();
    }
}

it('migrates four package tables on sqlite', function () {
    bootAiSqlite();
    runAiMigrations();

    $schema = Capsule::connection()->getSchemaBuilder();
    expect($schema->hasTable(TableNames::conversations()))->toBeTrue()
        ->and($schema->hasTable(TableNames::messages()))->toBeTrue()
        ->and($schema->hasTable(TableNames::turns()))->toBeTrue()
        ->and($schema->hasTable(TableNames::proposals()))->toBeTrue();
});

it('creates conversation and message by ulid', function () {
    bootAiSqlite();
    runAiMigrations();

    $conversationUlid = bin2hex(random_bytes(13));
    $messageUlid = bin2hex(random_bytes(13));

    $conversation = Conversation::query()->create([
        'ulid' => $conversationUlid,
        'status' => 'open',
        'meta' => ['source' => 'test'],
    ]);

    $message = Message::query()->create([
        'conversation_id' => $conversation->id,
        'ulid' => $messageUlid,
        'role' => 'user',
        'content' => 'hello',
        'meta' => null,
    ]);

    expect($conversation->ulid)->toBe($conversationUlid)
        ->and(Conversation::query()->where('ulid', $conversationUlid)->exists())->toBeTrue()
        ->and($message->ulid)->toBe($messageUlid)
        ->and(Message::query()->where('ulid', $messageUlid)->value('content'))->toBe('hello');
});

it('defines locked turn and proposal status constants', function () {
    expect(Turn::STATUSES)->toBe([
        'queued', 'running', 'completed', 'failed', 'cancelled',
    ]);
    expect(Proposal::STATUSES)->toBe([
        'pending', 'accepted', 'rejected', 'expired',
    ]);
});

it('models use prefix-aware package tables only', function () {
    expect(TableNames::conversations())->toStartWith('capabilities_ai_')
        ->and((new Conversation)->getTable())->toBe(TableNames::conversations())
        ->and((new Message)->getTable())->toBe(TableNames::messages())
        ->and((new Turn)->getTable())->toBe(TableNames::turns())
        ->and((new Proposal)->getTable())->toBe(TableNames::proposals());
});
