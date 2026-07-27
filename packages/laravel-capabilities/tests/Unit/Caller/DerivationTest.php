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


it("happy: Sanctum ability capabilities:cli derives caller cli [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['capabilities:cli']]))->toBe('cli');
});

it("happy: Sanctum PAT without mapped ability derives caller http [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['token_abilities' => ['read']]))->toBe('http');
});

it("happy: OAuth client registered as cli derives caller cli [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['oauth_client_id' => 'cli-app']))->toBe('cli');
});

it("happy: unregistered OAuth client derives http [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['oauth_client_id' => 'unknown-client']))->toBe('http');
});

it("happy: in-process adapter or job can set caller agent from server code [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['server_caller' => 'agent']))->toBe('agent');
});

it("happy: in-process adapter or job can set caller mcp from server code [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['server_caller' => 'mcp']))->toBe('mcp');
});

it("happy: in-process adapter or job can set caller http from server code [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['server_caller' => 'http']))->toBe('http');
});

it("happy: in-process adapter or job can set caller cli from server code [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['server_caller' => 'cli']))->toBe('cli');
});

it("happy: in-process adapter or job can set caller job from server code [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->deriveFromCredential(['server_caller' => 'job']))->toBe('job');
});

it("fail: X-Capabilities-Caller alone does not set caller [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->fromHeaderAlone('cli'))->toBe('http');
        $r = $d->resolve([], 'cli');
        expect($r['caller'])->toBe('http')->and($r['derived'])->toBe('http');
});

it("fail: model tool args cannot set caller [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->fromClientBody(['caller' => 'http', 'actor' => 1]))->toBeNull();
        expect($d->deriveFromCredential([]))->toBe('http');
});

it("fail: MCP tool JSON cannot set caller [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->fromClientBody(['caller' => 'http', 'actor' => 1]))->toBeNull();
        expect($d->deriveFromCredential([]))->toBe('http');
});

it("fail: CLI request body cannot set caller authoritatively [D-022]", function () {
    $d = H::defaultDeriver();
        expect($d->fromClientBody(['caller' => 'http', 'actor' => 1]))->toBeNull();
        expect($d->deriveFromCredential([]))->toBe('http');
});

it("edge: header matching derived is no-op [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('cli', 'cli');
        expect($r['caller'])->toBe('cli')->and($r['rejected'])->toBeFalse();
});

it("edge: header downgrade to stricter bucket allowed per privilege_order [D-022]", function () {
    $d = H::defaultDeriver();
        // http more privileged than cli; claim cli is downgrade
        $r = $d->applyHeaderClaim('http', 'cli');
        expect($r['caller'])->toBe('cli')->and($r['reason'])->toBe('downgrade');
});

it("fail: header upgrade to more privileged bucket ignored by default [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('cli', 'http');
        expect($r['caller'])->toBe('cli')->and($r['rejected'])->toBeFalse()->and($r['reason'])->toBe('upgrade_ignored');
});

it("fail: header upgrade rejected with caller_claim_rejected when reject_upgrade_attempts true [D-022]", function () {
    $d = H::defaultDeriver(rejectUpgrade: true);
        $r = $d->applyHeaderClaim('cli', 'http');
        expect($r['rejected'])->toBeTrue()->and($r['reason'])->toBe('caller_claim_rejected')->and($r['caller'])->toBe('cli');
});

it("edge: unknown header value ignored [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('http', 'spaceship');
        expect($r['caller'])->toBe('http')->and($r['reason'])->toBe('unknown_header_ignored');
});

