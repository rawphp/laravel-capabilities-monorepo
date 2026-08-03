<?php

// REQ-024: Register Artisan ops commands. Unit-only.

declare(strict_types=1);

use Illuminate\Console\Command;
use Rawphp\Capabilities\Adapters\Artisan\ArtisanCapabilityInvoker;
use Rawphp\Capabilities\Adapters\Artisan\ArtisanCommandRegistrar;
use Rawphp\Capabilities\Adapters\Artisan\ArtisanCommandTable;
use Rawphp\Capabilities\Adapters\Artisan\RunCapabilityCommand;
use Rawphp\Capabilities\CapabilitiesServiceProvider;

it('registers command classes from table when artisan enabled', function () {
    $classes = ArtisanCommandRegistrar::classes(['enabled' => true]);
    $sigs = ArtisanCommandRegistrar::signatures(['enabled' => true]);

    expect($classes)->toContain(RunCapabilityCommand::class)
        ->and($sigs)->not->toBeEmpty()
        ->and($sigs[0])->toContain('capability:run');
});

it('registers zero commands when artisan disabled', function () {
    expect(ArtisanCommandRegistrar::classes(['enabled' => false]))->toBeEmpty()
        ->and(ArtisanCommandRegistrar::definitions(['enabled' => false]))->toBeEmpty()
        ->and(ArtisanCommandTable::commands(['enabled' => false]))->toBeEmpty();
});

it('ops role is never product cli', function () {
    foreach (ArtisanCommandRegistrar::definitions(['enabled' => true]) as $def) {
        expect($def['role'])->toBe(ArtisanCommandTable::ROLE)
            ->and($def['caller'])->toBe(ArtisanCommandTable::CALLER)
            ->and($def['role'])->not->toBe('product_cli')
            ->and(ArtisanCapabilityInvoker::isProductCli())->toBeFalse();
    }
});

it('command class is a real console command for provider registration', function () {
    expect(is_subclass_of(RunCapabilityCommand::class, Command::class))->toBeTrue();
});

it('provider artisanCommands helper matches registrar signatures', function () {
    $fromProvider = array_column(CapabilitiesServiceProvider::artisanCommands(['enabled' => true]), 'signature');
    $fromRegistrar = ArtisanCommandRegistrar::signatures(['enabled' => true]);
    expect($fromProvider)->toEqual($fromRegistrar);
});
