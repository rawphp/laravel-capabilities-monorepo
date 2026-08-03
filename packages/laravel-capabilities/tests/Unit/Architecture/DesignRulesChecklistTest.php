<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;

it('happy: design rule enforced: one run [DESIGN]', function () {
    A::assertDesignRule('one run');
});

it('fail: design rule violation refused: one run [DESIGN]', function () {
    A::assertDesignRuleViolationRefused('one run');
});

it('happy: design rule enforced: adapters are dumb [DESIGN]', function () {
    A::assertDesignRule('adapters are dumb');
});

it('fail: design rule violation refused: adapters are dumb [DESIGN]', function () {
    A::assertDesignRuleViolationRefused('adapters are dumb');
});

it('happy: design rule enforced: domain stays yours [DESIGN]', function () {
    A::assertDesignRule('domain stays yours');
});

it('fail: design rule violation refused: domain stays yours [DESIGN]', function () {
    A::assertDesignRuleViolationRefused('domain stays yours');
});

it('happy: design rule enforced: global surface switches then per-capability narrowing [DESIGN]', function () {
    A::assertDesignRule('global surface switches then per-capability narrowing');
});

it('fail: design rule violation refused: global surface switches then per-capability narrowing [DESIGN]', function () {
    A::assertDesignRuleViolationRefused('global surface switches then per-capability narrowing');
});

it('happy: design rule enforced: fail closed [DESIGN]', function () {
    A::assertDesignRule('fail closed');
});

it('fail: design rule violation refused: fail closed [DESIGN]', function () {
    A::assertDesignRuleViolationRefused('fail closed');
});

it('happy: design rule enforced: conversation not invoke [DESIGN]', function () {
    A::assertDesignRule('conversation not invoke');
});

it('fail: design rule violation refused: conversation not invoke [DESIGN]', function () {
    A::assertDesignRuleViolationRefused('conversation not invoke');
});

it('happy: design rule enforced: jobs declare actor [DESIGN]', function () {
    A::assertDesignRule('jobs declare actor');
});

it('fail: design rule violation refused: jobs declare actor [DESIGN]', function () {
    A::assertDesignRuleViolationRefused('jobs declare actor');
});

it('happy: design rule enforced: resources re-resolved under scope [DESIGN]', function () {
    A::assertDesignRule('resources re-resolved under scope');
});

it('fail: design rule violation refused: resources re-resolved under scope [DESIGN]', function () {
    A::assertDesignRuleViolationRefused('resources re-resolved under scope');
});

it('happy: design rule enforced: mutating invokes support idempotency [DESIGN]', function () {
    A::assertDesignRule('mutating invokes support idempotency');
});

it('fail: design rule violation refused: mutating invokes support idempotency [DESIGN]', function () {
    A::assertDesignRuleViolationRefused('mutating invokes support idempotency');
});

it('happy: design rule enforced: approvals state machine with crash recovery [DESIGN]', function () {
    A::assertDesignRule('approvals state machine with crash recovery');
});

it('fail: design rule violation refused: approvals state machine with crash recovery [DESIGN]', function () {
    A::assertDesignRuleViolationRefused('approvals state machine with crash recovery');
});

it('happy: design rule enforced: messaging sibling package [DESIGN]', function () {
    A::assertDesignRule('messaging sibling package');
});

it('fail: design rule violation refused: messaging sibling package [DESIGN]', function () {
    A::assertDesignRuleViolationRefused('messaging sibling package');
});

it('happy: design rule enforced: agents get tool groups not full catalog [DESIGN]', function () {
    A::assertDesignRule('agents get tool groups not full catalog');
});

it('fail: design rule violation refused: agents get tool groups not full catalog [DESIGN]', function () {
    A::assertDesignRuleViolationRefused('agents get tool groups not full catalog');
});

it('happy: design rule enforced: one HTTP capability API [DESIGN]', function () {
    A::assertDesignRule('one HTTP capability API');
});

it('fail: design rule violation refused: one HTTP capability API [DESIGN]', function () {
    A::assertDesignRuleViolationRefused('one HTTP capability API');
});

it('happy: design rule enforced: transactions and audit explicit [DESIGN]', function () {
    A::assertDesignRule('transactions and audit explicit');
});

it('fail: design rule violation refused: transactions and audit explicit [DESIGN]', function () {
    A::assertDesignRuleViolationRefused('transactions and audit explicit');
});

it('happy: design rule enforced: peer adapters versioned and tested [DESIGN]', function () {
    A::assertDesignRule('peer adapters versioned and tested');
});

it('fail: design rule violation refused: peer adapters versioned and tested [DESIGN]', function () {
    A::assertDesignRuleViolationRefused('peer adapters versioned and tested');
});

it('happy: design rule enforced: names errors DTOs CLI language decided [DESIGN]', function () {
    A::assertDesignRule('names errors DTOs CLI language decided');
});

it('fail: design rule violation refused: names errors DTOs CLI language decided [DESIGN]', function () {
    A::assertDesignRuleViolationRefused('names errors DTOs CLI language decided');
});

it('happy: design rule enforced: caller derived not spoofable [DESIGN]', function () {
    A::assertDesignRule('caller derived not spoofable');
});

it('fail: design rule violation refused: caller derived not spoofable [DESIGN]', function () {
    A::assertDesignRuleViolationRefused('caller derived not spoofable');
});

it('happy: design rule enforced: MCP principals explicit auth profiles [DESIGN]', function () {
    A::assertDesignRule('MCP principals explicit auth profiles');
});

it('fail: design rule violation refused: MCP principals explicit auth profiles [DESIGN]', function () {
    A::assertDesignRuleViolationRefused('MCP principals explicit auth profiles');
});
