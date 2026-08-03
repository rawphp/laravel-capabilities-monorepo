<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

it('edge: policy requester decision for actor requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'requester']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('requester', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(true);
});

it('edge: policy requester decision for actor role_holder [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'requester']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('role_holder', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(false);
});

it('fail: policy requester denies random_user [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'requester']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('random_user', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(false);
});

it('fail: policy requester denies system_actor [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'requester']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('system_actor', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(false);
});

it('fail: policy requester denies other_tenant_user [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'requester']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('other_tenant_user', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(false);
});

it('edge: policy requester_or_role decision for actor requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'requester_or_role']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('requester', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(true);
});

it('edge: policy requester_or_role decision for actor role_holder [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'requester_or_role']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('role_holder', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(true);
});

it('edge: policy requester_or_role decision for actor random_user [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'requester_or_role']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('random_user', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(false);
});

it('fail: policy requester_or_role denies system_actor [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'requester_or_role']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('system_actor', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(false);
});

it('fail: policy requester_or_role denies other_tenant_user [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'requester_or_role']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('other_tenant_user', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(false);
});

it('fail: policy role:finance-approver denies requester self-approve [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'role:finance-approver']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('requester', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(false);
});

it('edge: policy role:finance-approver decision for actor role_holder [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'role:finance-approver']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('role_holder', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(true);
});

it('fail: policy role:finance-approver denies random_user [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'role:finance-approver']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('random_user', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(false);
});

it('fail: policy role:finance-approver denies system_actor [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'role:finance-approver']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('system_actor', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(false);
});

it('fail: policy role:finance-approver denies other_tenant_user [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'role:finance-approver']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('other_tenant_user', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(false);
});

it('edge: policy any_staff decision for actor requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'any_staff']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('requester', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(true);
});

it('edge: policy any_staff decision for actor role_holder [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'any_staff']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('role_holder', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(true);
});

it('edge: policy any_staff decision for actor random_user [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'any_staff']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('random_user', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(true);
});

it('fail: policy any_staff denies system_actor [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'any_staff']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('system_actor', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(false);
});

it('fail: policy any_staff denies other_tenant_user [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'any_staff']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('other_tenant_user', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(false);
});

it('edge: policy custom decision for actor requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'custom']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('requester', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(true);
});

it('edge: policy custom decision for actor role_holder [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'custom']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('role_holder', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(true);
});

it('edge: policy custom decision for actor random_user [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'custom']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('random_user', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(true);
});

it('fail: policy custom denies system_actor [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'custom']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('system_actor', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(false);
});

it('fail: policy custom denies other_tenant_user [D-006]', function () {
    $h = ApprovalHelpers::harness(['policy' => 'custom']);
    $row = ApprovalHelpers::pendingRecord(['requester_actor_id' => '7', 'tenant_id' => 't-1']);
    $actor = ApprovalHelpers::actorFor('other_tenant_user', $row);
    $tenant = $actor instanceof SystemActor ? null : ($actor->tenant_id ?? null);
    expect($h['policy']->allows($row, $actor, is_string($tenant) ? $tenant : null))->toBe(false);
});
