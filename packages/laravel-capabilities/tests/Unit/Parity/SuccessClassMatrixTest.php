<?php

// REQ-015: Parity cross-caller governance contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ParityHelpers as P;

it('happy: assertParity success class http vs registry [D-020]', function () {
    P::assertParitySurfaces('http', 'registry', 'success');
});

it('happy: assertParity deny class http vs registry [D-020]', function () {
    P::assertParitySurfaces('http', 'registry', 'deny');
});

it('happy: assertParity success class http vs mcp [D-020]', function () {
    P::assertParitySurfaces('http', 'mcp', 'success');
});

it('happy: assertParity deny class http vs mcp [D-020]', function () {
    P::assertParitySurfaces('http', 'mcp', 'deny');
});

it('happy: assertParity success class http vs job [D-020]', function () {
    P::assertParitySurfaces('http', 'job', 'success');
});

it('happy: assertParity deny class http vs job [D-020]', function () {
    P::assertParitySurfaces('http', 'job', 'deny');
});

it('happy: assertParity success class ai vs registry [D-020]', function () {
    P::assertParitySurfaces('ai', 'registry', 'success');
});

it('happy: assertParity deny class ai vs registry [D-020]', function () {
    P::assertParitySurfaces('ai', 'registry', 'deny');
});

it('happy: assertParity success class ai vs http [D-020]', function () {
    P::assertParitySurfaces('ai', 'http', 'success');
});

it('happy: assertParity deny class ai vs http [D-020]', function () {
    P::assertParitySurfaces('ai', 'http', 'deny');
});

it('happy: assertParity success class ai vs mcp [D-020]', function () {
    P::assertParitySurfaces('ai', 'mcp', 'success');
});

it('happy: assertParity deny class ai vs mcp [D-020]', function () {
    P::assertParitySurfaces('ai', 'mcp', 'deny');
});

it('happy: assertParity success class ai vs job [D-020]', function () {
    P::assertParitySurfaces('ai', 'job', 'success');
});

it('happy: assertParity deny class ai vs job [D-020]', function () {
    P::assertParitySurfaces('ai', 'job', 'deny');
});

it('happy: assertParity success class mcp vs registry [D-020]', function () {
    P::assertParitySurfaces('mcp', 'registry', 'success');
});

it('happy: assertParity deny class mcp vs registry [D-020]', function () {
    P::assertParitySurfaces('mcp', 'registry', 'deny');
});

it('happy: assertParity success class job vs registry [D-020]', function () {
    P::assertParitySurfaces('job', 'registry', 'success');
});

it('happy: assertParity deny class job vs registry [D-020]', function () {
    P::assertParitySurfaces('job', 'registry', 'deny');
});

it('happy: assertParity success class job vs mcp [D-020]', function () {
    P::assertParitySurfaces('job', 'mcp', 'success');
});

it('happy: assertParity deny class job vs mcp [D-020]', function () {
    P::assertParitySurfaces('job', 'mcp', 'deny');
});
