<?php

namespace Rawphp\Capabilities\Approval\Notifiers;

use Rawphp\Capabilities\Contracts\ApprovalNotifier;

/**
 * Core CLI-channel approval notifier — surfaces pending to product CLI clients.
 *
 * Never executes capabilities (D-006).
 */
final class CliApprovalNotifier implements ApprovalNotifier
{
    /** @var list<array<string, mixed>> */
    private array $notified = [];

    public function notifyPending(array $approval): void
    {
        $this->notified[] = $approval;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function notified(): array
    {
        return $this->notified;
    }
}
