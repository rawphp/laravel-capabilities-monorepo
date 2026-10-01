<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Illuminate\Contracts\Validation\Factory as FactoryContract;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\PresenceVerifierInterface;
use ReflectionClass;

/**
 * In-memory presence verifier for exists/unique unit tests (no database).
 */
final class CountingPresenceVerifier implements PresenceVerifierInterface
{
    /** @var list<array<string, mixed>> */
    public array $calls = [];

    /** @param array<string, int> $counts */
    public function __construct(public array $counts = []) {}

    public function getCount($collection, $column, $value, $id = null, $idColumn = null, array $extra = [])
    {
        $this->calls[] = [
            'collection' => $collection,
            'column' => $column,
            'value' => $value,
            'id' => $id,
            'idColumn' => $idColumn,
        ];

        return $this->counts[$collection] ?? 0;
    }

    public function getMultiCount($collection, $column, array $values, array $extra = [])
    {
        return $this->counts[$collection] ?? 0;
    }

    public static function factory(?PresenceVerifierInterface $verifier = null): FactoryContract
    {
        $loader = new ArrayLoader;
        $lang = dirname((string) (new ReflectionClass(Translator::class))->getFileName()).'/lang/en/validation.php';
        $loader->addMessages('en', 'validation', require $lang);
        $factory = new Factory(new Translator($loader, 'en'));
        if ($verifier !== null) {
            $factory->setPresenceVerifier($verifier);
        }

        return $factory;
    }
}
