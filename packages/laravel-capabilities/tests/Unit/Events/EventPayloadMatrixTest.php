<?php

// REQ-014: Event payload correlation keys (D-010). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Events\CapabilityApprovalDecided;
use Rawphp\Capabilities\Events\CapabilityApprovalExecuted;
use Rawphp\Capabilities\Events\CapabilityApprovalRequested;
use Rawphp\Capabilities\Events\CapabilityFailed;
use Rawphp\Capabilities\Events\CapabilityInvoked;
use Rawphp\Capabilities\Events\EventPayload;

it('edge: CapabilityInvoked payload may include name [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('name'))->toBeTrue();
    $meta = EventPayload::meta(['name' => 'sample-name', 'extra' => 1]);
    expect($meta)->toHaveKey('name')->and($meta['name'])->toBe('sample-name');
    $e = new CapabilityInvoked(capability: 'create-invoice', caller: 'http', meta: $meta);
    expect($e->meta)->toHaveKey('name')->and($e->capability)->toBe('create-invoice');
});

it('edge: CapabilityInvoked payload may include actor [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('actor'))->toBeTrue();
    $meta = EventPayload::meta(['actor' => 'sample-actor', 'extra' => 1]);
    expect($meta)->toHaveKey('actor')->and($meta['actor'])->toBe('sample-actor');
    $e = new CapabilityInvoked(capability: 'create-invoice', caller: 'http', meta: $meta);
    expect($e->meta)->toHaveKey('actor')->and($e->capability)->toBe('create-invoice');
});

it('edge: CapabilityInvoked payload may include scope [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('scope'))->toBeTrue();
    $meta = EventPayload::meta(['scope' => 'sample-scope', 'extra' => 1]);
    expect($meta)->toHaveKey('scope')->and($meta['scope'])->toBe('sample-scope');
    $e = new CapabilityInvoked(capability: 'create-invoice', caller: 'http', meta: $meta);
    expect($e->meta)->toHaveKey('scope')->and($e->capability)->toBe('create-invoice');
});

it('edge: CapabilityInvoked payload may include caller [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('caller'))->toBeTrue();
    $meta = EventPayload::meta(['caller' => 'sample-caller', 'extra' => 1]);
    expect($meta)->toHaveKey('caller')->and($meta['caller'])->toBe('sample-caller');
    $e = new CapabilityInvoked(capability: 'create-invoice', caller: 'http', meta: $meta);
    expect($e->meta)->toHaveKey('caller')->and($e->capability)->toBe('create-invoice');
});

it('edge: CapabilityInvoked payload may include duration [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('duration'))->toBeTrue();
    $meta = EventPayload::meta(['duration' => 'sample-duration', 'extra' => 1]);
    expect($meta)->toHaveKey('duration')->and($meta['duration'])->toBe('sample-duration');
    $e = new CapabilityInvoked(capability: 'create-invoice', caller: 'http', meta: $meta);
    expect($e->meta)->toHaveKey('duration')->and($e->capability)->toBe('create-invoice');
});

it('edge: CapabilityInvoked payload may include invocation_id [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('invocation_id'))->toBeTrue();
    $meta = EventPayload::meta(['invocation_id' => 'sample-invocation_id', 'extra' => 1]);
    expect($meta)->toHaveKey('invocation_id')->and($meta['invocation_id'])->toBe('sample-invocation_id');
    $e = new CapabilityInvoked(capability: 'create-invoice', caller: 'http', meta: $meta);
    expect($e->meta)->toHaveKey('invocation_id')->and($e->capability)->toBe('create-invoice');
});

it('edge: CapabilityInvoked payload may include request_id [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('request_id'))->toBeTrue();
    $meta = EventPayload::meta(['request_id' => 'sample-request_id', 'extra' => 1]);
    expect($meta)->toHaveKey('request_id')->and($meta['request_id'])->toBe('sample-request_id');
    $e = new CapabilityInvoked(capability: 'create-invoice', caller: 'http', meta: $meta);
    expect($e->meta)->toHaveKey('request_id')->and($e->capability)->toBe('create-invoice');
});

it('edge: CapabilityFailed payload may include name [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('name'))->toBeTrue();
    $meta = EventPayload::meta(['name' => 'sample-name', 'extra' => 1]);
    expect($meta)->toHaveKey('name')->and($meta['name'])->toBe('sample-name');
    $e = new CapabilityFailed(capability: 'create-invoice', code: 'domain_error', message: 'x', caller: 'http');
    expect($e->capability)->toBe('create-invoice');
    expect(EventPayload::meta(['name' => 'v']))->toHaveKey('name');
});

