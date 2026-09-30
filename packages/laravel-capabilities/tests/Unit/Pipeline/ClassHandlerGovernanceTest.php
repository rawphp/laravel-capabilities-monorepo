<?php

// L-001: #[Capability] classes (D-017) own authorize() / needsApproval() / run(). The pipeline
// resolves the handler once per invoke through the container and calls all three.

declare(strict_types=1);

use Illuminate\Container\Container;
use Rawphp\Capabilities\Contracts\DefinesCapability;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

final class GovernanceDenyingHandler implements DefinesCapability
{
    public static int $runs = 0;

    public function authorize(CreateInvoiceInput $input, CapabilityContext $ctx): bool
    {
        return false;
    }

    public function run(CreateInvoiceInput $input): CreateInvoiceResult
    {
        self::$runs++;

        return new CreateInvoiceResult(invoice_id: 1);
    }
}

final class GovernanceAllowingHandler implements DefinesCapability
{
    public static ?CapabilityContext $seenContext = null;

    public function authorize(CreateInvoiceInput $input, CapabilityContext $ctx): bool
    {
        self::$seenContext = $ctx;

        return $input->amount_cents < 1_000_000;
    }

    public function run(CreateInvoiceInput $input): CreateInvoiceResult
    {
        return new CreateInvoiceResult(invoice_id: 2);
    }
}

final class GovernanceApprovalHandler implements DefinesCapability
{
    public static int $runs = 0;

    public function authorize(CreateInvoiceInput $input): bool
    {
        return true;
    }

    public function needsApproval(CreateInvoiceInput $input, CapabilityContext $ctx): bool
    {
        return $ctx->caller() === 'agent';
    }

    public function run(CreateInvoiceInput $input): CreateInvoiceResult
    {
        self::$runs++;

        return new CreateInvoiceResult(invoice_id: 3);
    }
}

final class GovernanceInvoiceNumbers
{
    public function __construct(public int $next = 500) {}
}

final class GovernanceInjectedHandler implements DefinesCapability
{
    public static int $constructions = 0;

    public function __construct(private readonly GovernanceInvoiceNumbers $numbers)
    {
        self::$constructions++;
    }

    public function authorize(CreateInvoiceInput $input, CapabilityContext $ctx): bool
    {
        return true;
    }

    public function needsApproval(CreateInvoiceInput $input, CapabilityContext $ctx): bool
    {
        return false;
    }

    public function run(CreateInvoiceInput $input): CreateInvoiceResult
    {
        return new CreateInvoiceResult(invoice_id: $this->numbers->next);
    }
}

final class GovernanceRunOnlyHandler implements DefinesCapability
{
    public function run(CreateInvoiceInput $input): CreateInvoiceResult
    {
        return new CreateInvoiceResult(invoice_id: 4);
    }
}

function governanceHarness(string $class, bool $authorize = true, array $opts = []): array
{
    $h = PipelineHelpers::harness(array_merge(['authorize' => $authorize], $opts));
    $h['registry']->register(new CapabilityDefinition(
        name: 'class-cap',
        surfaces: ['http', 'agent'],
        input: CreateInvoiceInput::class,
        output: CreateInvoiceResult::class,
        allowSystemCallers: true,
        handlerClass: $class,
    ));

    return $h;
}

afterEach(function () {
    Container::setInstance(null);
});

it('fail: a class authorize() returning false denies even when the host authorizer allows [L-001 / D-017]', function () {
    GovernanceDenyingHandler::$runs = 0;
    $h = governanceHarness(GovernanceDenyingHandler::class, authorize: true);

    $result = $h['registry']->invoke('class-cap', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->errorCode())->toBe('forbidden')
        ->and(GovernanceDenyingHandler::$runs)->toBe(0);
});

it('happy: with the host gate open, a class authorize() decides and sees the context [L-001 / D-017]', function () {
    GovernanceAllowingHandler::$seenContext = null;
    $h = governanceHarness(GovernanceAllowingHandler::class, authorize: true);

    $result = $h['registry']->invoke('class-cap', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->isOk())->toBeTrue()
        ->and($result->data->invoice_id)->toBe(2)
        ->and(GovernanceAllowingHandler::$seenContext)->toBeInstanceOf(CapabilityContext::class)
        ->and(GovernanceAllowingHandler::$seenContext->caller())->toBe('http');
});