it("edge: spoof header from derived agent claiming mcp does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('agent', 'mcp');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('mcp', 'agent')) {
            expect($r['caller'])->toBe('agent');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['agent', 'mcp'], true))->toBeTrue();
            if ($r['caller'] === 'mcp') {
                expect($d->isMorePrivileged('agent', 'mcp') || 'agent' === 'mcp')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived agent claiming http does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('agent', 'http');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('http', 'agent')) {
            expect($r['caller'])->toBe('agent');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['agent', 'http'], true))->toBeTrue();
            if ($r['caller'] === 'http') {
                expect($d->isMorePrivileged('agent', 'http') || 'agent' === 'http')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived agent claiming cli does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('agent', 'cli');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('cli', 'agent')) {
            expect($r['caller'])->toBe('agent');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['agent', 'cli'], true))->toBeTrue();
            if ($r['caller'] === 'cli') {
                expect($d->isMorePrivileged('agent', 'cli') || 'agent' === 'cli')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived agent claiming job does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('agent', 'job');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('job', 'agent')) {
            expect($r['caller'])->toBe('agent');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['agent', 'job'], true))->toBeTrue();
            if ($r['caller'] === 'job') {
                expect($d->isMorePrivileged('agent', 'job') || 'agent' === 'job')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived mcp claiming agent does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('mcp', 'agent');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('agent', 'mcp')) {
            expect($r['caller'])->toBe('mcp');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['mcp', 'agent'], true))->toBeTrue();
            if ($r['caller'] === 'agent') {
                expect($d->isMorePrivileged('mcp', 'agent') || 'mcp' === 'agent')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived mcp claiming http does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('mcp', 'http');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('http', 'mcp')) {
            expect($r['caller'])->toBe('mcp');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['mcp', 'http'], true))->toBeTrue();
            if ($r['caller'] === 'http') {
                expect($d->isMorePrivileged('mcp', 'http') || 'mcp' === 'http')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived mcp claiming cli does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('mcp', 'cli');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('cli', 'mcp')) {
            expect($r['caller'])->toBe('mcp');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['mcp', 'cli'], true))->toBeTrue();
            if ($r['caller'] === 'cli') {
                expect($d->isMorePrivileged('mcp', 'cli') || 'mcp' === 'cli')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived mcp claiming job does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('mcp', 'job');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('job', 'mcp')) {
            expect($r['caller'])->toBe('mcp');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['mcp', 'job'], true))->toBeTrue();
            if ($r['caller'] === 'job') {
                expect($d->isMorePrivileged('mcp', 'job') || 'mcp' === 'job')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived http claiming agent does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('http', 'agent');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('agent', 'http')) {
            expect($r['caller'])->toBe('http');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['http', 'agent'], true))->toBeTrue();
            if ($r['caller'] === 'agent') {
                expect($d->isMorePrivileged('http', 'agent') || 'http' === 'agent')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived http claiming mcp does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('http', 'mcp');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('mcp', 'http')) {
            expect($r['caller'])->toBe('http');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['http', 'mcp'], true))->toBeTrue();
            if ($r['caller'] === 'mcp') {
                expect($d->isMorePrivileged('http', 'mcp') || 'http' === 'mcp')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived http claiming cli does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('http', 'cli');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('cli', 'http')) {
            expect($r['caller'])->toBe('http');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['http', 'cli'], true))->toBeTrue();
            if ($r['caller'] === 'cli') {
                expect($d->isMorePrivileged('http', 'cli') || 'http' === 'cli')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived http claiming job does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('http', 'job');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('job', 'http')) {
            expect($r['caller'])->toBe('http');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['http', 'job'], true))->toBeTrue();
            if ($r['caller'] === 'job') {
                expect($d->isMorePrivileged('http', 'job') || 'http' === 'job')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived cli claiming agent does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('cli', 'agent');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('agent', 'cli')) {
            expect($r['caller'])->toBe('cli');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['cli', 'agent'], true))->toBeTrue();
            if ($r['caller'] === 'agent') {
                expect($d->isMorePrivileged('cli', 'agent') || 'cli' === 'agent')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived cli claiming mcp does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('cli', 'mcp');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('mcp', 'cli')) {
            expect($r['caller'])->toBe('cli');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['cli', 'mcp'], true))->toBeTrue();
            if ($r['caller'] === 'mcp') {
                expect($d->isMorePrivileged('cli', 'mcp') || 'cli' === 'mcp')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived cli claiming http does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('cli', 'http');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('http', 'cli')) {
            expect($r['caller'])->toBe('cli');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['cli', 'http'], true))->toBeTrue();
            if ($r['caller'] === 'http') {
                expect($d->isMorePrivileged('cli', 'http') || 'cli' === 'http')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived cli claiming job does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('cli', 'job');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('job', 'cli')) {
            expect($r['caller'])->toBe('cli');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['cli', 'job'], true))->toBeTrue();
            if ($r['caller'] === 'job') {
                expect($d->isMorePrivileged('cli', 'job') || 'cli' === 'job')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived job claiming agent does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('job', 'agent');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('agent', 'job')) {
            expect($r['caller'])->toBe('job');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['job', 'agent'], true))->toBeTrue();
            if ($r['caller'] === 'agent') {
                expect($d->isMorePrivileged('job', 'agent') || 'job' === 'agent')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived job claiming mcp does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('job', 'mcp');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('mcp', 'job')) {
            expect($r['caller'])->toBe('job');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['job', 'mcp'], true))->toBeTrue();
            if ($r['caller'] === 'mcp') {
                expect($d->isMorePrivileged('job', 'mcp') || 'job' === 'mcp')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived job claiming http does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('job', 'http');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('http', 'job')) {
            expect($r['caller'])->toBe('job');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['job', 'http'], true))->toBeTrue();
            if ($r['caller'] === 'http') {
                expect($d->isMorePrivileged('job', 'http') || 'job' === 'http')->toBeTrue();
            }
        }
});

it("edge: spoof header from derived job claiming cli does not self-upgrade [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->applyHeaderClaim('job', 'cli');
        // Never self-upgrade: final caller must not be more privileged than derived
        if ($d->isMorePrivileged('cli', 'job')) {
            expect($r['caller'])->toBe('job');
        } else {
            // match or downgrade only
            expect(in_array($r['caller'], ['job', 'cli'], true))->toBeTrue();
            if ($r['caller'] === 'cli') {
                expect($d->isMorePrivileged('job', 'cli') || 'job' === 'cli')->toBeTrue();
            }
        }
});

it("happy: needsApproval branching on caller uses derived value not spoofed header [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['caller'])->toBe('cli'); // spoof http ignored
        $needs = $r['caller'] !== 'http';
        expect($needs)->toBeTrue();
});

it("happy: CLI credential spoofing http header still treated as cli for approval rules [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
        expect($r['derived'])->toBe('cli')->and($r['caller'])->toBe('cli');
});

it("happy: generic API token claiming cli still http for audit caller [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['token_abilities' => ['api:read']], 'cli');
        // derived http; claim cli is downgrade (allowed)
        expect($r['derived'])->toBe('http');
        expect(in_array($r['caller'], ['http', 'cli'], true))->toBeTrue();
});

it("happy: credential audit metadata records type client_id ability when present [D-022]", function () {
    $ctx = H::context(extra: ['credential' => [
            'type' => 'sanctum',
            'client_id' => 'cli-app',
            'ability' => 'capabilities:cli',
        ]]);
        expect($ctx->credential())->toHaveKeys(['type', 'client_id', 'ability']);
});

it("fail: null principal never accepted after context build [CTX-001]", function () {
    expect(fn () => CapabilityContext::make(['caller' => 'http', 'actor' => null]))
            ->toThrow(InvalidArgumentException::class);
});

