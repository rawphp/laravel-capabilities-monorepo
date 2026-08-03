<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Rawphp\CapabilitiesAi\Contracts\LlmClient;

/**
 * Opt-out defaults for host {@see LlmClient} implementors.
 * Multi-round tools stay off until the host overrides supportsToolRounds() to true.
 */
trait LlmClientDefaults
{
    public function supportsToolRounds(): bool
    {
        return false;
    }
}
