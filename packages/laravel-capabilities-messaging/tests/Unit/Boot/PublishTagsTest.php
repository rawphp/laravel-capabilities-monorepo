<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Boot\MessagingRegistration;
use Rawphp\CapabilitiesMessaging\MessagingServiceProvider;

it('edge: publish tag capabilities-messaging-config available [MSG-001]', function () {
    expect(MessagingServiceProvider::publishTags())->toContain('capabilities-messaging-config');
    expect(MessagingRegistration::PUBLISH_TAGS)->toContain('capabilities-messaging-config');
});
