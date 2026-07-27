<?php

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Discovery\AttributeDiscoverer;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\Capabilities\AttributedCreateInvoice;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;

it('happy: attribute class with DefinesCapability auto-discovered under configured path [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $registry->discover(classMap: [AttributedCreateInvoice::class]);

    expect($registry->has('create-invoice'))->toBeTrue();
    $def = $registry->get('create-invoice');
    expect($def->handlerClass)->toBe(AttributedCreateInvoice::class)
        ->and($def->source)->toBe('attribute')
        ->and($def->input)->toBe(CreateInvoiceInput::class);
});

it('happy: fluent Capability::define registers same CapabilityDefinition shape [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::fluentCreateInvoice($registry);

    expect($def)->toBeInstanceOf(CapabilityDefinition::class)
        ->and($def->name)->toBe('create-invoice')
        ->and($def->input)->toBe(CreateInvoiceInput::class)
        ->and($def->output)->toBe(CreateInvoiceResult::class)
        ->and($def->source)->toBe('fluent')
        ->and($registry->get('create-invoice')->name)->toBe('create-invoice');
});

it('fail: duplicate name from class and fluent throws at boot [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $registry->discover(classMap: [AttributedCreateInvoice::class]);

    expect(fn () => DiscoveryHelpers::fluentCreateInvoice($registry))
        ->toThrow(InvalidArgumentException::class);
});

it('fail: ad-hoc invokable without registry is not supported for mutations [D-017]', function () {
    $invokable = new class
    {
        public function __invoke(array $input): array
        {
            return $input;
        }
    };

    $registry = DiscoveryHelpers::registry();
    expect($registry->all())->toBe([])
        ->and(is_callable($invokable))->toBeTrue()
        ->and($registry->has('create-invoice'))->toBeFalse();
});

it('fail: third discovery path is not registered [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    // Only attribute classMap + fluent register; plain class name without attribute is ignored.
    $discoverer = new AttributeDiscoverer;
    $result = $discoverer->fromClasses([stdClass::class]);

    expect($result)->toBe([])
        ->and($registry->all())->toBe([]);
});

it('happy: definition stores field name when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'field-name-cap');
    expect($def->name)->toBe('field-name-cap');
});

it('happy: definition stores field description when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'desc-cap', ['description' => 'Hello world']);
    expect($def->description)->toBe('Hello world');
});

it('happy: definition stores field surfaces when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'surf-cap', ['surfaces' => ['agent', 'cli']]);
    expect($def->surfaces)->toBe(['agent', 'cli']);
});

it('happy: definition stores field input when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'input-cap');
    expect($def->input)->toBe(CreateInvoiceInput::class);
});

it('happy: definition stores field output when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'output-cap');
    expect($def->output)->toBe(CreateInvoiceResult::class);
});

it('happy: definition stores field aliases when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'alias-cap', ['aliases' => ['old.name']]);
    expect($def->aliases)->toBe(['old.name']);
});

it('happy: definition stores field deprecated when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'dep-cap', ['deprecated' => true]);
    expect($def->deprecated)->toBeTrue();
});

it('happy: definition stores field successor when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'succ-cap', ['successor' => 'new-cap']);
    expect($def->successor)->toBe('new-cap');
});

it('happy: definition stores field sunset_at when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'sunset-cap', ['sunset_at' => '2026-12-31']);
    expect($def->sunset_at)->toBe('2026-12-31');
});

it('happy: definition stores field groups when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'groups-cap', ['groups' => ['billing', 'admin']]);
    expect($def->groups)->toBe(['billing', 'admin']);
});

it('happy: definition stores field tags when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'tags-cap', ['tags' => ['finance']]);
    expect($def->tags)->toBe(['finance']);
});

it('happy: definition stores field readOnly when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = Capability::define('ro-cap')
        ->readOnly(true)
        ->surfaces(['agent'])
        ->register($registry);
    expect($def->readOnly)->toBeTrue()
        ->and($def->isMutating())->toBeFalse();
});

it('happy: definition stores field allowSystemCallers when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'sys-cap', ['allowSystemCallers' => ['worker-a']]);
    expect($def->allowSystemCallers)->toBe(['worker-a']);
});

it('happy: definition stores field globalSystem when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'gs-cap', ['globalSystem' => true]);
    expect($def->globalSystem)->toBeTrue();
});

