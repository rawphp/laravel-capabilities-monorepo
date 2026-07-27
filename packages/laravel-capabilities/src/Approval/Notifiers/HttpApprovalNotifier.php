<?php

namespace Rawphp\Capabilities\Approval\Notifiers;

use Rawphp\Capabilities\Contracts\ApprovalNotifier;

/**
 * Core HTTP-channel approval notifier — records pending for UI/API consumers.
 *
 * Never executes capabilities (D-006).
 */
final class HttpApprovalNotifier implements ApprovalNotifier
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
