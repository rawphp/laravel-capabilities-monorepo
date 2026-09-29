<?php

// SURF-004 / D-007 / D-021: provider boot() enforces surface config invariants.
// Unit-only: bare Illuminate container + in-memory config, no router, no database.

declare(strict_types=1);

use Illuminate\Container\Container;
use Rawphp\Capabilities\Boot\BootException;
use Rawphp\Capabilities\Boot\BootGuard;
use Rawphp\Capabilities\CapabilitiesServiceProvider;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

/**
 * @param  array<string, bool>  $surfaces  surface => enabled
 */
function pbgBoot(array $surfaces, bool $messagingInstalled = false): void
{
    $config = BootHelpers::config(['surfaces' => BootHelpers::surfaces($surfaces)]);

    $app = new class extends Container
    {
        public function runningInConsole(): bool
        {
            return false;
        }
    };
    $app->instance('config', new class($config)
    {
        /** @param  array<string, mixed>  $config */
        public function __construct(private array $config) {}

        public function get(string $key, mixed $default = null): mixed
        {
            return $key === 'capabilities' ? $this->config : $default;
        }
    });

    $provider = new class($app, $messagingInstalled) extends CapabilitiesServiceProvider
    {
        public function __construct(Container $app, private bool $messagingInstalled)
        {
            parent::__construct($app);
        }

        protected function messagingPackageInstalled(): bool
        {
            return $this->messagingInstalled;
        }
    };

    $provider->boot();
}

it('fail: provider boot rejects cli enabled while http disabled [SURF-004]', function () {
    expect(fn () => pbgBoot(['cli' => true, 'http' => false]))
        ->toThrow(BootException::class, 'requires surface "http"');
});

it('fail: provider boot rejects messaging enabled without the messaging package [D-007]', function () {
    expect(fn () => pbgBoot(['messaging' => true, 'agent' => true], messagingInstalled: false))
        ->toThrow(BootException::class, 'rawphp/laravel-capabilities-messaging is not installed');
});

it('fail: provider boot rejects messaging enabled without agent [SURF-004]', function () {
    expect(fn () => pbgBoot(['messaging' => true, 'agent' => false], messagingInstalled: true))
        ->toThrow(BootException::class, 'requires surface "agent"');
});

it('happy: provider boot accepts consistent surfaces without probing peers [ORI-801]', function () {
    // Defaults keep agent + mcp on with laravel/ai + laravel/mcp absent: peer checks stay
    // with each surface's registrar, so boot does not fail on missing peers here.
    expect(fn () => pbgBoot(['cli' => false, 'http' => false]))->not->toThrow(Throwable::class)
        ->and(fn () => pbgBoot(['messaging' => true, 'agent' => true], messagingInstalled: true))
        ->not->toThrow(Throwable::class);
});

it('happy: assertSurfaceRules passes default surfaces and rejects cli without http [SURF-004]', function () {
    expect(fn () => (new BootGuard(config: BootHelpers::config()))->assertSurfaceRules())
        ->not->toThrow(Throwable::class);

    $bad = BootHelpers::config(['surfaces' => BootHelpers::surfaces(['cli' => true, 'http' => false])]);
    expect(fn () => (new BootGuard(config: $bad))->assertSurfaceRules())
        ->toThrow(BootException::class);
});

it('edge: CAPABILITIES_SKIP_BOOT_CHECKS does not bypass surface invariants outside production [D-021]', function () {
    // D-021: the skip flag covers deferred-style checks only; SURF-004 is not deferred.
    $bad = BootHelpers::config(['surfaces' => BootHelpers::surfaces(['cli' => true, 'http' => false])]);
    $guard = new BootGuard(config: $bad, appEnv: 'testing', skipBootChecks: true);

    expect($guard->shouldSkipDeferredChecks())->toBeTrue()
        ->and(fn () => $guard->assertSurfaceRules())->toThrow(BootException::class);
});
