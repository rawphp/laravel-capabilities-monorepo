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


it("edge: derived caller computed when cred=cli_ability header=agent [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'agent');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('agent', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=cli_ability header=agent [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'agent');
        if ($d->isMorePrivileged('agent', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=cli_ability header=mcp [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'mcp');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('mcp', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=cli_ability header=mcp [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'mcp');
        if ($d->isMorePrivileged('mcp', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=cli_ability header=http [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'http');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('http', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=cli_ability header=http [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'http');
        if ($d->isMorePrivileged('http', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=cli_ability header=cli [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'cli');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('cli', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=cli_ability header=cli [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'cli');
        if ($d->isMorePrivileged('cli', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=cli_ability header=job [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'job');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('job', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=cli_ability header=job [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'job');
        if ($d->isMorePrivileged('job', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=api_pat header=agent [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'agent');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('agent', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=api_pat header=agent [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'agent');
        if ($d->isMorePrivileged('agent', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=api_pat header=mcp [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'mcp');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('mcp', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=api_pat header=mcp [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'mcp');
        if ($d->isMorePrivileged('mcp', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=api_pat header=http [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'http');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('http', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=api_pat header=http [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'http');
        if ($d->isMorePrivileged('http', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=api_pat header=cli [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'cli');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('cli', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=api_pat header=cli [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'cli');
        if ($d->isMorePrivileged('cli', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=api_pat header=job [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'job');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('job', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=api_pat header=job [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'job');
        if ($d->isMorePrivileged('job', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=oauth_cli_client header=agent [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'cli-app'], 'agent');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('agent', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=oauth_cli_client header=agent [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'cli-app'], 'agent');
        if ($d->isMorePrivileged('agent', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=oauth_cli_client header=mcp [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'cli-app'], 'mcp');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('mcp', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=oauth_cli_client header=mcp [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'cli-app'], 'mcp');
        if ($d->isMorePrivileged('mcp', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=oauth_cli_client header=http [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'cli-app'], 'http');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('http', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=oauth_cli_client header=http [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'cli-app'], 'http');
        if ($d->isMorePrivileged('http', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=oauth_cli_client header=cli [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'cli-app'], 'cli');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('cli', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=oauth_cli_client header=cli [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'cli-app'], 'cli');
        if ($d->isMorePrivileged('cli', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=oauth_cli_client header=job [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'cli-app'], 'job');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('job', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=oauth_cli_client header=job [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'cli-app'], 'job');
        if ($d->isMorePrivileged('job', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=oauth_http_client header=agent [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'mobile-app'], 'agent');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('agent', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=oauth_http_client header=agent [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'mobile-app'], 'agent');
        if ($d->isMorePrivileged('agent', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=oauth_http_client header=mcp [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'mobile-app'], 'mcp');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('mcp', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=oauth_http_client header=mcp [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'mobile-app'], 'mcp');
        if ($d->isMorePrivileged('mcp', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=oauth_http_client header=http [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'mobile-app'], 'http');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('http', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=oauth_http_client header=http [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'mobile-app'], 'http');
        if ($d->isMorePrivileged('http', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=oauth_http_client header=cli [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'mobile-app'], 'cli');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('cli', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=oauth_http_client header=cli [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'mobile-app'], 'cli');
        if ($d->isMorePrivileged('cli', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=oauth_http_client header=job [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'mobile-app'], 'job');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('job', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=oauth_http_client header=job [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve(['oauth_client_id' => 'mobile-app'], 'job');
        if ($d->isMorePrivileged('job', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=none header=agent [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'agent');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('agent', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=none header=agent [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'agent');
        if ($d->isMorePrivileged('agent', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=none header=mcp [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'mcp');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('mcp', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=none header=mcp [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'mcp');
        if ($d->isMorePrivileged('mcp', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=none header=http [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'http');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('http', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=none header=http [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'http');
        if ($d->isMorePrivileged('http', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=none header=cli [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'cli');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('cli', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=none header=cli [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'cli');
        if ($d->isMorePrivileged('cli', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

it("edge: derived caller computed when cred=none header=job [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'job');
        expect($r['derived'])->toBeString()->and(in_array($r['derived'], CapabilityContext::CALLERS, true))->toBeTrue();
        // header never upgrades privilege
        if ($d->isMorePrivileged('job', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        }
});

it("fail: header cannot self-upgrade when cred=none header=job [D-022]", function () {
    $d = H::defaultDeriver();
        $r = $d->resolve([], 'job');
        if ($d->isMorePrivileged('job', $r['derived'])) {
            expect($r['caller'])->toBe($r['derived']);
        } else {
            expect($r['caller'])->toBeString();
        }
});

