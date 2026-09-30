<?php

namespace Rawphp\CapabilitiesMessaging\Telegram;

use RuntimeException;

/**
 * Transient update failure: Bot API 429/5xx sending the reply. Capability results, retryable
 * or not, go back to the agent instead (see {@see ProcessTelegramUpdate}).
 *
 * ProcessTelegramUpdate rethrows it so the queued job fails and the queue retries;
 * every other failure is terminal and returned as an ok=false result.
 */
final class RetryableUpdateFailure extends RuntimeException {}
