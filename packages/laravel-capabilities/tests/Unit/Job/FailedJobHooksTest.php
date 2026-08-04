<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\RunCapabilityJob;
use Rawphp\Capabilities\Support\SystemActor;

it('edge: failed job tagged with capability [D-019]', function () {
    $job = new RunCapabilityJob(name: 'cap-x', actingAs: SystemActor::named('scheduler'), tenantId: 't1');
    expect($job->failureTags()['capability'])->toBe('cap-x');
});

it('edge: failed job tagged with caller [D-019]', function () {
    $job = new RunCapabilityJob(name: 'cap-x', actingAs: 1, tenantId: 't1');
    expect($job->failureTags()['caller'])->toBe('job');
});

it('edge: failed job tagged with actor_type [D-019]', function () {
    $job = new RunCapabilityJob(name: 'cap-x', actingAs: SystemActor::named('scheduler'));
    expect($job->failureTags()['actor_type'])->toBe('system');
});

it('edge: failed job tagged with tenant_id [D-019]', function () {
    $job = new RunCapabilityJob(name: 'cap-x', actingAs: 1, tenantId: 'tenant-z');
    expect($job->failureTags()['tenant_id'])->toBe('tenant-z');
});

it('happy: RunCapability uses Laravel failed-job hooks [D-019]', function () {
    $job = new RunCapabilityJob(name: 'cap-x', actingAs: SystemActor::named('scheduler'), tenantId: 't');
    $tags = $job->failureTags();
    expect($tags)->toHaveKeys(['capability', 'caller', 'actor_type', 'tenant_id']);
});
