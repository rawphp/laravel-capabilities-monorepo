<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\RunCapabilityJob;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Http\CallerDeriver;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Pipeline\ResolveActor;
use Rawphp\Capabilities\Pipeline\ResolveTenantFromCaller;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\CallerClaimRejectedException;
use Rawphp\Capabilities\Support\DefaultScopeResolver;
use Rawphp\Capabilities\Support\InMemoryScopedQueryFactory;
use Rawphp\Capabilities\Support\MissingJobActorException;
use Rawphp\Capabilities\Support\MissingJobTenantException;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Support\UnresolvedScopeException;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;
use Rawphp\Capabilities\Tests\Support\SharedFakes;
use InvalidArgumentException;
use RuntimeException;
use stdClass;


it("happy: token ability 'capabilities:cli' derives caller cli [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['capabilities:cli']]))->toBe('cli');
});

it("happy: token ability 'none' derives caller http [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it("happy: token ability 'api' derives caller http [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it("happy: token ability 'capabilities:http' derives caller http [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it("edge: oauth client_id capabilities-cli maps to cli when configured [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("edge: oauth client_id ios-app maps to http when configured [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("edge: oauth client_id unknown-client maps to http when configured [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("fail: header claim cli with derived http results in ignored_or_rejected_upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it("fail: header claim http with derived cli results in downgrade_or_keep [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it("fail: header claim agent with derived http results in ignored_upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it("fail: header claim job with derived http results in ignored_upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

