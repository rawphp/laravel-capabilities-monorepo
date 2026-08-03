<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;

it('fail: core is not llm_client [NONGOAL]', function () {
    A::assertNonGoal('llm_client');
});

it('happy: tests guard against becoming llm_client [NONGOAL]', function () {
    A::assertNonGoal('llm_client');
});

it('fail: core is not mcp_wire_protocol [NONGOAL]', function () {
    A::assertNonGoal('mcp_wire_protocol');
});

it('happy: tests guard against becoming mcp_wire_protocol [NONGOAL]', function () {
    A::assertNonGoal('mcp_wire_protocol');
});

it('fail: core is not artisan_product_cli [NONGOAL]', function () {
    A::assertNonGoal('artisan_product_cli');
});

it('happy: tests guard against becoming artisan_product_cli [NONGOAL]', function () {
    A::assertNonGoal('artisan_product_cli');
});

it('fail: core is not chat_ui [NONGOAL]', function () {
    A::assertNonGoal('chat_ui');
});

it('happy: tests guard against becoming chat_ui [NONGOAL]', function () {
    A::assertNonGoal('chat_ui');
});

it('fail: core is not telegram_runtime_core [NONGOAL]', function () {
    A::assertNonGoal('telegram_runtime_core');
});

it('happy: tests guard against becoming telegram_runtime_core [NONGOAL]', function () {
    A::assertNonGoal('telegram_runtime_core');
});

it('fail: core is not a2a_mesh [NONGOAL]', function () {
    A::assertNonGoal('a2a_mesh');
});

it('happy: tests guard against becoming a2a_mesh [NONGOAL]', function () {
    A::assertNonGoal('a2a_mesh');
});

it('fail: core is not controller_replacement [NONGOAL]', function () {
    A::assertNonGoal('controller_replacement');
});

it('happy: tests guard against becoming controller_replacement [NONGOAL]', function () {
    A::assertNonGoal('controller_replacement');
});

it('fail: core is not messaging_os [NONGOAL]', function () {
    A::assertNonGoal('messaging_os');
});

it('happy: tests guard against becoming messaging_os [NONGOAL]', function () {
    A::assertNonGoal('messaging_os');
});
