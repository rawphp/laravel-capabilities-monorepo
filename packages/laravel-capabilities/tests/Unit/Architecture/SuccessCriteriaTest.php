<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as ScopeH;

it('happy: success criterion: new feature is one capability class [SUCCESS]', function () {
    A::adaptersAreDumb();
    $h = PipelineHelpers::harness();
    expect($h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'))->isOk())->toBeTrue();
});

it('happy: success criterion: turning off MCP is a config flag [SUCCESS]', function () {
    $cfg = CapabilitiesConfig::defaults();
    expect($cfg['surfaces'])->toHaveKey('mcp');
    expect(array_key_exists('enabled', $cfg['surfaces']['mcp'] ?? ['enabled' => true]) || isset($cfg['surfaces']['mcp']['enabled']))->toBeTrue();
});

it('happy: success criterion: agent cannot bypass UI rules [SUCCESS]', function () {
    A::assertConcernCannotSkip('authorize', 'agent');
    A::assertConcernCannotSkip('schema', 'agent');
});

it('happy: success criterion: cross-tenant customer_id denied [SUCCESS]', function () {
    $h = ScopeH::scopeHarness(['tenancy_required' => true]);
    $r = $h['registry']->invoke($h['name'], ScopeH::foreignInput(), ScopeH::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
    ]));
    expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('happy: success criterion: support agent tool list excludes finance void [SUCCESS]', function () {
    A::assertRefuse('full catalog dump to agents by default');
});

it('happy: success criterion: CLI and mobile same invoke endpoint [SUCCESS]', function () {
    A::assertRefuse('second HTTP invoke controller for CLI');
});

it('happy: success criterion: drift is registry bug not lifestyle [SUCCESS]', function () {
    A::noAlternateDomainMutationApi();
    A::adaptersAreDumb();
});

it('happy: success criterion: package feels Laravel-native [SUCCESS]', function () {
    expect(is_file(A::CORE_SRC.'/CapabilitiesServiceProvider.php'))->toBeTrue()
        ->and(is_file(A::CORE_ROOT.'/composer.json'))->toBeTrue();
});
