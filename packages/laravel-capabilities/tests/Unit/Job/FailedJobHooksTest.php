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


it("edge: failed job tagged with capability [D-019]", function () {
    $job = new RunCapabilityJob(name: 'cap-x', actingAs: SystemActor::named('scheduler'), tenantId: 't1');
        expect($job->failureTags()['capability'])->toBe('cap-x');
});

it("edge: failed job tagged with caller [D-019]", function () {
    $job = new RunCapabilityJob(name: 'cap-x', actingAs: 1, tenantId: 't1');
        expect($job->failureTags()['caller'])->toBe('job');
});

it("edge: failed job tagged with actor_type [D-019]", function () {
    $job = new RunCapabilityJob(name: 'cap-x', actingAs: SystemActor::named('scheduler'));
        expect($job->failureTags()['actor_type'])->toBe('system');
});

it("edge: failed job tagged with tenant_id [D-019]", function () {
    $job = new RunCapabilityJob(name: 'cap-x', actingAs: 1, tenantId: 'tenant-z');
        expect($job->failureTags()['tenant_id'])->toBe('tenant-z');
});

it("happy: RunCapability uses Laravel failed-job hooks [D-019]", function () {
    $job = new RunCapabilityJob(name: 'cap-x', actingAs: SystemActor::named('scheduler'), tenantId: 't');
        $tags = $job->failureTags();
        expect($tags)->toHaveKeys(['capability', 'caller', 'actor_type', 'tenant_id']);
});

