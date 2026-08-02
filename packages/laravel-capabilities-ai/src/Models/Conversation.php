<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Package conversation row (capabilities_ai_* tables only).
 */
class Conversation extends Model
{
    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'ulid',
        'app_id',
        'user_id',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function getTable(): string
    {
        return TableNames::conversations();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function turns(): HasMany
    {
        return $this->hasMany(Turn::class, 'conversation_id');
    }
}
