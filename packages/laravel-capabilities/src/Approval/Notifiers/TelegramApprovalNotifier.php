<?php

namespace Rawphp\Capabilities\Approval\Notifiers;

use Rawphp\Capabilities\Contracts\ApprovalNotifier;

/**
 * Recording-only Telegram-shaped notifier for core unit tests / contract wiring.
 *
 * No Bot API, no network (D-007). Messaging package provides the real channel
 * adapter that implements the same {@see ApprovalNotifier} contract.
 */
final class TelegramApprovalNotifier implements ApprovalNotifier
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
