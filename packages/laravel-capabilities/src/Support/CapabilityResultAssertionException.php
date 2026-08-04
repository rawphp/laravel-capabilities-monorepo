<?php

namespace Rawphp\Capabilities\Support;

use RuntimeException;

/**
 * Thrown by CapabilityResult assert* helpers when the result does not match.
 *
 * Used by package unit tests and consumer tests that call assertOk / assertFailed / …
 */
final class CapabilityResultAssertionException extends RuntimeException {}
