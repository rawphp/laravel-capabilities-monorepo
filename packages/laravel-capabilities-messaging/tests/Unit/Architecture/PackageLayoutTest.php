<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('happy: messaging layout includes Telegram [LAYOUT]', function () {
    expect(is_dir(H::MSG_SRC.'/Telegram'))->toBeTrue();
});

it('happy: messaging layout includes Identity [LAYOUT]', function () {
    expect(is_dir(H::MSG_SRC.'/Identity'))->toBeTrue();
});

it('happy: messaging layout includes Threads [LAYOUT]', function () {
    expect(is_dir(H::MSG_SRC.'/Threads'))->toBeTrue();
});

it('happy: messaging layout includes Notifiers [LAYOUT]', function () {
    expect(is_dir(H::MSG_SRC.'/Notifiers'))->toBeTrue();
});

it('fail: messaging layout reimplements registry pipeline [D-007]', function () {
    $hits = H::scanSource('/class\s+CapabilityRegistry\b/');
    expect($hits)->toBeEmpty();
    $pipe = H::scanSource('/PipelineStages|stageJsonSchemaValidate/');
    expect($pipe)->toBeEmpty();
});
