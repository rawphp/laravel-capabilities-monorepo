<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesMessaging\Tests\Fixtures;

/**
 * Stand-in for a host Eloquent user model: static query()->find() over in-memory rows. No DB.
 */
final class InMemoryUserModel
{
    /** @var array<string, self> */
    public static array $rows = [];

    public function __construct(public string $id) {}

    /**
     * @param  list<string>  $ids
     */
    public static function seed(array $ids): void
    {
        self::$rows = [];
        foreach ($ids as $id) {
            self::$rows[$id] = new self($id);
        }
    }

    public static function query(): object
    {
        return new class
        {
            public function find(mixed $id): ?InMemoryUserModel
            {
                return InMemoryUserModel::$rows[(string) $id] ?? null;
            }
        };
    }
}