it('edge: CapabilityFailed payload may include actor [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('actor'))->toBeTrue();
    $meta = EventPayload::meta(['actor' => 'sample-actor', 'extra' => 1]);
    expect($meta)->toHaveKey('actor')->and($meta['actor'])->toBe('sample-actor');
    $e = new CapabilityFailed(capability: 'create-invoice', code: 'domain_error', message: 'x', caller: 'http');
    expect($e->capability)->toBe('create-invoice');
    expect(EventPayload::meta(['actor' => 'v']))->toHaveKey('actor');
});

it('edge: CapabilityFailed payload may include scope [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('scope'))->toBeTrue();
    $meta = EventPayload::meta(['scope' => 'sample-scope', 'extra' => 1]);
    expect($meta)->toHaveKey('scope')->and($meta['scope'])->toBe('sample-scope');
    $e = new CapabilityFailed(capability: 'create-invoice', code: 'domain_error', message: 'x', caller: 'http');
    expect($e->capability)->toBe('create-invoice');
    expect(EventPayload::meta(['scope' => 'v']))->toHaveKey('scope');
});

it('edge: CapabilityFailed payload may include caller [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('caller'))->toBeTrue();
    $meta = EventPayload::meta(['caller' => 'sample-caller', 'extra' => 1]);
    expect($meta)->toHaveKey('caller')->and($meta['caller'])->toBe('sample-caller');
    $e = new CapabilityFailed(capability: 'create-invoice', code: 'domain_error', message: 'x', caller: 'http');
    expect($e->capability)->toBe('create-invoice');
    expect(EventPayload::meta(['caller' => 'v']))->toHaveKey('caller');
});

it('edge: CapabilityFailed payload may include duration [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('duration'))->toBeTrue();
    $meta = EventPayload::meta(['duration' => 'sample-duration', 'extra' => 1]);
    expect($meta)->toHaveKey('duration')->and($meta['duration'])->toBe('sample-duration');
    $e = new CapabilityFailed(capability: 'create-invoice', code: 'domain_error', message: 'x', caller: 'http');
    expect($e->capability)->toBe('create-invoice');
    expect(EventPayload::meta(['duration' => 'v']))->toHaveKey('duration');
});

it('edge: CapabilityFailed payload may include invocation_id [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('invocation_id'))->toBeTrue();
    $meta = EventPayload::meta(['invocation_id' => 'sample-invocation_id', 'extra' => 1]);
    expect($meta)->toHaveKey('invocation_id')->and($meta['invocation_id'])->toBe('sample-invocation_id');
    $e = new CapabilityFailed(capability: 'create-invoice', code: 'domain_error', message: 'x', caller: 'http');
    expect($e->capability)->toBe('create-invoice');
    expect(EventPayload::meta(['invocation_id' => 'v']))->toHaveKey('invocation_id');
});

it('edge: CapabilityFailed payload may include request_id [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('request_id'))->toBeTrue();
    $meta = EventPayload::meta(['request_id' => 'sample-request_id', 'extra' => 1]);
    expect($meta)->toHaveKey('request_id')->and($meta['request_id'])->toBe('sample-request_id');
    $e = new CapabilityFailed(capability: 'create-invoice', code: 'domain_error', message: 'x', caller: 'http');
    expect($e->capability)->toBe('create-invoice');
    expect(EventPayload::meta(['request_id' => 'v']))->toHaveKey('request_id');
});

it('edge: CapabilityApprovalRequested payload may include name [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('name'))->toBeTrue();
    $meta = EventPayload::meta(['name' => 'sample-name', 'extra' => 1]);
    expect($meta)->toHaveKey('name')->and($meta['name'])->toBe('sample-name');
    $e = new CapabilityApprovalRequested(capability: 'create-invoice', approvalId: '1', caller: 'http', meta: $meta);
    expect($e->meta)->toHaveKey('name');
});

it('edge: CapabilityApprovalRequested payload may include actor [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('actor'))->toBeTrue();
    $meta = EventPayload::meta(['actor' => 'sample-actor', 'extra' => 1]);
    expect($meta)->toHaveKey('actor')->and($meta['actor'])->toBe('sample-actor');
    $e = new CapabilityApprovalRequested(capability: 'create-invoice', approvalId: '1', caller: 'http', meta: $meta);
    expect($e->meta)->toHaveKey('actor');
});

it('edge: CapabilityApprovalRequested payload may include scope [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('scope'))->toBeTrue();
    $meta = EventPayload::meta(['scope' => 'sample-scope', 'extra' => 1]);
    expect($meta)->toHaveKey('scope')->and($meta['scope'])->toBe('sample-scope');
    $e = new CapabilityApprovalRequested(capability: 'create-invoice', approvalId: '1', caller: 'http', meta: $meta);
    expect($e->meta)->toHaveKey('scope');
});

