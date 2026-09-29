<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Contracts;

/**
 * Optional {@see LlmClient} capability: lets TurnRunner fit a whole multi-round turn inside
 * its job timeout (claim_ttl).
 *
 * TurnRunner starts a round only while one full request timeout still ends before the turn
 * deadline; otherwise the turn fails as retryable instead of the worker being killed
 * mid-request. It also hands the client that deadline so the client's own retries (429 waits)
 * stop at the turn, not at the call. A client that does not implement this gets no turn
 * budget: the runner cannot know how long one of its calls may take.
 */
interface DeadlineAwareLlmClient extends LlmClient
{
    /** Longest one complete() request may run before its transport timeout fires, in seconds. */
    public function requestTimeoutSeconds(): int;

    /**
     * Copy of this client whose retries never run past $deadlineNs, an absolute
     * hrtime(true) value (monotonic nanoseconds).
     */
    public function withDeadline(int $deadlineNs): static;
}
