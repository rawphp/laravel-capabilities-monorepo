<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Tests\Fakes;

use Illuminate\Database\Eloquent\Model;
use Rawphp\CapabilitiesAi\Contracts\ActorLookup;

/**
 * In-memory ActorLookup: unsaved user models keyed by a sequential id, no database.
 */
final class InMemoryActorLookup implements ActorLookup
{
    /** @var array<string, Model> */
    public array $users = [];

    private int $nextId = 1;

    /**
     * @template T of Model
     *
     * @param  T  $user
     * @return T
     */
    public function add(Model $user): Model
    {
        $user->forceFill(['id' => $this->nextId++]);

        return $this->users[(string) $user->getKey()] = $user;
    }

    public function remove(Model $user): void
    {
        unset($this->users[(string) $user->getKey()]);
    }

    public function find(string $userId): ?object
    {
        return $this->users[$userId] ?? null;
    }
}
