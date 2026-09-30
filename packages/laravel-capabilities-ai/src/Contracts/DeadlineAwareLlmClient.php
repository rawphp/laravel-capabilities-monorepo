<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Contracts;

/**
 * Optional {@see LlmClient} capability: lets TurnRunner fit a whole multi-round turn inside
 * its job timeout (claim_ttl).
 *
 * TurnRunner hands the client the turn deadline; the client then shrinks each request (and
 * each retry) to end before it, so the worker is never killed mid-request. A round is refused,
 * failing the turn as retryable, only when less than MIN_REQUEST_SECONDS are left. A client
 * that does not implement this gets no turn budget: the runner cannot bound its calls.
 */
interface DeadlineAwareLlmClient extends LlmClient
{
    /**
     * Least time left before the deadline for which a request (a round, or a 429 retry) is
     * still started. Below it, a tool-choosing completion is more likely to hit its shortened
     * timeout than to finish, so failing retryable up front is cheaper than a doomed call.
     */
    public const MIN_REQUEST_SECONDS = 10;

    /**
     * Copy of this client whose requests and retries all end before $deadlineNs, an absolute
     * hrtime(true) value (monotonic nanoseconds): each request's transport timeout is at most
     * the time left, and none starts with under MIN_REQUEST_SECONDS left.
     */
    public function withDeadline(int $deadlineNs): static;
}
