<?php

namespace Rawphp\Capabilities\Events;

/**
 * Shared correlation / payload keys for bus events (D-010).
 */
final class EventPayload
{
    /** @var list<string> */
    public const CORRELATION_KEYS = [
        'name',
        'actor',
        'scope',
        'caller',
        'duration',
        'invocation_id',
        'request_id',
    ];

    /**
     * Build meta bag for domain events. Unknown keys are preserved.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public static function meta(array $fields = []): array
    {
        $meta = [];
        foreach (self::CORRELATION_KEYS as $key) {
            if (array_key_exists($key, $fields)) {
                $meta[$key] = $fields[$key];
            }
        }
        foreach ($fields as $key => $value) {
            if (! array_key_exists($key, $meta)) {
                $meta[$key] = $value;
            }
        }

        return $meta;
    }

    public static function hasCorrelationKey(string $key): bool
    {
        return in_array($key, self::CORRELATION_KEYS, true);
    }

    /**
     * Events that should use afterCommit listeners by default (D-010).
     *
     * @return list<class-string>
     */
    public static function afterCommitEvents(): array
    {
        return [
            CapabilityInvoked::class,
            CapabilityFailed::class,
            CapabilityApprovalRequested::class,
            CapabilityApprovalDecided::class,
            CapabilityApprovalExecuted::class,
        ];
    }

    public static function listenersShouldUseAfterCommit(string $eventClass): bool
    {
        if (! class_exists($eventClass)) {
            return false;
        }

        if (method_exists($eventClass, 'listenersShouldUseAfterCommit')) {
            return (bool) $eventClass::listenersShouldUseAfterCommit();
        }

        return in_array($eventClass, self::afterCommitEvents(), true);
    }
}
