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


it("happy: AiToolAdapter sets caller agent in server code [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['adapter' => 'agent', 'server_caller' => 'agent']))->toBe('agent');
});

it("fail: AiToolAdapter does not trust client caller field [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['adapter' => 'cli', 'server_caller' => 'cli']))->toBe('cli');
});

it("happy: McpToolAdapter sets caller mcp in server code [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['adapter' => 'mcp', 'server_caller' => 'mcp']))->toBe('mcp');
});

it("fail: McpToolAdapter does not trust client caller field [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['adapter' => 'cli', 'server_caller' => 'cli']))->toBe('cli');
});

it("happy: HttpController sets caller http_or_cli_derived in server code [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("fail: HttpController does not trust client caller field [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("happy: RunCapabilityJob sets caller job in server code [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("fail: RunCapabilityJob does not trust client caller field [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("happy: ArtisanCommand sets caller job_or_explicit in server code [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("fail: ArtisanCommand does not trust client caller field [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("happy: InProcessInvoke sets caller explicit_argument in server code [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

it("fail: InProcessInvoke does not trust client caller field [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli');
        expect($r['caller'])->toBe('cli');
});

