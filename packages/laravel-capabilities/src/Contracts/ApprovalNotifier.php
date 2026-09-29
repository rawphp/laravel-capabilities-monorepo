<?php

namespace Rawphp\Capabilities\Contracts;

/**
 * Notify a human of a pending approval (HTTP/CLI in core; chat channels in messaging).
 *
 * Core contract is channel-agnostic — no messaging Bot SDK types (D-007).
 */
interface ApprovalNotifier
{
    /**
     * Container tag the core provider collects extra notifiers from and attaches to the single
     * ApprovalManager (L-101 / M-101). Siblings tag their implementation under this name; the
     * plain contract binding is attached as well.
     */
    public const CONTAINER_TAG = 'capabilities.approval_notifiers';

    /**
     * @param  array<string, mixed>  $approval  Approval record (id, capability_name, summary, …)
     */
    public function notifyPending(array $approval): void;
}
