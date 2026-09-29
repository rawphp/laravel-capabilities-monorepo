<?php

// Split packaging guards: the monorepo is the only test authority. Package remotes
// and dist archives must not ship a test suite that only boots inside the monorepo.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;

dataset('php packages', [
    'laravel-capabilities',
    'laravel-capabilities-messaging',
    'laravel-capabilities-ai',
]);

it('split excludes monorepo-only test scaffolding from PHP package remotes', function () {
    $workflow = (string) file_get_contents(A::MONOREPO_ROOT.'/.github/workflows/split-packages.yml');

    // Root-anchored so only the package-root suite is dropped (CLI *_test.go stays).
    expect($workflow)->toContain("--exclude '/tests/'")
        ->and($workflow)->toContain("--exclude '/phpunit.xml'");
});

it('package composer.json carries no test script or test-only autoload', function (string $package) {
    $composer = json_decode(
        (string) file_get_contents(A::MONOREPO_ROOT.'/packages/'.$package.'/composer.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    expect($composer['scripts']['test'] ?? null)->toBeNull()
        ->and($composer)->not->toHaveKey('autoload-dev')
        ->and($composer)->not->toHaveKey('require-dev');
})->with('php packages');

it('package .gitattributes export-ignores tests and phpunit.xml from dist archives', function (string $package) {
    $path = A::MONOREPO_ROOT.'/packages/'.$package.'/.gitattributes';

    expect(is_file($path))->toBeTrue();
    $attributes = (string) file_get_contents($path);
    expect($attributes)->toMatch('#^/tests\s+export-ignore$#m')
        ->and($attributes)->toMatch('#^/phpunit\.xml\s+export-ignore$#m');
})->with('php packages');
