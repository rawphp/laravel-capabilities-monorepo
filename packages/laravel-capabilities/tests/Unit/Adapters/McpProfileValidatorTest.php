<?php

// ORI-842 / UR-062 / D-024: MCP profile allowlist validation (existence + mcp surface).
// Unit-only — no live laravel/mcp.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Mcp\McpProfileValidator;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;

it('throws when profile allowlist names a capability missing from registry [ORI-842]', function () {
    $h = AdapterHelpers::harness([
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'allowSystemCallers' => true],
        ],
    ]);

    expect(fn () => McpProfileValidator::assertAllowlist($h['registry'], 'lab', ['missing.cap']))
        ->toThrow(InvalidArgumentException::class, 'unknown capability');
});

it('throws when capability exists but surfaces exclude mcp [ORI-842]', function () {
    $h = AdapterHelpers::harness([
        'caps' => [
            [
                'name' => 'http-only-job',
                'groups' => ['billing'],
                'surfaces' => ['http', 'cli'],
                'allowSystemCallers' => true,
            ],
        ],
    ]);

    expect(fn () => McpProfileValidator::assertAllowlist($h['registry'], 'lab', ['http-only-job']))
        ->toThrow(InvalidArgumentException::class, 'does not enable the mcp surface');
});

it('throws when profile allowlist contains an empty capability name [ORI-842]', function () {
    $h = AdapterHelpers::harness([
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'allowSystemCallers' => true],
        ],
    ]);

    expect(fn () => McpProfileValidator::assertAllowlist($h['registry'], 'lab', ['']))
        ->toThrow(InvalidArgumentException::class, 'empty capability name');
});

it('passes when all listed capabilities exist and enable mcp [ORI-842]', function () {
    $h = AdapterHelpers::harness([
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'allowSystemCallers' => true],
            ['name' => 'void-invoice', 'groups' => ['billing'], 'allowSystemCallers' => true],
        ],
    ]);

    McpProfileValidator::assertAllowlist($h['registry'], 'billing', ['create-invoice', 'void-invoice']);

    expect(true)->toBeTrue();
});

it('passes for empty allowlist (nothing to validate) [ORI-842]', function () {
    $h = AdapterHelpers::harness([
        'caps' => [
            ['name' => 'create-invoice', 'groups' => ['billing'], 'allowSystemCallers' => true],
        ],
    ]);

    McpProfileValidator::assertAllowlist($h['registry'], 'empty', []);

    expect(true)->toBeTrue();
});