it('fail: a class authorize() sees the typed input and can deny on it [L-001]', function () {
    $h = governanceHarness(GovernanceAllowingHandler::class, authorize: true);

    $result = $h['registry']->invoke('class-cap', array_merge(PipelineHelpers::validInput(), ['amount_cents' => 5_000_000]), PipelineHelpers::options());

    expect($result->errorCode())->toBe('forbidden');
});

it('edge: a class needsApproval() returning true yields approval_required without run() [L-001 / D-006]', function () {
    GovernanceApprovalHandler::$runs = 0;
    $h = governanceHarness(GovernanceApprovalHandler::class);

    $viaAgent = $h['registry']->invoke('class-cap', PipelineHelpers::validInput(), PipelineHelpers::options('agent'));
    $viaHttp = $h['registry']->invoke('class-cap', PipelineHelpers::validInput(), PipelineHelpers::options('http'));

    expect($viaAgent->isApprovalRequired())->toBeTrue()
        ->and($viaHttp->isOk())->toBeTrue()
        ->and(GovernanceApprovalHandler::$runs)->toBe(1);
});

it('happy: a handler with constructor dependencies is resolved through the container once per invoke [L-001]', function () {
    GovernanceInjectedHandler::$constructions = 0;
    $container = new Container;
    $container->instance(GovernanceInvoiceNumbers::class, new GovernanceInvoiceNumbers(next: 777));
    Container::setInstance($container);
    $h = governanceHarness(GovernanceInjectedHandler::class, authorize: true);

    $result = $h['registry']->invoke('class-cap', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->isOk())->toBeTrue()
        ->and($result->data->invoice_id)->toBe(777)
        ->and(GovernanceInjectedHandler::$constructions)->toBe(1);
});

it('happy: registry withHandlerFactory replaces container resolution [L-001]', function () {
    GovernanceInjectedHandler::$constructions = 0;
    $h = governanceHarness(GovernanceInjectedHandler::class, authorize: true);
    $made = [];
    $h['registry']->withHandlerFactory(function (string $class) use (&$made): object {
        $made[] = $class;

        return new $class(new GovernanceInvoiceNumbers(next: 42));
    });

    $result = $h['registry']->invoke('class-cap', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->data->invoice_id)->toBe(42)
        ->and($made)->toBe([GovernanceInjectedHandler::class]);
});

it('fail: a handler the factory cannot build is an internal failure and run() is never called [L-001]', function () {
    GovernanceDenyingHandler::$runs = 0;
    $h = governanceHarness(GovernanceDenyingHandler::class);
    $h['registry']->withHandlerFactory(function (string $class): object {
        throw new RuntimeException('no binding');
    });

    $result = $h['registry']->invoke('class-cap', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->errorCode())->toBe('internal')
        ->and(GovernanceDenyingHandler::$runs)->toBe(0);
});

it('edge: a class without authorize() still goes through the host authorizer (deny by default) [L-001 / L-003]', function () {
    $denied = governanceHarness(GovernanceRunOnlyHandler::class, authorize: false);
    $allowed = governanceHarness(GovernanceRunOnlyHandler::class, authorize: true);

    expect($denied['registry']->invoke('class-cap', PipelineHelpers::validInput(), PipelineHelpers::options())->errorCode())->toBe('forbidden')
        ->and($allowed['registry']->invoke('class-cap', PipelineHelpers::validInput(), PipelineHelpers::options())->isOk())->toBeTrue();
});

it('edge: approval re-check authorizes() uses the class authorize() [L-001 / D-006]', function () {
    $h = governanceHarness(GovernanceDenyingHandler::class, authorize: true);
    $context = new CapabilityContext(
        caller: 'http',
        actor: PipelineHelpers::userActor(),
        scope: new CapabilityScope(tenantId: 't-1'),
    );

    expect($h['registry']->authorizes('class-cap', PipelineHelpers::validInput(), $context))->toBeFalse();
});

it('fail: a host authorizer that denies gates a fluent authorize callable too [REQ-528]', function () {
    $h = PipelineHelpers::harness(['authorize' => false, 'authorize_cb' => fn () => true]);

    expect($h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options())->errorCode())->toBe('forbidden')
        ->and($h['registry']->withAuthorizer(StubAuthorizer::allow())->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options())->isOk())->toBeTrue();
});
