<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\AdapterApi;
use Rawphp\Capabilities\Adapters\PeerSupportMatrix;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;
use Rawphp\Capabilities\Adapters\StructuredToolResponse;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;
use Rawphp\Capabilities\Tests\Fixtures\PeerContractFixtures;

/**
 * Frozen peer contract fixtures (D-011 / REQ-037).
 * Fail when AI/MCP adapters, probe PEER_CLASSES, or AdapterApi shapes drift.
 * Mock/fake peers only — never require live laravel/ai or laravel/mcp.
 */

it('happy: fixture defines AI tool map keys for AdapterApi V1 [D-011]', function () {
    expect(PeerContractFixtures::aiToolKeys())->not->toBeEmpty()
        ->and(PeerContractFixtures::aiToolKeys())->toBe([
            'name',
            'description',
            'input_schema',
            'source',
            'adapter_api',
            'peer',
        ])
        ->and(PeerContractFixtures::adapterApiVersion())->toBe(AdapterApi::V1);
});

it('happy: fixture defines MCP tool registration shape for AdapterApi V1 [D-011]', function () {
    expect(PeerContractFixtures::mcpToolKeys())->not->toBeEmpty()
        ->and(PeerContractFixtures::mcpToolKeys())->toBe(PeerContractFixtures::aiToolKeys())
        ->and(PeerContractFixtures::mcpPeer())->toBe(PeerVersionProbe::PEER_MCP)
        ->and(PeerContractFixtures::aiPeer())->toBe(PeerVersionProbe::PEER_AI)
        ->and(PeerContractFixtures::toolSource())->toBe('registry');
});

it('happy: AiToolAdapterV1 toolsFor produces fixture-compatible shape with mock registry [D-011]', function () {
    $h = AdapterHelpers::harness();
    $tools = $h['ai']->toolsFor('billing');

    expect($tools)->not->toBeEmpty();

    foreach ($tools as $tool) {
        expect(array_keys($tool))->toEqualCanonicalizing(PeerContractFixtures::aiToolKeys())
            ->and($tool['source'])->toBe(PeerContractFixtures::toolSource())
            ->and($tool['adapter_api'])->toBe(PeerContractFixtures::adapterApiVersion())
            ->and($tool['peer'])->toBe(PeerContractFixtures::aiPeer())
            ->and($tool['name'])->toBeString()->not->toBeEmpty()
            ->and($tool['description'])->toBeString()
            ->and(array_key_exists('input_schema', $tool))->toBeTrue();
    }
});

it('happy: McpToolAdapterV1 register produces fixture-compatible shape with mock registry [D-011]', function () {
    $h = AdapterHelpers::harness();
    $tools = $h['mcp']->register('billing');

    expect($tools)->not->toBeEmpty()
        ->and($h['mcp']->registeredTools())->toBe($tools);

    foreach ($tools as $tool) {
        expect(array_keys($tool))->toEqualCanonicalizing(PeerContractFixtures::mcpToolKeys())
            ->and($tool['source'])->toBe(PeerContractFixtures::toolSource())
            ->and($tool['adapter_api'])->toBe(PeerContractFixtures::adapterApiVersion())
            ->and($tool['peer'])->toBe(PeerContractFixtures::mcpPeer())
            ->and($tool['name'])->toBeString()->not->toBeEmpty();
    }
});

it('happy: AiToolAdapterV1 register call shape matches toolsFor fixture [D-011]', function () {
    $h = AdapterHelpers::harness();
    $listed = $h['ai']->toolsFor('billing');
    $registered = $h['ai']->register('billing');

    expect($registered)->toBe($listed)
        ->and($h['ai']->isRegistered())->toBeTrue()
        ->and($h['ai']->registeredTools())->toBe($registered)
        ->and($h['ai']->adapterApiVersion())->toBe(PeerContractFixtures::adapterApiVersion());
});

it('happy: probe PEER_CLASSES remain documented in fixtures [D-011]', function () {
    $documented = PeerContractFixtures::peerClasses();
    $live = PeerVersionProbe::peerClasses();

    expect($documented)->toBe($live)
        ->and($documented)->toHaveKeys([PeerVersionProbe::PEER_AI, PeerVersionProbe::PEER_MCP])
        ->and($documented[PeerVersionProbe::PEER_AI])->not->toBeEmpty()
        ->and($documented[PeerVersionProbe::PEER_MCP])->not->toBeEmpty();

    foreach ($documented as $peer => $classes) {
        expect($classes)->toBeArray()->not->toBeEmpty();
        foreach ($classes as $class) {
            expect($class)->toBeString()->not->toBeEmpty()
                // Fixture documents FQCNs only — suite must not require them to exist.
                ->and(class_exists($class))->toBeFalse();
        }
    }
});

