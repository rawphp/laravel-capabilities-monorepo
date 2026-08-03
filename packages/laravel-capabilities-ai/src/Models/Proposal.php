<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proposal extends Model
{
    public const STATUS_PENDING = 'pending';

    /** Claimed for accept; may stay here for approval_required / retryable (host re-drive). */
    public const STATUS_ACCEPTING = 'accepting';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTING,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_FAILED,
        self::STATUS_EXPIRED,
    ];

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'turn_id',
        'conversation_id',
        'ulid',
        'type',
        'payload',
        'target_capability',
        'status',
        'accepted_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'accepted_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return TableNames::proposals();
    }

    public function turn(): BelongsTo
    {
        return $this->belongsTo(Turn::class, 'turn_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
