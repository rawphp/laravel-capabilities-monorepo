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


it("edge: header http matching derived http is no-op [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it("edge: header downgrade derived http claim cli allowed per policy [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it("edge: header downgrade derived http claim mcp allowed per policy [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it("edge: header downgrade derived http claim agent allowed per policy [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it("edge: header downgrade derived http claim job allowed per policy [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it("fail: header upgrade derived cli claim http ignored or rejected [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it("edge: header cli matching derived cli is no-op [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("edge: header downgrade derived cli claim mcp allowed per policy [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("edge: header downgrade derived cli claim agent allowed per policy [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("edge: header downgrade derived cli claim job allowed per policy [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("fail: header upgrade derived mcp claim http ignored or rejected [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it("fail: header upgrade derived mcp claim cli ignored or rejected [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("edge: header mcp matching derived mcp is no-op [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("edge: header downgrade derived mcp claim agent allowed per policy [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("edge: header downgrade derived mcp claim job allowed per policy [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("fail: header upgrade derived agent claim http ignored or rejected [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it("fail: header upgrade derived agent claim cli ignored or rejected [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("fail: header upgrade derived agent claim mcp ignored or rejected [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("edge: header agent matching derived agent is no-op [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("edge: header downgrade derived agent claim job allowed per policy [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("fail: header upgrade derived job claim http ignored or rejected [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it("fail: header upgrade derived job claim cli ignored or rejected [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("fail: header upgrade derived job claim mcp ignored or rejected [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("fail: header upgrade derived job claim agent ignored or rejected [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("edge: header job matching derived job is no-op [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

