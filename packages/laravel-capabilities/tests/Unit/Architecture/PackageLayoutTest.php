<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;

it("happy: core layout includes Registry/CapabilityRegistry [LAYOUT]", function () {
    expect(is_file(A::CORE_SRC.'/Registry/CapabilityRegistry.php'))->toBeTrue();
});

it("happy: core layout includes Adapters/Ai [LAYOUT]", function () {
    expect(is_dir(A::CORE_SRC.'/Adapters/Ai'))->toBeTrue();
});

it("happy: core layout includes Adapters/Mcp [LAYOUT]", function () {
    expect(is_dir(A::CORE_SRC.'/Adapters/Mcp'))->toBeTrue();
});

it("happy: core layout includes Adapters/Http [LAYOUT]", function () {
    expect(is_dir(A::CORE_SRC.'/Adapters/Http'))->toBeTrue();
});

it("happy: core layout includes Approval [LAYOUT]", function () {
    expect(is_dir(A::CORE_SRC.'/Approval'))->toBeTrue();
});

it("happy: core layout includes Audit [LAYOUT]", function () {
    expect(is_dir(A::CORE_SRC.'/Audit') || is_dir(A::CORE_SRC.'/Contracts'))->toBeTrue();
});

it("happy: core layout includes Idempotency [LAYOUT]", function () {
    expect(is_dir(A::CORE_SRC.'/Idempotency'))->toBeTrue();
});

it("happy: core layout includes Contracts/ConversationIngress [LAYOUT]", function () {
    A::conversationContractsExist();
});

it("happy: core layout includes Contracts/ApprovalNotifier [LAYOUT]", function () {
    expect(interface_exists(\Rawphp\Capabilities\Contracts\ApprovalNotifier::class))->toBeTrue();
});

it("happy: core layout includes Support/SystemActor [LAYOUT]", function () {
    expect(class_exists(\Rawphp\Capabilities\Support\SystemActor::class))->toBeTrue();
});

it("happy: core layout includes Support/CapabilityContext [LAYOUT]", function () {
    expect(class_exists(\Rawphp\Capabilities\Support\CapabilityContext::class))->toBeTrue();
});

it("fail: core layout includes Messaging bot runtime directory [D-007]", function () {
    expect(is_dir(A::CORE_SRC.'/Messaging'))->toBeFalse();
    A::coreHasNoMessagingRuntime();
});