it('happy: definition stores field approvalPolicy when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'ap-cap', ['approvalPolicy' => 'two_person']);
    expect($def->approvalPolicy)->toBe('two_person');
});

it('happy: definition stores field approvalTtlHours when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'ttl-cap', ['approvalTtlHours' => 48]);
    expect($def->approvalTtlHours)->toBe(48);
});

it('happy: definition stores field rateLimit when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'rl-cap', ['rateLimit' => ['per_minute' => 5]]);
    expect($def->rateLimit)->toBe(['per_minute' => 5]);
});

it('happy: definition stores field idempotent when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'id-cap', ['idempotent' => 'required']);
    expect($def->idempotent)->toBe(CapabilityDefinition::IDEMPOTENT_REQUIRED);
});

it('happy: definition stores field audit when declared [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'audit-cap', ['audit' => ['force' => true]]);
    expect($def->audit)->toBe(['force' => true]);
});

it('edge: empty surfaces list yields no effective exposure [SURF-001]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = Capability::define('empty-surf')
        ->readOnly(true)
        ->surfaces([])
        ->register($registry);

    expect($def->effectiveSurfaces($registry->globallyEnabledSurfaces()))->toBe([])
        ->and($def->hasEffectiveExposure($registry->globallyEnabledSurfaces()))->toBeFalse();
});

it('happy: aliases resolve to canonical name before run [D-012]', function () {
    $registry = DiscoveryHelpers::registry();
    DiscoveryHelpers::mutatingWith($registry, 'canonical-cap', [
        'aliases' => ['legacy.cap'],
        'run' => fn ($in) => new CreateInvoiceResult(invoice_id: 9),
    ]);

    expect($registry->resolveName('legacy.cap'))->toBe('canonical-cap')
        ->and($registry->get('legacy.cap')->name)->toBe('canonical-cap');

    $result = $registry->invoke('legacy.cap', [
        'customer_id' => 1,
        'amount_cents' => 100,
        'currency' => 'USD',
    ]);
    expect($result->isOk())->toBeTrue()
        ->and($result->data->invoice_id)->toBe(9);
});

it('happy: allowSystemCallers empty denies all SystemActors [D-002]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'deny-sys', ['allowSystemCallers' => false]);
    expect($def->allowsSystemCaller(SystemActor::named('any')))->toBeFalse();
});

it('happy: allowSystemCallers true allows any registered system name [D-002]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'any-sys', ['allowSystemCallers' => true]);
    expect($def->allowsSystemCaller(SystemActor::named('cron')))->toBeTrue()
        ->and($def->allowsSystemCaller(SystemActor::named('other')))->toBeTrue();
});

it('happy: allowSystemCallers list allows only listed SystemActor names [D-002]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'list-sys', ['allowSystemCallers' => ['billing-worker']]);
    expect($def->allowsSystemCaller(SystemActor::named('billing-worker')))->toBeTrue()
        ->and($def->allowsSystemCaller(SystemActor::named('other')))->toBeFalse();
});

it('happy: globalSystem true allows SystemActor without tenantId [D-003]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'global-sys', [
        'globalSystem' => true,
        'allowSystemCallers' => true,
    ]);
    expect($def->globalSystem)->toBeTrue()
        ->and($def->allowsSystemCaller(SystemActor::named('ops')))->toBeTrue();
});

it('happy: readOnly true marks non-mutating for audit and idempotency [D-005]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = Capability::define('ro-flags')
        ->readOnly(true)
        ->surfaces(['http'])
        ->register($registry);

    expect($def->isMutating())->toBeFalse()
        ->and($def->shouldAudit())->toBeFalse()
        ->and($def->shouldUseIdempotency())->toBeFalse();
});

it('edge: groups and tags available for profile composition [D-008]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = DiscoveryHelpers::mutatingWith($registry, 'profile-meta', [
        'groups' => ['support'],
        'tags' => ['safe'],
    ]);
    expect($def->groups)->toContain('support')
        ->and($def->tags)->toContain('safe');
});

it('fail: missing name on definition rejected at registration [D-017]', function () {
    expect(fn () => new CapabilityDefinition(name: ''))
        ->toThrow(InvalidArgumentException::class);
});

it('fail: missing input type on mutating capability rejected at registration [D-017]', function () {
    expect(fn () => new CapabilityDefinition(name: 'no-input', readOnly: false, input: null))
        ->toThrow(InvalidArgumentException::class);
});