it('happy: matrix cells remain documented in fixtures [D-011]', function () {
    $documented = PeerContractFixtures::matrixCells();
    $live = PeerSupportMatrix::constraints();

    expect($documented)->toBe($live)
        ->and($documented)->toHaveKeys([PeerSupportMatrix::PEER_AI, PeerSupportMatrix::PEER_MCP]);

    foreach ($documented as $peer => $constraints) {
        expect($constraints)->toBeArray()->not->toBeEmpty()
            ->and($constraints)->not->toContain('*');
    }
});

it('happy: AdapterApi CURRENT and supported match fixtures [D-011]', function () {
    expect(AdapterApi::CURRENT)->toBe(PeerContractFixtures::adapterApiVersion())
        ->and(AdapterApi::V1)->toBe(PeerContractFixtures::adapterApiVersion())
        ->and(AdapterApi::supported())->toBe(PeerContractFixtures::supportedAdapterApis())
        ->and(AdapterApi::select(AdapterApi::CURRENT))->toBe(AdapterApi::CURRENT);
});

it('fail: requiresBump true when bridge shape drifts from fixture [D-011]', function () {
    $fixture = PeerContractFixtures::adapterApiShape();
    $drifted = $fixture;
    $drifted['ai_tool_keys'][] = 'extra_peer_field';

    expect(AdapterApi::requiresBump($fixture, $drifted))->toBeTrue()
        ->and(AdapterApi::requiresBump($fixture, $fixture))->toBeFalse();
});

it('happy: live AI tool shape matches fixture shape for requiresBump [D-011]', function () {
    $h = AdapterHelpers::harness();
    $tool = $h['ai']->toolsFor('billing')[0];
    $liveShape = PeerContractFixtures::shapeFromTool($tool);
    $fixtureShape = PeerContractFixtures::aiToolShapeTemplate();

    expect(AdapterApi::requiresBump($fixtureShape, $liveShape))->toBeFalse();
});

it('happy: live MCP tool shape matches fixture shape for requiresBump [D-011]', function () {
    $h = AdapterHelpers::harness();
    $tool = $h['mcp']->register('billing')[0];
    $liveShape = PeerContractFixtures::shapeFromTool($tool);
    $fixtureShape = PeerContractFixtures::mcpToolShapeTemplate();

    expect(AdapterApi::requiresBump($fixtureShape, $liveShape))->toBeFalse();
});

it('happy: structured tool response success shape matches fixture [D-011]', function () {
    $payload = StructuredToolResponse::fromResult(
        CapabilityResult::ok(['invoice_id' => 1], ['request_id' => 'r1']),
    );

    expect(array_keys($payload))->toEqualCanonicalizing(PeerContractFixtures::structuredSuccessKeys())
        ->and($payload['ok'])->toBeTrue()
        ->and($payload)->toHaveKey('data')
        ->and($payload)->toHaveKey('meta');
});

it('happy: structured tool response error shape matches fixture [D-011]', function () {
    $payload = StructuredToolResponse::fromResult(
        CapabilityResult::failure(code: 'forbidden', message: 'no'),
    );

    expect(array_keys($payload))->toEqualCanonicalizing(PeerContractFixtures::structuredErrorKeys())
        ->and($payload['ok'])->toBeFalse()
        ->and(array_keys($payload['error']))->toEqualCanonicalizing(PeerContractFixtures::structuredErrorBodyKeys())
        ->and($payload['error']['structured'])->toBeTrue();
});

it('happy: probe result keys match fixture without live peer classes [D-011]', function () {
    $probe = PeerVersionProbe::fake([
        PeerVersionProbe::PEER_AI => true,
        PeerVersionProbe::PEER_MCP => false,
    ]);
    $result = $probe->probe(PeerVersionProbe::PEER_AI);

    expect(array_keys($result))->toEqualCanonicalizing(PeerContractFixtures::probeResultKeys())
        ->and($result['adapter_api'])->toBe(PeerContractFixtures::adapterApiVersion())
        ->and($result['installed'])->toBeTrue();

    // Default suite uses class_exists fakes only — never loads peer packages.
    foreach (PeerContractFixtures::peerClasses() as $classes) {
        foreach ($classes as $class) {
            expect(class_exists($class))->toBeFalse();
        }
    }
});

it('fail: AiToolAdapterV1 shape keys drift is detected against fixture [D-011]', function () {
    $h = AdapterHelpers::harness();
    $tool = $h['ai']->toolsFor('billing')[0];
    $mutated = $tool;
    unset($mutated['peer']);

    expect(array_keys($mutated))->not->toEqualCanonicalizing(PeerContractFixtures::aiToolKeys())
        ->and(AdapterApi::requiresBump(
            PeerContractFixtures::shapeFromTool($tool),
            PeerContractFixtures::shapeFromTool($mutated),
        ))->toBeTrue();
});
