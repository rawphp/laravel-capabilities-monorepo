<?php

// REQ-528 / D-017 / L-003: a host-bound Authorizer is a gate every invoke must pass, in front of
// the capability's own rule (fluent authorize callable, else class authorize()). Unit only.

declare(strict_types=1);

use Illuminate\Container\Container;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Contracts\Authorizer;
use Rawphp\Capabilities\Contracts\DefinesCapability;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Tests\Support\SharedFakes;

final class GateRecordingAuthorizer implements Authorizer
{
    public int $calls = 0;

    public function __construct(private readonly bool $allowed) {}

    public function authorize(string $capability, mixed $input, mixed $context): bool
    {
        $this->calls++;

        return $this->allowed;
    }
}

final class GateOwnRuleAllowHandler implements DefinesCapability
{
    public static int $authorizeCalls = 0;

    public static int $runs = 0;

    public function authorize(CreateInvoiceInput $input, CapabilityContext $ctx): bool
    {
        self::$authorizeCalls++;

        return true;
    }

    public function run(CreateInvoiceInput $input): CreateInvoiceResult
    {
        self::$runs++;

        return new CreateInvoiceResult(invoice_id: 1);
    }
}

final class GateOwnRuleDenyHandler implements DefinesCapability
{
    public function authorize(CreateInvoiceInput $input, CapabilityContext $ctx): bool
    {
        return false;
    }

    public function run(CreateInvoiceInput $input): CreateInvoiceResult
    {
        return new CreateInvoiceResult(invoice_id: 2);
    }
}

final class GateNoRuleHandler implements DefinesCapability
{
    public function run(CreateInvoiceInput $input): CreateInvoiceResult
    {
        return new CreateInvoiceResult(invoice_id: 3);
    }
}

/**
 * Registry with the host Authorizer supplied (or, when null, not supplied at all) and one
 * capability per definition kind.
 *
 * @param  'fluent'|'class'  $kind
 * @param  class-string|callable|null  $own  class handler (class kind) or authorize callable (fluent kind); null = no own rule
 */
function gateRegistry(?Authorizer $host, string $kind, string|callable|null $own): CapabilityRegistry
{
    $fakes = SharedFakes::create();
    $registry = new CapabilityRegistry(
        authorizer: $host,
        approvalStore: $fakes->approvals,
        idempotencyStore: $fakes->idempotency,
        auditWriter: $fakes->audit,
        rateLimiter: $fakes->rateLimiter,
    );

    if ($kind === 'class') {
        $registry->register(new CapabilityDefinition(
            name: 'gate-cap',
            surfaces: ['http', 'agent'],
            input: CreateInvoiceInput::class,
            output: CreateInvoiceResult::class,
            allowSystemCallers: true,
            handlerClass: $own ?? GateNoRuleHandler::class,
        ));

        return $registry;
    }

    $builder = Capability::define('gate-cap')
        ->description('gate test')
        ->surfaces(['http', 'agent'])
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->allowSystemCallers(true)
        ->run(fn () => new CreateInvoiceResult(invoice_id: 9));
    if ($own !== null) {
        $builder->authorize($own);
    }
    $builder->register($registry);

    return $registry;
}

function gateInvoke(CapabilityRegistry $registry): ?string
{
    $result = $registry->invoke('gate-cap', PipelineHelpers::validInput(), PipelineHelpers::options());

    return $result->isOk() ? null : $result->errorCode();
}

afterEach(function () {
    Container::setInstance(null);
    GateOwnRuleAllowHandler::$authorizeCalls = 0;
    GateOwnRuleAllowHandler::$runs = 0;
});

// Rule table x definition kind. Own-rule verdicts: allow / deny / none.
$fluentOwn = [
    'allow' => fn () => true,
    'deny' => fn () => false,
];
$classOwn = [
    'allow' => GateOwnRuleAllowHandler::class,
    'deny' => GateOwnRuleDenyHandler::class,
];

