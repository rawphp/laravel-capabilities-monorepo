<?php

namespace Rawphp\Capabilities\Approval\Notifiers;

/**
 * @deprecated Use {@see RecordingTelegramApprovalNotifier} for the in-memory
 * recording double (tests / wiring). Production Bot API delivery lives in
 * the sibling package `rawphp/laravel-capabilities-messaging` (production
 * Telegram approval notifier — different FQCN, not this core class).
 *
 * Soft-landing dual-class for the 0.x rename: this FQCN remains loadable and
 * type-compatible with {@see RecordingTelegramApprovalNotifier} but is
 * recording-only — **no** Bot API / network in core (D-007).
 */
class TelegramApprovalNotifier extends RecordingTelegramApprovalNotifier {}
