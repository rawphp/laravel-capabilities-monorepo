<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Rawphp\Capabilities\Contracts\ConversationIdentity;
use Rawphp\Capabilities\Contracts\Metrics;
use Rawphp\Capabilities\Observability\InMemoryMetrics;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\MessagingServiceProvider;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('happy: forged bind attempt increments bind-denied counter with reason forged_bind [MSG-002][D-019]', function () {
    $metrics = new InMemoryMetrics;
    $id = new IdentityLinker(H::config(), metrics: $metrics);

    expect(fn () => $id->rejectForgedBind(['laravel_user_id' => '1', 'telegram_user_id' => '2']))
        ->toThrow(RuntimeException::class);

    expect($metrics->get(IdentityLinker::METRIC_BIND_DENIED, ['reason' => 'forged_bind']))->toBe(1)
        ->and($metrics->emissions())->toHaveCount(1);
});

it('happy: cross-tenant resolve increments bind-denied counter with reason tenant_mismatch [MSG-002][D-019]', function () {
    $metrics = new InMemoryMetrics;
    $id = new IdentityLinker(H::config(), metrics: $metrics);
    $id->link('tg-1', 'u1', 'tenant-a');

    expect($id->resolve(['telegram_user_id' => 'tg-1', 'expected_tenant_id' => 'tenant-other']))->toBeNull();

    expect($metrics->get(IdentityLinker::METRIC_BIND_DENIED, ['reason' => 'tenant_mismatch']))->toBe(1)
        ->and($metrics->emissions())->toHaveCount(1);
});

it('edge: same-tenant resolve and unlinked user emit no deny metric [MSG-002][D-019]', function () {
    $metrics = new InMemoryMetrics;
    $id = new IdentityLinker(H::config(), metrics: $metrics);
    $id->link('tg-1', 'u1', 'tenant-a');

    expect($id->resolve(['telegram_user_id' => 'tg-1', 'expected_tenant_id' => 'tenant-a']))->not->toBeNull()
        ->and($id->resolve(['telegram_user_id' => 'unknown']))->toBeNull();

    expect($metrics->emissions())->toBe([]);
});

it('edge: denials still fail closed when no Metrics is injected [MSG-002][D-019]', function () {
    $id = new IdentityLinker(H::config());
    $id->link('tg-1', 'u1', 'tenant-a');

    expect($id->resolve(['telegram_user_id' => 'tg-1', 'expected_tenant_id' => 'tenant-other']))->toBeNull()
        ->and(fn () => $id->rejectForgedBind([]))->toThrow(RuntimeException::class);
});

/**
 * Minimal DB-free container: config reported as cached (as after `config:cache`), so register()
 * skips mergeConfigFrom and the env()-driven config file.
 */
function identityMetricsContainer(): Container
{
    $app = new class extends Container implements CachesConfiguration
    {
        public function configurationIsCached(): bool
        {
            return true;
        }

        public function getCachedConfigPath(): string
        {
            return '';
        }

        public function getCachedServicesPath(): string
        {
            return '';
        }
    };
    $app->instance('config', new class
    {
        public function get(string $key, mixed $default = null): mixed
        {
            return $default;
        }
    });
    (new MessagingServiceProvider($app))->register();

    return $app;
}

it('happy: provider injects bound core Metrics into IdentityLinker [D-019]', function () {
    $app = identityMetricsContainer();
    $metrics = new InMemoryMetrics;
    $app->instance(Metrics::class, $metrics);

    expect(fn () => $app->make(IdentityLinker::class)->rejectForgedBind([]))->toThrow(RuntimeException::class);

    expect($metrics->get(IdentityLinker::METRIC_BIND_DENIED, ['reason' => 'forged_bind']))->toBe(1);
});

it('edge: provider builds IdentityLinker without Metrics when none is bound [D-019]', function () {
    $app = identityMetricsContainer();

    expect($app->make(IdentityLinker::class))->toBeInstanceOf(IdentityLinker::class)
        ->and($app->make(ConversationIdentity::class))->toBe($app->make(IdentityLinker::class));
});
