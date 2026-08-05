<?php

namespace Rawphp\Capabilities\Approval\Notifiers;

use Rawphp\Capabilities\Contracts\ApprovalNotifier;

/**
 * In-memory recording stub for Telegram-shaped approval notifications.
 *
 * **Not** a production Bot API client (D-007). Core owns only the
 * {@see ApprovalNotifier} contract and this recording double for unit tests /
 * wiring. The real channel adapter lives in the sibling package
 * `rawphp/laravel-capabilities-messaging` (production Telegram approval notifier).
 */
class RecordingTelegramApprovalNotifier implements ApprovalNotifier
{
    /** @var list<array<string, mixed>> */
    private array $notified = [];

    /** @var list<array{approval: array<string, mixed>, text: string}> */
    private array $edits = [];

    public function notifyPending(array $approval): void
    {
        $this->notified[] = $approval;
    }

    /**
     * Edit a previously sent approval message (e.g. mark expired / decided).
     *
     * @param  array<string, mixed>  $approval
     */
    public function editMessage(array $approval, string $text): void
    {
        $this->edits[] = ['approval' => $approval, 'text' => $text];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function notified(): array
    {
        return $this->notified;
    }

    /**
     * @return list<array{approval: array<string, mixed>, text: string}>
     */
    public function edits(): array
    {
        return $this->edits;
    }
}
