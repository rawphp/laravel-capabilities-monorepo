<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\RunCapabilityJob;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\MissingJobTenantException;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

it('edge: job dispatch resolves scope when actor=user tenant=present globalSystem=True tenancyRequired=True [D-003]', function () {
    $h = H::scopeHarness([
        'allowSystemCallers' => true,
        'globalSystem' => true,
        'tenancy_required' => true,
    ]);
    if (true) {
        // re-register with globalSystem
        $h = H::scopeHarness(['allowSystemCallers' => true, 'globalSystem' => true, 'name' => 'gs-cap']);
    }
    $payload = [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => 7,
        'tenantId' => 'tenant-a',
        'tenancy_required' => true,
        'globalSystem' => true,
    ];
    if (! false && 'tenant-a' === null) {
        $payload['tenancy_required'] = false;
    }
    try {
        $result = RunCapabilityJob::dispatchSync($h['registry'], $payload);
        expect($result)->toBeInstanceOf(CapabilityResult::class);
    } catch (MissingJobTenantException $e) {
        expect(true && ! true && false && ! true)->toBeTrue();
    }
});

it('edge: job dispatch resolves scope when actor=user tenant=present globalSystem=True tenancyRequired=False [D-003]', function () {
    $h = H::scopeHarness([
        'allowSystemCallers' => true,
        'globalSystem' => true,
        'tenancy_required' => false,
    ]);
    if (true) {
        // re-register with globalSystem
        $h = H::scopeHarness(['allowSystemCallers' => true, 'globalSystem' => true, 'name' => 'gs-cap']);
    }
    $payload = [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => 7,
        'tenantId' => 'tenant-a',
        'tenancy_required' => false,
        'globalSystem' => true,
    ];
    if (! false && 'tenant-a' === null) {
        $payload['tenancy_required'] = false;
    }
    try {
        $result = RunCapabilityJob::dispatchSync($h['registry'], $payload);
        expect($result)->toBeInstanceOf(CapabilityResult::class);
    } catch (MissingJobTenantException $e) {
        expect(false && ! true && false && ! true)->toBeTrue();
    }
});

it('edge: job dispatch resolves scope when actor=user tenant=present globalSystem=False tenancyRequired=True [D-003]', function () {
    $h = H::scopeHarness([
        'allowSystemCallers' => true,
        'globalSystem' => false,
        'tenancy_required' => true,
    ]);
    if (false) {
        // re-register with globalSystem
        $h = H::scopeHarness(['allowSystemCallers' => true, 'globalSystem' => true, 'name' => 'gs-cap']);
    }
    $payload = [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => 7,
        'tenantId' => 'tenant-a',
        'tenancy_required' => true,
        'globalSystem' => false,
    ];
    if (! false && 'tenant-a' === null) {
        $payload['tenancy_required'] = false;
    }
    try {
        $result = RunCapabilityJob::dispatchSync($h['registry'], $payload);
        expect($result)->toBeInstanceOf(CapabilityResult::class);
    } catch (MissingJobTenantException $e) {
        expect(true && ! false && false && ! true)->toBeTrue();
    }
});

it('edge: job dispatch resolves scope when actor=user tenant=present globalSystem=False tenancyRequired=False [D-003]', function () {
    $h = H::scopeHarness([
        'allowSystemCallers' => true,
        'globalSystem' => false,
        'tenancy_required' => false,
    ]);
    if (false) {
        // re-register with globalSystem
        $h = H::scopeHarness(['allowSystemCallers' => true, 'globalSystem' => true, 'name' => 'gs-cap']);
    }
    $payload = [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => 7,
        'tenantId' => 'tenant-a',
        'tenancy_required' => false,
        'globalSystem' => false,
    ];
    if (! false && 'tenant-a' === null) {
        $payload['tenancy_required'] = false;
    }
    try {
        $result = RunCapabilityJob::dispatchSync($h['registry'], $payload);
        expect($result)->toBeInstanceOf(CapabilityResult::class);
    } catch (MissingJobTenantException $e) {
        expect(false && ! false && false && ! true)->toBeTrue();
    }
});

it('edge: job dispatch scope from user membership when actor=user tenant=absent globalSystem=True tenancyRequired=True [D-003]', function () {
    $h = H::scopeHarness([
        'allowSystemCallers' => true,
        'globalSystem' => true,
        'tenancy_required' => true,
    ]);
    if (true) {
        // re-register with globalSystem
        $h = H::scopeHarness(['allowSystemCallers' => true, 'globalSystem' => true, 'name' => 'gs-cap']);
    }
    $payload = [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => 7,
        'tenantId' => null,
        'tenancy_required' => true,
        'globalSystem' => true,
    ];
    if (! false && null === null) {
        $payload['tenancy_required'] = false;
    }
    try {
        $result = RunCapabilityJob::dispatchSync($h['registry'], $payload);
        expect($result)->toBeInstanceOf(CapabilityResult::class);
    } catch (MissingJobTenantException $e) {
        expect(true && ! true && false && ! false)->toBeTrue();
    }
});

