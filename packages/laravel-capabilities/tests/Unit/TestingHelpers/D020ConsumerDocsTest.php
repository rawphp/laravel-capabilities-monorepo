<?php

declare(strict_types=1);

/**
 * Guard package README + monorepo D-020 consumer docs (REQ-045).
 * Docs must document real assertSchemaSnapshot / assertParity argument shapes —
 * not empty-arg presence stubs. Unit-only string checks.
 */
function d020MonorepoRoot(): string
{
    // tests/Unit/TestingHelpers → package root is 3 levels up; monorepo root is packages/..
    return dirname(__DIR__, 5);
}

function d020PackageReadme(): string
{
    $path = dirname(__DIR__, 3).'/README.md';

    expect(is_file($path))->toBeTrue("package README missing at {$path}");

    $contents = file_get_contents($path);
    expect($contents)->toBeString()->not->toBeEmpty();

    return $contents;
}

function d020Spec(): string
{
    $path = d020MonorepoRoot().'/docs/spec.md';

    expect(is_file($path))->toBeTrue("docs/spec.md missing at {$path}");

    $contents = file_get_contents($path);
    expect($contents)->toBeString()->not->toBeEmpty();

    return $contents;
}

function d020Tutorial(): string
{
    $path = d020MonorepoRoot().'/docs/tutorials/first-capability.md';

    expect(is_file($path))->toBeTrue("tutorial missing at {$path}");

    $contents = file_get_contents($path);
    expect($contents)->toBeString()->not->toBeEmpty();

    return $contents;
}

it('happy: package README documents assertSchemaSnapshot with real argument shapes [D-020]', function () {
    $readme = d020PackageReadme();

    expect($readme)->toMatch('/##+ Testing|##+ D-020|##+ Schema snapshots|##+ Parity/i')
        ->and($readme)->toContain('assertSchemaSnapshot')
        ->and($readme)->toContain('assertParity')
        // Real lock modes — not bare name-only call as the only example
        ->and($readme)->toMatch('/input_schema/')
        ->and($readme)->toMatch('/output_schema/')
        ->and($readme)->toMatch('/\.schema\.json|snapshotDirectory|capability-schemas/');
});

it('happy: package README documents assertParity options shape [D-020]', function () {
    $readme = d020PackageReadme();

    expect($readme)->toMatch("/assertParity\s*\(\s*['\"]/")
        ->and($readme)->toContain("'surfaces'")
        ->and($readme)->toContain("'input'")
        // Must not claim empty-arg assertParity alone proves multi-surface parity
        ->and($readme)->not->toMatch('/assertParity\s*\(\s*\)\s*;/');
});

it('happy: package README states unit-path / not live multi-surface feature suite [D-020]', function () {
    $readme = d020PackageReadme();

    expect($readme)->toMatch('/success\/deny|success.deny|result class/i')
        ->and($readme)->toMatch('/not.*live multi-surface|not a live|unit-path|mocks\/fakes/i');
});

it('happy: spec D-020 documents real snapshot lock modes not empty-arg only [D-020]', function () {
    $spec = d020Spec();

    // Must not present name-only call as locking schemas
    expect($spec)->not->toMatch("/assertSchemaSnapshot\('create-invoice'\);\s*\/\/\s*locks/");

    // Must show durable path, conventional dir, or envelope with both schema sides
    expect($spec)->toMatch('/assertSchemaSnapshot/')
        ->and($spec)->toMatch('/input_schema/')
        ->and($spec)->toMatch('/output_schema|\.schema\.json|snapshot/');
});

it('happy: first-capability tutorial embeds real helper snippets [D-020]', function () {
    $tutorial = d020Tutorial();

    expect($tutorial)->toContain('assertSchemaSnapshot')
        ->and($tutorial)->toContain('assertParity')
        ->and($tutorial)->toMatch("/'surfaces'\s*=>/")
        ->and($tutorial)->toMatch('/\.schema\.json|capability-schemas|snapshotDirectory|null,\s*/');
});