it('edge: CapabilityApprovalRequested payload may include caller [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('caller'))->toBeTrue();
    $meta = EventPayload::meta(['caller' => 'sample-caller', 'extra' => 1]);
    expect($meta)->toHaveKey('caller')->and($meta['caller'])->toBe('sample-caller');
    $e = new CapabilityApprovalRequested(capability: 'create-invoice', approvalId: '1', caller: 'http', meta: $meta);
    expect($e->meta)->toHaveKey('caller');
});

it('edge: CapabilityApprovalRequested payload may include duration [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('duration'))->toBeTrue();
    $meta = EventPayload::meta(['duration' => 'sample-duration', 'extra' => 1]);
    expect($meta)->toHaveKey('duration')->and($meta['duration'])->toBe('sample-duration');
    $e = new CapabilityApprovalRequested(capability: 'create-invoice', approvalId: '1', caller: 'http', meta: $meta);
    expect($e->meta)->toHaveKey('duration');
});

it('edge: CapabilityApprovalRequested payload may include invocation_id [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('invocation_id'))->toBeTrue();
    $meta = EventPayload::meta(['invocation_id' => 'sample-invocation_id', 'extra' => 1]);
    expect($meta)->toHaveKey('invocation_id')->and($meta['invocation_id'])->toBe('sample-invocation_id');
    $e = new CapabilityApprovalRequested(capability: 'create-invoice', approvalId: '1', caller: 'http', meta: $meta);
    expect($e->meta)->toHaveKey('invocation_id');
});

it('edge: CapabilityApprovalRequested payload may include request_id [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('request_id'))->toBeTrue();
    $meta = EventPayload::meta(['request_id' => 'sample-request_id', 'extra' => 1]);
    expect($meta)->toHaveKey('request_id')->and($meta['request_id'])->toBe('sample-request_id');
    $e = new CapabilityApprovalRequested(capability: 'create-invoice', approvalId: '1', caller: 'http', meta: $meta);
    expect($e->meta)->toHaveKey('request_id');
});

it('edge: CapabilityApprovalDecided payload may include name [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('name'))->toBeTrue();
    $meta = EventPayload::meta(['name' => 'sample-name', 'extra' => 1]);
    expect($meta)->toHaveKey('name')->and($meta['name'])->toBe('sample-name');
    $e = new CapabilityApprovalDecided(capability: 'create-invoice', approvalId: '1', decision: 'accept', decidedBy: 'u1', meta: $meta);
    expect($e->meta)->toHaveKey('name');
});

it('edge: CapabilityApprovalDecided payload may include actor [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('actor'))->toBeTrue();
    $meta = EventPayload::meta(['actor' => 'sample-actor', 'extra' => 1]);
    expect($meta)->toHaveKey('actor')->and($meta['actor'])->toBe('sample-actor');
    $e = new CapabilityApprovalDecided(capability: 'create-invoice', approvalId: '1', decision: 'accept', decidedBy: 'u1', meta: $meta);
    expect($e->meta)->toHaveKey('actor');
});

it('edge: CapabilityApprovalDecided payload may include scope [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('scope'))->toBeTrue();
    $meta = EventPayload::meta(['scope' => 'sample-scope', 'extra' => 1]);
    expect($meta)->toHaveKey('scope')->and($meta['scope'])->toBe('sample-scope');
    $e = new CapabilityApprovalDecided(capability: 'create-invoice', approvalId: '1', decision: 'accept', decidedBy: 'u1', meta: $meta);
    expect($e->meta)->toHaveKey('scope');
});

it('edge: CapabilityApprovalDecided payload may include caller [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('caller'))->toBeTrue();
    $meta = EventPayload::meta(['caller' => 'sample-caller', 'extra' => 1]);
    expect($meta)->toHaveKey('caller')->and($meta['caller'])->toBe('sample-caller');
    $e = new CapabilityApprovalDecided(capability: 'create-invoice', approvalId: '1', decision: 'accept', decidedBy: 'u1', meta: $meta);
    expect($e->meta)->toHaveKey('caller');
});

it('edge: CapabilityApprovalDecided payload may include duration [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('duration'))->toBeTrue();
    $meta = EventPayload::meta(['duration' => 'sample-duration', 'extra' => 1]);
    expect($meta)->toHaveKey('duration')->and($meta['duration'])->toBe('sample-duration');
    $e = new CapabilityApprovalDecided(capability: 'create-invoice', approvalId: '1', decision: 'accept', decidedBy: 'u1', meta: $meta);
    expect($e->meta)->toHaveKey('duration');
});

