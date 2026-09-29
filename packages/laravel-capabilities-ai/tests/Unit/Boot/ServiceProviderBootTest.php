<?php

declare(strict_types=1);

/**
 * Provider boot(): optional routes and console-only publish tags + reap command.
 * Minimal container + real Router; no HTTP kernel, no database.
 */

use Illuminate\Console\Application as Artisan;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;
use Rawphp\CapabilitiesAi\CapabilitiesAiServiceProvider;
use Rawphp\CapabilitiesAi\Http\ChatController;

// Foundation path helpers are host-provided (laravel/framework); stub them for console boot.
if (! function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return '/host/config/'.$path;
    }
}
if (! function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        return '/host/database/'.$path;
    }
}

/**
 * @param  array<string, mixed>  $aiOverrides
 * @return array{0: Container, 1: Router}
 */
function bootAiProviderWithRouter(array $aiOverrides = [], bool $console = false): array
{
    $app = new class($console) extends Container
    {
        public function __construct(private bool $console) {}

        public function runningInConsole(): bool
        {
            return $this->console;
        }
    };

    $base = require dirname(__DIR__, 3).'/config/capabilities-ai.php';
    $app->instance('config', new class(['capabilities-ai' => array_replace_recursive($base, $aiOverrides)])
    {
        /** @param  array<string, mixed>  $items */
        public function __construct(private array $items) {}

        public function get(string $key, mixed $default = null): mixed
        {
            $cur = $this->items;
            foreach (explode('.', $key) as $p) {
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
    });

    $router = new Router(new Dispatcher($app), $app);
    $app->instance('router', $router);
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);

    (new CapabilitiesAiServiceProvider($app))->boot();
    $router->getRoutes()->refreshNameLookups();

    return [$app, $router];
}

afterEach(function () {
    Facade::clearResolvedInstances();
    Artisan::forgetBootstrappers();
});

it('registers no routes when routes.enabled is false (package default)', function () {
    [, $router] = bootAiProviderWithRouter();

    expect($router->getRoutes()->count())->toBe(0);
});

it('registers chat routes under the configured prefix and middleware when enabled', function () {
    [, $router] = bootAiProviderWithRouter([
        'routes' => ['enabled' => true, 'prefix' => 'api/chat', 'middleware' => ['api', 'auth:host']],
    ]);

    $store = $router->getRoutes()->getByName('capabilities-ai.messages.store');

    expect($store)->not->toBeNull()
        ->and($store->uri())->toBe('api/chat/messages')
        ->and($store->methods())->toContain('POST')
        ->and($store->middleware())->toBe(['api', 'auth:host'])
        ->and($store->getActionName())->toBe(ChatController::class.'@storeMessage')
        ->and($router->getRoutes()->getByName('capabilities-ai.turns.events')?->uri())->toBe('api/chat/turns/{turnUlid}/events')
        ->and($router->getRoutes()->getByName('capabilities-ai.proposals.accept')?->uri())->toBe('api/chat/proposals/{proposalUlid}/accept');
});

it('omits proposal routes when proposals.enabled is false (D-024)', function () {
    [, $router] = bootAiProviderWithRouter([
        'routes' => ['enabled' => true],
        'proposals' => ['enabled' => false],
    ]);

    expect($router->getRoutes()->getByName('capabilities-ai.messages.store')?->uri())->toBe('capabilities-ai/chat/messages')
        ->and($router->getRoutes()->getByName('capabilities-ai.proposals.accept'))->toBeNull()
        ->and($router->getRoutes()->getByName('capabilities-ai.proposals.reject'))->toBeNull();
});

it('publishes config and migrations tags and registers the reap command in console', function () {
    [$app] = bootAiProviderWithRouter(console: true);

    $config = ServiceProvider::pathsToPublish(CapabilitiesAiServiceProvider::class, 'capabilities-ai-config');
    $migrations = ServiceProvider::pathsToPublish(CapabilitiesAiServiceProvider::class, 'capabilities-ai-migrations');
    $packageRoot = realpath(dirname(__DIR__, 3));
    $artisan = new Artisan($app, new Dispatcher($app), 'test');

    expect(array_values($config))->toBe(['/host/config/capabilities-ai.php'])
        ->and(realpath((string) array_key_first($config)))->toBe($packageRoot.'/config/capabilities-ai.php')
        ->and(array_values($migrations))->toBe(['/host/database/migrations'])
        ->and(realpath((string) array_key_first($migrations)))->toBe($packageRoot.'/database/migrations')
        ->and($artisan->has('capabilities-ai:reap-stale-turns'))->toBeTrue();
});

it('registers no console command outside the console', function () {
    [$app] = bootAiProviderWithRouter(console: false);

    expect((new Artisan($app, new Dispatcher($app), 'test'))->has('capabilities-ai:reap-stale-turns'))->toBeFalse();
});
