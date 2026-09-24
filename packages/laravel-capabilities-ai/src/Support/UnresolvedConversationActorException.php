<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use RuntimeException;

/**
 * The conversation owner is missing or no longer loads as a user (principal refusal).
 *
 * Distinct from resolver misconfiguration (plain RuntimeException) and DB errors,
 * which are not the principal's fault and stay re-drivable.
 */
final class UnresolvedConversationActorException extends RuntimeException {}