foreach (['fluent' => $fluentOwn, 'class' => $classOwn] as $kind => $own) {
    it("happy [$kind]: host allow + own rule allow -> allowed", function () use ($kind, $own) {
        $host = new GateRecordingAuthorizer(true);

        expect(gateInvoke(gateRegistry($host, $kind, $own['allow'])))->toBeNull()
            ->and($host->calls)->toBe(1);
    });

    it("fail [$kind]: host allow + own rule deny -> forbidden", function () use ($kind, $own) {
        $host = new GateRecordingAuthorizer(true);

        expect(gateInvoke(gateRegistry($host, $kind, $own['deny'])))->toBe('forbidden')
            ->and($host->calls)->toBe(1);
    });

    it("fail [$kind]: host deny + own rule allow -> forbidden and the own rule is never called", function () use ($kind, $own) {
        $host = new GateRecordingAuthorizer(false);
        $ownCalls = 0;
        $rule = $kind === 'fluent'
            ? function () use (&$ownCalls): bool {
                $ownCalls++;

                return true;
            }
        : $own['allow'];

        $result = gateInvoke(gateRegistry($host, $kind, $rule));

        expect($result)->toBe('forbidden')
            ->and($host->calls)->toBe(1)
            ->and($ownCalls)->toBe(0)
            ->and(GateOwnRuleAllowHandler::$authorizeCalls)->toBe(0)
            ->and(GateOwnRuleAllowHandler::$runs)->toBe(0);
    });

    it("fail [$kind]: host deny + own rule deny -> forbidden", function () use ($kind, $own) {
        expect(gateInvoke(gateRegistry(new GateRecordingAuthorizer(false), $kind, $own['deny'])))->toBe('forbidden');
    });

    it("happy [$kind]: own rule allow + no host authorizer -> the own rule decides (L-001)", function () use ($kind, $own) {
        expect(gateInvoke(gateRegistry(null, $kind, $own['allow'])))->toBeNull();
    });

    it("fail [$kind]: own rule deny + no host authorizer -> forbidden", function () use ($kind, $own) {
        expect(gateInvoke(gateRegistry(null, $kind, $own['deny'])))->toBe('forbidden');
    });

    it("fail [$kind]: no own rule + no host authorizer -> default deny (L-003)", function () use ($kind) {
        expect(gateInvoke(gateRegistry(null, $kind, null)))->toBe('forbidden');
    });

    it("happy [$kind]: no own rule + host allow -> the host decides", function () use ($kind) {
        expect(gateInvoke(gateRegistry(new GateRecordingAuthorizer(true), $kind, null)))->toBeNull();
    });

    it("fail [$kind]: no own rule + host deny -> forbidden", function () use ($kind) {
        expect(gateInvoke(gateRegistry(new GateRecordingAuthorizer(false), $kind, null)))->toBe('forbidden');
    });
}

it('happy: withAuthorizer() after construction makes the authorizer a gate', function () {
    $registry = gateRegistry(null, 'class', GateOwnRuleAllowHandler::class);
    expect(gateInvoke($registry))->toBeNull();

    $registry->withAuthorizer(new GateRecordingAuthorizer(false));

    expect(gateInvoke($registry))->toBe('forbidden');
});

it('edge: approval accept re-check authorizes() honours the host gate and the own rule', function () {
    $context = new CapabilityContext(
        caller: 'http',
        actor: PipelineHelpers::userActor(),
        scope: new CapabilityScope(tenantId: 't-1'),
    );
    $input = PipelineHelpers::validInput();

    $hostDenies = gateRegistry(new GateRecordingAuthorizer(false), 'class', GateOwnRuleAllowHandler::class);
    $bothAllow = gateRegistry(new GateRecordingAuthorizer(true), 'class', GateOwnRuleAllowHandler::class);
    $ownDenies = gateRegistry(new GateRecordingAuthorizer(true), 'class', GateOwnRuleDenyHandler::class);
    $noHostOwnAllows = gateRegistry(null, 'class', GateOwnRuleAllowHandler::class);
    $fluentHostDenies = gateRegistry(new GateRecordingAuthorizer(false), 'fluent', fn () => true);

    expect($hostDenies->authorizes('gate-cap', $input, $context))->toBeFalse()
        ->and(GateOwnRuleAllowHandler::$authorizeCalls)->toBe(0)
        ->and($bothAllow->authorizes('gate-cap', $input, $context))->toBeTrue()
        ->and($ownDenies->authorizes('gate-cap', $input, $context))->toBeFalse()
        ->and($noHostOwnAllows->authorizes('gate-cap', $input, $context))->toBeTrue()
        ->and($fluentHostDenies->authorizes('gate-cap', $input, $context))->toBeFalse();
});

it('edge: the approval accept path (executeApproval) is gated by the host authorizer', function () {
    $host = new GateRecordingAuthorizer(false);
    $registry = gateRegistry($host, 'class', GateOwnRuleAllowHandler::class);

    $result = $registry->executeApproval([
        'id' => 'ap-1',
        'capability_name' => 'gate-cap',
        'input_json' => PipelineHelpers::validInput(),
        'original_caller' => 'http',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '7',
        'tenant_id' => 't-1',
    ]);

    expect($result->errorCode())->toBe('forbidden')
        ->and(GateOwnRuleAllowHandler::$runs)->toBe(0);
});
