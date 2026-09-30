<?php

// L-015 / D-017: a cached class map (bootstrap/cache/capabilities.php) replaces the per-boot
// directory walk + tokenize; `capabilities:cache` / `capabilities:clear` manage it. It is a cache
// of the attribute scan, not a third discovery path.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Artisan\ArtisanCommandRegistrar;
use Rawphp\Capabilities\Adapters\Artisan\CacheCapabilitiesCommand;
use Rawphp\Capabilities\Adapters\Artisan\ClearCapabilitiesCommand;
use Rawphp\Capabilities\Discovery\AttributeDiscoverer;
use Rawphp\Capabilities\Discovery\CapabilityDiscoveryBoot;
use Rawphp\Capabilities\Discovery\DiscoveryManifest;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Tests\Fixtures\ArtisanCommandHarness;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;
use Rawphp\Capabilities\Tests\Fixtures\Capabilities\AttributedCreateInvoice;
use Rawphp\Capabilities\Tests\Fixtures\Capabilities\AttributedCreateInvoiceWithCli;
use Rawphp\Capabilities\Tests\Fixtures\Capabilities\AttributedListCustomers;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;
use Rawphp\Capabilities\Tests\Fixtures\FakeProviderApp;
use Rawphp\Capabilities\Tests\Fixtures\Outside\OutsideCapability;

function l015FixturesPath(): string
{
    return dirname(__DIR__, 2).'/Fixtures/Capabilities';
}

function l015ManifestPath(): string
{
    $dir = sys_get_temp_dir().'/capabilities-manifest-'.uniqid('', true);

    return $dir.'/cache/capabilities.php';
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/capabilities-manifest-*/cache/capabilities.php') ?: [] as $file) {
        @unlink($file);
        @rmdir(dirname($file));
        @rmdir(dirname($file, 2));
    }
});

it('builds the class map from the attribute scan of the discovery paths', function () {
    $classes = DiscoveryManifest::build([l015FixturesPath()]);

    expect($classes)->toBe([AttributedCreateInvoice::class, AttributedCreateInvoiceWithCli::class, AttributedListCustomers::class])
        ->and(DiscoveryManifest::build([sys_get_temp_dir().'/capabilities-nowhere-'.uniqid('', true)]))->toBe([]);
});

it('writes, loads and clears the manifest; a missing manifest loads as null', function () {
    $path = l015ManifestPath();

    expect(DiscoveryManifest::load($path))->toBeNull()
        ->and(DiscoveryManifest::load(null))->toBeNull();

    DiscoveryManifest::write($path, [OutsideCapability::class, AttributedListCustomers::class]);

    expect(is_file($path))->toBeTrue()
        ->and(DiscoveryManifest::load($path))->toBe([OutsideCapability::class, AttributedListCustomers::class])
        ->and(DiscoveryManifest::clear($path))->toBeTrue()
        ->and(DiscoveryManifest::load($path))->toBeNull()
        ->and(DiscoveryManifest::clear($path))->toBeFalse();
});

it('boot uses the manifest class map and never walks the discovery path when one exists', function () {
    $path = l015ManifestPath();
    DiscoveryManifest::write($path, [OutsideCapability::class]);
    $registry = DiscoveryHelpers::registry();

    $names = CapabilityDiscoveryBoot::run($registry, ['path' => l015FixturesPath()], $path);

    expect($names)->toBe(['outside-cap'])
        ->and($registry->has('outside-cap'))->toBeTrue()
        // Under the configured path but not in the manifest: the scan did not run.
        ->and($registry->has('create-invoice'))->toBeFalse();
});

it('an empty manifest registers nothing; no manifest falls back to scanning', function () {
    $path = l015ManifestPath();
    DiscoveryManifest::write($path, []);

    $cached = DiscoveryHelpers::registry();
    $scanned = DiscoveryHelpers::registry();

    expect(CapabilityDiscoveryBoot::run($cached, ['path' => l015FixturesPath()], $path))->toBe([])
        ->and(CapabilityDiscoveryBoot::run($scanned, ['path' => l015FixturesPath()], null))->toContain('create-invoice');
});

it('capabilities:cache writes the manifest from config path and capabilities:clear removes it', function () {
    $path = l015ManifestPath();
    $config = ['capabilities' => ['path' => l015FixturesPath()]];

    $cached = ArtisanCommandHarness::run(new CacheCapabilitiesCommand($path), [], [], $config);
    $loaded = DiscoveryManifest::load($path);
    $cleared = ArtisanCommandHarness::run(new ClearCapabilitiesCommand($path), [], [], $config);
    $again = ArtisanCommandHarness::run(new ClearCapabilitiesCommand($path), [], [], $config);

    expect($cached['exit'])->toBe(0)
        ->and($cached['output'])->toContain('Cached 3 capability classes')
        ->and($loaded)->toBe(DiscoveryManifest::build([l015FixturesPath()]))
        ->and($cleared['exit'])->toBe(0)
        ->and($cleared['output'])->toContain('cleared')
        ->and(is_file($path))->toBeFalse()
        ->and($again['output'])->toContain('No capability cache');
});

it('capabilities:cache fails closed without a bootstrap path; clear is a no-op', function () {
    $cache = ArtisanCommandHarness::run(new CacheCapabilitiesCommand, [], [], ['capabilities' => []]);
    $clear = ArtisanCommandHarness::run(new ClearCapabilitiesCommand, [], [], ['capabilities' => []]);

    expect($cache['exit'])->toBe(1)
        ->and($cache['output'])->toContain('bootstrap/cache')
        ->and($clear['exit'])->toBe(0);
});

it('the cache pair is infrastructure: registered even with the artisan invoke surface disabled', function () {
    expect(ArtisanCommandRegistrar::infrastructure([]))->toContain(CacheCapabilitiesCommand::class, ClearCapabilitiesCommand::class)
        ->and(ArtisanCommandRegistrar::all(['enabled' => false], ['execution' => 'atomic']))->toBe([CacheCapabilitiesCommand::class, ClearCapabilitiesCommand::class]);

    $app = FakeProviderApp::registered(BootHelpers::config([
        'surfaces' => ['artisan' => ['enabled' => false]],
        'approval' => ['store' => 'memory', 'execution' => 'atomic'],
        'idempotency' => ['driver' => 'memory'],
        'audit' => ['driver' => 'memory'],
    ]));

    expect($app->provider->bootArtisanCommands())->toBe([CacheCapabilitiesCommand::class, ClearCapabilitiesCommand::class]);
});

it('provider boot discovery reads the manifest under the app bootstrap path', function () {
    $path = l015ManifestPath();
    DiscoveryManifest::write($path, [OutsideCapability::class]);

    $app = FakeProviderApp::registered(BootHelpers::config([
        'path' => l015FixturesPath(),
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
        'audit' => ['driver' => 'memory'],
    ]));
    $app->bootstrapDir = dirname($path, 2);

    $names = $app->provider->bootCapabilityDiscovery();
    /** @var CapabilityRegistry $registry */
    $registry = $app->make(CapabilityRegistry::class);

    expect(DiscoveryManifest::pathFor($app))->toBe($path)
        ->and($names)->toBe(['outside-cap'])
        ->and($registry->has('create-invoice'))->toBeFalse();
});

it('a manifest is only a cache of the scan: the same classes come out either way', function () {
    $fromScan = array_map(fn ($d) => $d->handlerClass, (new AttributeDiscoverer)->fromPaths([l015FixturesPath()]));
    sort($fromScan);

    expect(DiscoveryManifest::build([l015FixturesPath()]))->toBe($fromScan);
});
