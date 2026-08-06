<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\CapabilitiesAiServiceProvider;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Jobs\RunTurnJob;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;

/**
 * Minimal config repo (same shape as ServiceProviderBindingsTest).
 *
 * @param  array<string, mixed>  $items
 */
function dqConfigRepo(array $items): object
{
    return new class($items)
    {
        /** @param  array<string, mixed>  $items */
        public function __construct(private array $items) {}

        public function get(string $key, mixed $default = null): mixed
        {
            $parts = explode('.', $key);
            $cur = $this->items;
            foreach ($parts as $p) {
                if (! is_array($cur) || ! array_key_exists($p, $cur)) {
                    return $default;
                }
                $cur = $cur[$p];
            }

            return $cur;
        }

        public function set(string $key, mixed $value): void
        {
            $this->items[$key] = $value;
        }
    };
}

/**
 * @param  array<string, mixed>  $aiOverrides
 * @return array{0: Container, 1: object}
 */
function bootDispatchQueueContainer(array $aiOverrides = []): array
{
    $app = new class extends Container
    {
        public function runningInConsole(): bool
        {
            return true;
        }
    };

    $base = require dirname(__DIR__, 3).'/config/capabilities-ai.php';
    $config = array_replace_recursive($base, $aiOverrides);
    $app->instance('config', dqConfigRepo(['capabilities-ai' => $config]));

    $bag = new class
    {
        /** @var list<object> */
        public array $jobs = [];
    };

    $app->instance('Illuminate\Contracts\Bus\Dispatcher', new class($bag)
    {
        public function __construct(private object $bag) {}

        public function dispatch(object $job): mixed
        {
            $this->bag->jobs[] = $job;

            return null;
        }
    });

    // Host-prebound ProgressStore so singleton factory is not required (cleaner harness).
    $app->instance(ProgressStore::class, new ArrayProgressStore);

    (new CapabilitiesAiServiceProvider($app))->register();

    return [$app, $bag];
}

function bootDispatchQueueSqlite(Container $app): void
{
    $capsule = new Capsule;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $capsule->setEventDispatcher(new EventDispatcher(new Container));
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    $app->instance('db', $capsule->getDatabaseManager());
    Facade::setFacadeApplication($app);
    Schema::swap($capsule->getConnection()->getSchemaBuilder());
    $dir = dirname(__DIR__, 3).'/database/migrations';
    $files = glob($dir.'/*.php') ?: [];
    sort($files);
    foreach ($files as $file) {
        (require $file)->up();
    }
}

it('default dispatch sets RunTurnJob queue and connection from config', function () {
    [$app, $bag] = bootDispatchQueueContainer([
        'queue' => [
            'name' => 'capabilities-ai',
            'connection' => 'redis',
        ],
    ]);

    bootDispatchQueueSqlite($app);

    $svc = $app->make(ConversationService::class);
    $svc->createUserMessage('queued');

    expect($bag->jobs)->toHaveCount(1)
        ->and($bag->jobs[0])->toBeInstanceOf(RunTurnJob::class)
        ->and($bag->jobs[0]->queue ?? null)->toBe('capabilities-ai')
        ->and($bag->jobs[0]->connection ?? null)->toBe('redis');
});

it('default dispatch leaves queue/connection null when config empty', function () {
    [$app, $bag] = bootDispatchQueueContainer([
        'queue' => [
            'name' => null,
            'connection' => null,
        ],
    ]);

    bootDispatchQueueSqlite($app);

    $svc = $app->make(ConversationService::class);
    $svc->createUserMessage('no queue');

    expect($bag->jobs)->toHaveCount(1)
        ->and($bag->jobs[0]->queue ?? null)->toBeNull()
        ->and($bag->jobs[0]->connection ?? null)->toBeNull();
});
