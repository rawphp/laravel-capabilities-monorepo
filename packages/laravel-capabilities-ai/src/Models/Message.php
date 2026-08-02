<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'conversation_id',
        'ulid',
        'role',
        'content',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function getTable(): string
    {
        return TableNames::messages();
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
