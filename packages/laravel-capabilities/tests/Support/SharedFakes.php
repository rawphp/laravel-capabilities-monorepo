<?php

namespace Rawphp\Capabilities\Tests\Support;

use DateTimeImmutable;
use Rawphp\Capabilities\Contracts\ApprovalStore;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Contracts\Authorizer;
use Rawphp\Capabilities\Contracts\Clock;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Contracts\RateLimiter;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Support\InMemoryAuditWriter;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\Capabilities\Support\StubAuthorizer;

/**
 * Convenience constructor for the shared unit-test double set.
 *
 * Every store/writer requires an explicit {@see Clock}; callers may share one
 * FixedClock across the bundle for deterministic expiry scenarios.
 */
final class SharedFakes
{
    public function __construct(
        public readonly Clock $clock,
        public readonly ApprovalStore $approvals,
        public readonly IdempotencyStore $idempotency,
        public readonly AuditWriter $audit,
        public readonly RateLimiter $rateLimiter,
        public readonly Authorizer $authorizer,
    ) {}

    /**
     * Build a complete in-memory double set with no DB/Redis/network.
     *
     * Missing typed dependencies are not defaulted to null — all stores are
     * constructed with a real Clock instance.
     */
    public static function create(
        ?Clock $clock = null,
        bool $authorize = true,
        ?DateTimeImmutable $now = null,
    ): self {
        $clock ??= new FixedClock($now ?? new DateTimeImmutable('2026-01-01T00:00:00+00:00'));

        return new self(
            clock: $clock,
            approvals: new InMemoryApprovalStore($clock),
            idempotency: new InMemoryIdempotencyStore($clock),
            audit: new InMemoryAuditWriter($clock),
            rateLimiter: new InMemoryRateLimiter,
            authorizer: $authorize ? StubAuthorizer::allow() : StubAuthorizer::deny(),
        );
    }
}
