<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('fail: no tests/Feature directory in messaging package [POLICY]', function () {
    expect(is_dir(H::MSG_ROOT.'/tests/Feature'))->toBeFalse();
});

it('fail: tests do not require database connection (case 1) [POLICY]', function () {
    $xml = (string) file_get_contents(H::MSG_ROOT.'/phpunit.xml');
    expect($xml)->toContain('DB_CONNECTION')
        ->and($xml)->toContain('value=""');
});

it('happy: tests live under tests/Unit (case 1) [POLICY]', function () {
    expect(is_dir(H::MSG_ROOT.'/tests/Unit'))->toBeTrue();
});

it('happy: MessagingHelpers fixture class is autoloadable [POLICY]', function () {
    expect(class_exists(MessagingHelpers::class))->toBeTrue();
});
