<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;

it("fail: package is not an LLM client [NONGOAL]", function () {
    A::assertNonGoal('llm_client');
});

it("fail: package is not an MCP protocol implementation [NONGOAL]", function () {
    A::assertNonGoal('mcp_wire_protocol');
});

it("fail: package is not Artisan as product CLI [NONGOAL]", function () {
    A::assertNonGoal('artisan_product_cli');
});

it("fail: package is not a chat UI kit [NONGOAL]", function () {
    A::assertNonGoal('chat_ui');
});

it("fail: package is not Telegram runtime in core [NONGOAL]", function () {
    A::assertNonGoal('telegram_runtime_core');
});

it("fail: package is not A2A mesh runtime [NONGOAL]", function () {
    A::assertNonGoal('a2a_mesh');
});

it("fail: package is not a replacement for controllers Form Requests domain services [NONGOAL]", function () {
    A::assertNonGoal('controller_replacement');
});

it("fail: package is not agent-native full messaging OS [NONGOAL]", function () {
    A::assertNonGoal('messaging_os');
});
