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
     * @param  array<string, mixed>  $approval  Approval record (id, capability_name, summary, …)
     */
    public function notifyPending(array $approval): void;
}
