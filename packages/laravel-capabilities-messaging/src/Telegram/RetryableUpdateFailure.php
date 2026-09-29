<?php

namespace Rawphp\CapabilitiesMessaging\Telegram;

use RuntimeException;

/**
 * Transient update failure (Bot API 429/5xx, retryable registry result).
 *
 * ProcessTelegramUpdate rethrows it so the queued job fails and the queue retries;
 * every other failure is terminal and returned as an ok=false result.
 */
final class RetryableUpdateFailure extends RuntimeException {}
