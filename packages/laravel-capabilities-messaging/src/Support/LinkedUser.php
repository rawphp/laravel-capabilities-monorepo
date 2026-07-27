<?php

namespace Rawphp\CapabilitiesMessaging\Support;

/**
 * Minimal product principal resolved from a chat identity link.
 *
 * Not an Eloquent model — messaging never trusts Telegram ids as Laravel users.
 */
final class LinkedUser
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $tenantId = null,
        public readonly ?string $telegramUserId = null,
        public readonly ?string $name = null,
    ) {}

    public function getAuthIdentifier(): string
    {
        return $this->id;
    }
}