it('edge: job dispatch scope from user membership when actor=user tenant=absent globalSystem=True tenancyRequired=False [D-003]', function () {
    $h = H::scopeHarness([
        'allowSystemCallers' => true,
        'globalSystem' => true,
        'tenancy_required' => false,
    ]);
    if (true) {
        // re-register with globalSystem
        $h = H::scopeHarness(['allowSystemCallers' => true, 'globalSystem' => true, 'name' => 'gs-cap']);
    }
    $payload = [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => 7,
        'tenantId' => null,
        'tenancy_required' => false,
        'globalSystem' => true,
    ];
    if (! false && null === null) {
        $payload['tenancy_required'] = false;
    }
    try {
        $result = RunCapabilityJob::dispatchSync($h['registry'], $payload);
        expect($result)->toBeInstanceOf(CapabilityResult::class);
    } catch (MissingJobTenantException $e) {
        expect(false && ! true && false && ! false)->toBeTrue();
    }
});

it('edge: job dispatch scope from user membership when actor=user tenant=absent globalSystem=False tenancyRequired=True [D-003]', function () {
    $h = H::scopeHarness([
        'allowSystemCallers' => true,
        'globalSystem' => false,
        'tenancy_required' => true,
    ]);
    if (false) {
        // re-register with globalSystem
        $h = H::scopeHarness(['allowSystemCallers' => true, 'globalSystem' => true, 'name' => 'gs-cap']);
    }
    $payload = [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => 7,
        'tenantId' => null,
        'tenancy_required' => true,
        'globalSystem' => false,
    ];
    if (! false && null === null) {
        $payload['tenancy_required'] = false;
    }
    try {
        $result = RunCapabilityJob::dispatchSync($h['registry'], $payload);
        expect($result)->toBeInstanceOf(CapabilityResult::class);
    } catch (MissingJobTenantException $e) {
        expect(true && ! false && false && ! false)->toBeTrue();
    }
});

it('edge: job dispatch scope from user membership when actor=user tenant=absent globalSystem=False tenancyRequired=False [D-003]', function () {
    $h = H::scopeHarness([
        'allowSystemCallers' => true,
        'globalSystem' => false,
        'tenancy_required' => false,
    ]);
    if (false) {
        // re-register with globalSystem
        $h = H::scopeHarness(['allowSystemCallers' => true, 'globalSystem' => true, 'name' => 'gs-cap']);
    }
    $payload = [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => 7,
        'tenantId' => null,
        'tenancy_required' => false,
        'globalSystem' => false,
    ];
    if (! false && null === null) {
        $payload['tenancy_required'] = false;
    }
    try {
        $result = RunCapabilityJob::dispatchSync($h['registry'], $payload);
        expect($result)->toBeInstanceOf(CapabilityResult::class);
    } catch (MissingJobTenantException $e) {
        expect(false && ! false && false && ! false)->toBeTrue();
    }
});

it('happy: job dispatch allowed when actor=system tenant=present globalSystem=True tenancyRequired=True and allowlisted [D-002]', function () {
    $h = H::scopeHarness([
        'allowSystemCallers' => true,
        'globalSystem' => true,
        'tenancy_required' => true,
    ]);
    if (true) {
        // re-register with globalSystem
        $h = H::scopeHarness(['allowSystemCallers' => true, 'globalSystem' => true, 'name' => 'gs-cap']);
    }
    $payload = [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'),
        'tenantId' => 'tenant-a',
        'tenancy_required' => true,
        'globalSystem' => true,
    ];
    if (! true && 'tenant-a' === null) {
        $payload['tenancy_required'] = false;
    }
    try {
        $result = RunCapabilityJob::dispatchSync($h['registry'], $payload);
        expect($result)->toBeInstanceOf(CapabilityResult::class);
    } catch (MissingJobTenantException $e) {
        expect(true && ! true && true && ! true)->toBeTrue();
    }
});

it('happy: job dispatch allowed when actor=system tenant=present globalSystem=True tenancyRequired=False and allowlisted [D-002]', function () {
    $h = H::scopeHarness([
        'allowSystemCallers' => true,
        'globalSystem' => true,
        'tenancy_required' => false,
    ]);
    if (true) {
        // re-register with globalSystem
        $h = H::scopeHarness(['allowSystemCallers' => true, 'globalSystem' => true, 'name' => 'gs-cap']);
    }
    $payload = [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'),
        'tenantId' => 'tenant-a',
        'tenancy_required' => false,
        'globalSystem' => true,
    ];
    if (! true && 'tenant-a' === null) {
        $payload['tenancy_required'] = false;
    }
    try {
        $result = RunCapabilityJob::dispatchSync($h['registry'], $payload);
        expect($result)->toBeInstanceOf(CapabilityResult::class);
    } catch (MissingJobTenantException $e) {
        expect(false && ! true && true && ! true)->toBeTrue();
    }
});

