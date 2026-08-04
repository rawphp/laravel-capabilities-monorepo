<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use Rawphp\CapabilitiesAi\Models\Proposal;

/**
 * Typed post-claim accept result — HTTP/jobs map status without exception archaeology.
 */
final class AcceptOutcome
{
    public const KIND_ACCEPTED = 'accepted';

    public const KIND_APPROVAL_REQUIRED = 'approval_required';

    public const KIND_RETRYABLE = 'retryable';

    public const KIND_FAILED = 'failed';

    public const KIND_REFUSE = 'refuse';

    /**
     * @param  array<string, mixed>|null  $error  Wire-style error envelope when not accepted
     */
    public function __construct(
        public readonly string $kind,
        public readonly Proposal $proposal,
        public readonly ?string $message = null,
        public readonly ?string $approvalId = null,
        public readonly ?int $httpStatus = null,
        public readonly ?array $error = null,
    ) {}

    public static function accepted(Proposal $proposal): self
    {
        return new self(
            kind: self::KIND_ACCEPTED,
            proposal: $proposal,
            httpStatus: 200,
        );
    }

    /**
     * @param  array<string, mixed>|null  $error
     */
    public static function approvalRequired(
        Proposal $proposal,
        ?string $approvalId = null,
        ?string $message = null,
        ?array $error = null,
    ): self {
        return new self(
            kind: self::KIND_APPROVAL_REQUIRED,
            proposal: $proposal,
            message: $message ?? 'Approval required',
            approvalId: $approvalId,
            httpStatus: 202,
            error: $error,
        );
    }

    /**
     * @param  array<string, mixed>|null  $error
     */
    public static function retryable(
        Proposal $proposal,
        ?string $message = null,
        ?int $httpStatus = null,
        ?array $error = null,
    ): self {
        return new self(
            kind: self::KIND_RETRYABLE,
            proposal: $proposal,
            message: $message ?? 'Accept failed; retry',
            httpStatus: $httpStatus ?? 409,
            error: $error,
        );
    }

    /**
     * @param  array<string, mixed>|null  $error
     */
    public static function failed(
        Proposal $proposal,
        ?string $message = null,
        ?int $httpStatus = null,
        ?array $error = null,
    ): self {
        return new self(
            kind: self::KIND_FAILED,
            proposal: $proposal,
            message: $message ?? 'Accept failed',
            httpStatus: $httpStatus ?? 422,
            error: $error,
        );
    }

    /**
     * @param  array<string, mixed>|null  $error
     */
    public static function refuse(
        Proposal $proposal,
        ?string $message = null,
        ?int $httpStatus = null,
        ?array $error = null,
    ): self {
        return new self(
            kind: self::KIND_REFUSE,
            proposal: $proposal,
            message: $message ?? 'Accept refused',
            httpStatus: $httpStatus ?? 403,
            error: $error,
        );
    }

    public function isAccepted(): bool
    {
        return $this->kind === self::KIND_ACCEPTED;
    }
}
