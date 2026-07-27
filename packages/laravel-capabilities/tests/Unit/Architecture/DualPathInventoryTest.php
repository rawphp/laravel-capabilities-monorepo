<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Support\ErrorCodeMap;

it("fail: dual path forbidden: http_controller_domain_create [BELIEF-001]", function () {
    A::assertDualPathForbidden('http_controller_domain_create');
});

it("happy: allowed path uses registry instead of http_controller_domain_create [BELIEF-001]", function () {
    A::assertAllowedPathUsesRegistry('http_controller_domain_create');
});

it("fail: dual path forbidden: ai_tool_domain_create [BELIEF-001]", function () {
    A::assertDualPathForbidden('ai_tool_domain_create');
});

it("happy: allowed path uses registry instead of ai_tool_domain_create [BELIEF-001]", function () {
    A::assertAllowedPathUsesRegistry('ai_tool_domain_create');
});

it("fail: dual path forbidden: mcp_tool_domain_create [BELIEF-001]", function () {
    A::assertDualPathForbidden('mcp_tool_domain_create');
});

it("happy: allowed path uses registry instead of mcp_tool_domain_create [BELIEF-001]", function () {
    A::assertAllowedPathUsesRegistry('mcp_tool_domain_create');
});

it("fail: dual path forbidden: cli_local_domain_create [BELIEF-001]", function () {
    A::assertDualPathForbidden('cli_local_domain_create');
});

it("happy: allowed path uses registry instead of cli_local_domain_create [BELIEF-001]", function () {
    A::assertAllowedPathUsesRegistry('cli_local_domain_create');
});

it("fail: dual path forbidden: job_handle_domain_create [BELIEF-001]", function () {
    A::assertDualPathForbidden('job_handle_domain_create');
});

it("happy: allowed path uses registry instead of job_handle_domain_create [BELIEF-001]", function () {
    A::assertAllowedPathUsesRegistry('job_handle_domain_create');
});

it("fail: dual path forbidden: telegram_adapter_domain_create [BELIEF-001]", function () {
    A::assertDualPathForbidden('telegram_adapter_domain_create');
});

it("happy: allowed path uses registry instead of telegram_adapter_domain_create [BELIEF-001]", function () {
    A::assertAllowedPathUsesRegistry('telegram_adapter_domain_create');
});

it("fail: dual path forbidden: approval_notifier_domain_create [BELIEF-001]", function () {
    A::assertDualPathForbidden('approval_notifier_domain_create');
});

it("happy: allowed path uses registry instead of approval_notifier_domain_create [BELIEF-001]", function () {
    A::assertAllowedPathUsesRegistry('approval_notifier_domain_create');
});

it("fail: dual path forbidden: artisan_command_domain_create [BELIEF-001]", function () {
    A::assertDualPathForbidden('artisan_command_domain_create');
});

it("happy: allowed path uses registry instead of artisan_command_domain_create [BELIEF-001]", function () {
    A::assertAllowedPathUsesRegistry('artisan_command_domain_create');
});

