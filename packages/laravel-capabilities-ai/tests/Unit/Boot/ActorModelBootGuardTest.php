<?php

declare(strict_types=1);

/**
 * Fail-closed boot: the conversation actor model (capabilities-ai.user_model →
 * auth.providers.users.model) is validated when the provider boots with a bound
 * CapabilityBus, not first on a user's turn.
 */

use Illuminate\Container\Container;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Schema\CatalogPresenter;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesAi\CapabilitiesAiServiceProvider;

final class ActorModelBootGuardQueryableUser
{
    public static function query(): object
    {
        throw new RuntimeException('boot guard must not query the user table');
    }
}

final class ActorModelBootGuardPlainClass {}

/**
 * @param  array<string, mixed>  $items
 */
function ambgConfigRepo(array $items): object
{
    return new class($items)
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
    };
}

/**
 * @param  array<string, mixed>  $items  full config (capabilities-ai + auth)
 */
function ambgBoot(array $items, bool $withBus = true): void
{
    $app = new class extends Container
    {
        public function runningInConsole(): bool
        {
            return false;
        }
    };

    $base = require dirname(__DIR__, 3).'/config/capabilities-ai.php';
    $items['capabilities-ai'] = array_replace_recursive($base, $items['capabilities-ai'] ?? []);
    $app->instance('config', ambgConfigRepo($items));

    if ($withBus) {
        $app->instance(CapabilityBus::class, new class implements CapabilityBus
        {
            public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
            {
                return CapabilityResult::ok();
            }

            public function catalog(): CatalogPresenter
            {
                throw new RuntimeException('unused');
            }
        });
    }

    (new CapabilitiesAiServiceProvider($app))->boot();
}

it('boots when capabilities-ai.user_model is a queryable class (no query run)', function () {
    expect(fn () => ambgBoot(['capabilities-ai' => ['user_model' => ActorModelBootGuardQueryableUser::class]]))
        ->not->toThrow(Throwable::class);
});

it('boots when auth.providers.users.model is the queryable fallback', function () {
    expect(fn () => ambgBoot(['auth' => ['providers' => ['users' => ['model' => ActorModelBootGuardQueryableUser::class]]]]))
        ->not->toThrow(Throwable::class);
});

it('capabilities-ai.user_model wins over auth.providers.users.model at boot', function () {
    expect(fn () => ambgBoot([
        'capabilities-ai' => ['user_model' => 'App\\Models\\MissingActor'],
        'auth' => ['providers' => ['users' => ['model' => ActorModelBootGuardQueryableUser::class]]],
    ]))->toThrow(RuntimeException::class, 'App\\Models\\MissingActor');
});

it('fails boot when no user model is configured and the bus is bound', function () {
    expect(fn () => ambgBoot([]))
        ->toThrow(RuntimeException::class, 'No user model configured');
});

it('fails boot when the configured user model class does not exist', function () {
    expect(fn () => ambgBoot(['capabilities-ai' => ['user_model' => 'App\\Models\\Nope']]))
        ->toThrow(RuntimeException::class, 'does not exist');
});

it('fails boot when the configured user model is not queryable', function () {
    expect(fn () => ambgBoot(['capabilities-ai' => ['user_model' => ActorModelBootGuardPlainClass::class]]))
        ->toThrow(RuntimeException::class, 'is not queryable');
});

it('skips the actor model check when no CapabilityBus is bound (no bus invokes possible)', function () {
    expect(fn () => ambgBoot(['capabilities-ai' => ['user_model' => 'App\\Models\\Nope']], withBus: false))
        ->not->toThrow(Throwable::class);
});
