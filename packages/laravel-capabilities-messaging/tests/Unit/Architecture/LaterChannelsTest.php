<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

foreach (['slack', 'whatsapp', 'email'] as $channel) {
    it("edge: channel {$channel} not required in v1 telegram path [D-007]", function () use ($channel) {
        expect(is_dir(H::MSG_SRC.'/'.ucfirst($channel)))->toBeFalse();
        $cfg = MessagingConfig::defaults();
        expect($cfg)->toHaveKey('telegram')->and($cfg)->not->toHaveKey($channel);
    });

    it("fail: channel {$channel} must not be implemented in core [D-007]", function () use ($channel) {
        expect(is_dir(H::CORE_SRC.'/'.ucfirst($channel)))->toBeFalse();
        expect(is_dir(H::CORE_SRC.'/Messaging'))->toBeFalse();
    });
}