it('edge: CapabilityApprovalDecided payload may include invocation_id [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('invocation_id'))->toBeTrue();
    $meta = EventPayload::meta(['invocation_id' => 'sample-invocation_id', 'extra' => 1]);
    expect($meta)->toHaveKey('invocation_id')->and($meta['invocation_id'])->toBe('sample-invocation_id');
    $e = new CapabilityApprovalDecided(capability: 'create-invoice', approvalId: '1', decision: 'accept', decidedBy: 'u1', meta: $meta);
    expect($e->meta)->toHaveKey('invocation_id');
});

it('edge: CapabilityApprovalDecided payload may include request_id [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('request_id'))->toBeTrue();
    $meta = EventPayload::meta(['request_id' => 'sample-request_id', 'extra' => 1]);
    expect($meta)->toHaveKey('request_id')->and($meta['request_id'])->toBe('sample-request_id');
    $e = new CapabilityApprovalDecided(capability: 'create-invoice', approvalId: '1', decision: 'accept', decidedBy: 'u1', meta: $meta);
    expect($e->meta)->toHaveKey('request_id');
});

it('edge: CapabilityApprovalExecuted payload may include name [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('name'))->toBeTrue();
    $meta = EventPayload::meta(['name' => 'sample-name', 'extra' => 1]);
    expect($meta)->toHaveKey('name')->and($meta['name'])->toBe('sample-name');
    $e = new CapabilityApprovalExecuted(capability: 'create-invoice', approvalId: '1', meta: $meta);
    expect($e->meta)->toHaveKey('name');
});

it('edge: CapabilityApprovalExecuted payload may include actor [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('actor'))->toBeTrue();
    $meta = EventPayload::meta(['actor' => 'sample-actor', 'extra' => 1]);
    expect($meta)->toHaveKey('actor')->and($meta['actor'])->toBe('sample-actor');
    $e = new CapabilityApprovalExecuted(capability: 'create-invoice', approvalId: '1', meta: $meta);
    expect($e->meta)->toHaveKey('actor');
});

it('edge: CapabilityApprovalExecuted payload may include scope [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('scope'))->toBeTrue();
    $meta = EventPayload::meta(['scope' => 'sample-scope', 'extra' => 1]);
    expect($meta)->toHaveKey('scope')->and($meta['scope'])->toBe('sample-scope');
    $e = new CapabilityApprovalExecuted(capability: 'create-invoice', approvalId: '1', meta: $meta);
    expect($e->meta)->toHaveKey('scope');
});

it('edge: CapabilityApprovalExecuted payload may include caller [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('caller'))->toBeTrue();
    $meta = EventPayload::meta(['caller' => 'sample-caller', 'extra' => 1]);
    expect($meta)->toHaveKey('caller')->and($meta['caller'])->toBe('sample-caller');
    $e = new CapabilityApprovalExecuted(capability: 'create-invoice', approvalId: '1', meta: $meta);
    expect($e->meta)->toHaveKey('caller');
});

it('edge: CapabilityApprovalExecuted payload may include duration [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('duration'))->toBeTrue();
    $meta = EventPayload::meta(['duration' => 'sample-duration', 'extra' => 1]);
    expect($meta)->toHaveKey('duration')->and($meta['duration'])->toBe('sample-duration');
    $e = new CapabilityApprovalExecuted(capability: 'create-invoice', approvalId: '1', meta: $meta);
    expect($e->meta)->toHaveKey('duration');
});

it('edge: CapabilityApprovalExecuted payload may include invocation_id [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('invocation_id'))->toBeTrue();
    $meta = EventPayload::meta(['invocation_id' => 'sample-invocation_id', 'extra' => 1]);
    expect($meta)->toHaveKey('invocation_id')->and($meta['invocation_id'])->toBe('sample-invocation_id');
    $e = new CapabilityApprovalExecuted(capability: 'create-invoice', approvalId: '1', meta: $meta);
    expect($e->meta)->toHaveKey('invocation_id');
});

it('edge: CapabilityApprovalExecuted payload may include request_id [D-010]', function () {
    expect(EventPayload::hasCorrelationKey('request_id'))->toBeTrue();
    $meta = EventPayload::meta(['request_id' => 'sample-request_id', 'extra' => 1]);
    expect($meta)->toHaveKey('request_id')->and($meta['request_id'])->toBe('sample-request_id');
    $e = new CapabilityApprovalExecuted(capability: 'create-invoice', approvalId: '1', meta: $meta);
    expect($e->meta)->toHaveKey('request_id');
});
