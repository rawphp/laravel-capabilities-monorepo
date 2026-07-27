<?php

namespace Rawphp\CapabilitiesMessaging\Support;

/**
 * Queue port for webhook → ProcessTelegramUpdate (mockable; no Laravel Queue required).
 */
interface UpdateQueue
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function push(string $job, array $payload): void;
}