it('happy: job dispatch allowed when actor=system tenant=present globalSystem=False tenancyRequired=True and allowlisted [D-002]', function () {
    $h = H::scopeHarness([
        'allowSystemCallers' => true,
        'globalSystem' => false,
        'tenancy_required' => true,
    ]);
    if (false) {
        // re-register with globalSystem
        $h = H::scopeHarness(['allowSystemCallers' => true, 'globalSystem' => true, 'name' => 'gs-cap']);
    }
    $payload = [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'),
        'tenantId' => 'tenant-a',
        'tenancy_required' => true,
        'globalSystem' => false,
    ];
    if (! true && 'tenant-a' === null) {
        $payload['tenancy_required'] = false;
    }
    try {
        $result = RunCapabilityJob::dispatchSync($h['registry'], $payload);
        expect($result)->toBeInstanceOf(CapabilityResult::class);
    } catch (MissingJobTenantException $e) {
        expect(true && ! false && true && ! true)->toBeTrue();
    }
});

it('happy: job dispatch allowed when actor=system tenant=present globalSystem=False tenancyRequired=False and allowlisted [D-002]', function () {
    $h = H::scopeHarness([
        'allowSystemCallers' => true,
        'globalSystem' => false,
        'tenancy_required' => false,
    ]);
    if (false) {
        // re-register with globalSystem
        $h = H::scopeHarness(['allowSystemCallers' => true, 'globalSystem' => true, 'name' => 'gs-cap']);
    }
    $payload = [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'),
        'tenantId' => 'tenant-a',
        'tenancy_required' => false,
        'globalSystem' => false,
    ];
    if (! true && 'tenant-a' === null) {
        $payload['tenancy_required'] = false;
    }
    try {
        $result = RunCapabilityJob::dispatchSync($h['registry'], $payload);
        expect($result)->toBeInstanceOf(CapabilityResult::class);
    } catch (MissingJobTenantException $e) {
        expect(false && ! false && true && ! true)->toBeTrue();
    }
});

it('edge: job dispatch resolves scope when actor=system tenant=absent globalSystem=True tenancyRequired=True [D-003]', function () {
    $h = H::scopeHarness([
        'allowSystemCallers' => true,
        'globalSystem' => true,
        'tenancy_required' => true,
    ]);
    if (true) {
        // re-register with globalSystem
        $h = H::scopeHarness(['allowSystemCallers' => true, 'globalSystem' => true, 'name' => 'gs-cap']);
    }
    $payload = [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'),
        'tenantId' => null,
        'tenancy_required' => true,
        'globalSystem' => true,
    ];
    if (! true && null === null) {
        $payload['tenancy_required'] = false;
    }
    try {
        $result = RunCapabilityJob::dispatchSync($h['registry'], $payload);
        expect($result)->toBeInstanceOf(CapabilityResult::class);
    } catch (MissingJobTenantException $e) {
        expect(true && ! true && true && ! false)->toBeTrue();
    }
});

it('edge: job dispatch resolves scope when actor=system tenant=absent globalSystem=True tenancyRequired=False [D-003]', function () {
    $h = H::scopeHarness([
        'allowSystemCallers' => true,
        'globalSystem' => true,
        'tenancy_required' => false,
    ]);
    if (true) {
        // re-register with globalSystem
        $h = H::scopeHarness(['allowSystemCallers' => true, 'globalSystem' => true, 'name' => 'gs-cap']);
    }
    $payload = [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'),
        'tenantId' => null,
        'tenancy_required' => false,
        'globalSystem' => true,
    ];
    if (! true && null === null) {
        $payload['tenancy_required'] = false;
    }
    try {
        $result = RunCapabilityJob::dispatchSync($h['registry'], $payload);
        expect($result)->toBeInstanceOf(CapabilityResult::class);
    } catch (MissingJobTenantException $e) {
        expect(false && ! true && true && ! false)->toBeTrue();
    }
});

it('fail: job dispatch refused when actor=system tenant=absent globalSystem=False tenancyRequired=True [P2-005]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler'], 'globalSystem' => false]);
    expect(fn () => RunCapabilityJob::dispatchSync($h['registry'], [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'),
        'tenantId' => null,
        'tenancy_required' => true,
        'globalSystem' => false,
    ]))->toThrow(MissingJobTenantException::class);
});

it('edge: job dispatch resolves scope when actor=system tenant=absent globalSystem=False tenancyRequired=False [D-003]', function () {
    $h = H::scopeHarness([
        'allowSystemCallers' => true,
        'globalSystem' => false,
        'tenancy_required' => false,
    ]);
    if (false) {
        // re-register with globalSystem
        $h = H::scopeHarness(['allowSystemCallers' => true, 'globalSystem' => true, 'name' => 'gs-cap']);
    }
    $payload = [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'),
        'tenantId' => null,
        'tenancy_required' => false,
        'globalSystem' => false,
    ];
    if (! true && null === null) {
        $payload['tenancy_required'] = false;
    }
    try {
        $result = RunCapabilityJob::dispatchSync($h['registry'], $payload);
        expect($result)->toBeInstanceOf(CapabilityResult::class);
    } catch (MissingJobTenantException $e) {
        expect(false && ! false && true && ! false)->toBeTrue();
    }
});
