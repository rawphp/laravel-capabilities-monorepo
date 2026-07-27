<?php

declare(strict_types=1);

/**
 * Guard README D-011 release-gate documentation (REQ-038).
 * String presence only — unit-only, no live peers.
 */

function peerReleaseGateReadme(): string
{
    $path = dirname(__DIR__, 3).'/README.md';

    expect(is_file($path))->toBeTrue("package README missing at {$path}");

    $contents = file_get_contents($path);
    expect($contents)->toBeString()->not->toBeEmpty();

    return $contents;
}

it('happy: README has Peer support / D-011 release gate section [D-011]', function () {
    $readme = peerReleaseGateReadme();

    expect($readme)->toContain('## Peer support / D-011 release gate')
        ->and($readme)->toMatch('/PeerSupportMatrix/')
        ->and($readme)->toContain('src/Adapters/PeerSupportMatrix.php');
});

it('happy: README lists required unit contract suite filters [D-011]', function () {
    $readme = peerReleaseGateReadme();

    expect($readme)->toContain('--filter=PeerContract')
        ->and($readme)->toContain('--filter=Adapter')
        ->and($readme)->toContain('--filter=PeerSupportMatrix');
});

it('happy: README documents fail/disable boot behaviour [D-011]', function () {
    $readme = peerReleaseGateReadme();

    expect($readme)->toContain('on_incompatible')
        ->and($readme)->toContain('fail')
        ->and($readme)->toContain('disable')
        ->and($readme)->toMatch('/half-register/i');
});

it('happy: README documents AdapterApi bump rule [D-011]', function () {
    $readme = peerReleaseGateReadme();

    expect($readme)->toContain('AdapterApi')
        ->and($readme)->toMatch('/requiresBump|bump when/i');
});

it('happy: README states default package CI does not install live peers [D-011]', function () {
    $readme = peerReleaseGateReadme();

    expect($readme)->toMatch('/does\s+\*\*not\*\*\s+install\s+live/i')
        ->and($readme)->toContain('laravel/ai')
        ->and($readme)->toContain('laravel/mcp');
});

it('happy: README documents optional consumer peer-live checklist [D-011]', function () {
    $readme = peerReleaseGateReadme();

    expect($readme)->toMatch('/##+ Optional consumer peer-live/i')
        ->and($readme)->toMatch('/composer require laravel\/ai/i')
        ->and($readme)->toMatch('/composer require laravel\/mcp/i')
        ->and($readme)->toMatch('/matrix cell/i');
});
