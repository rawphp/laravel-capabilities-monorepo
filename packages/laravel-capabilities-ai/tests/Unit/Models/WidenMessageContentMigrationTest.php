<?php

declare(strict_types=1);

/**
 * messages.content widen migration against a recording Schema builder (no database).
 */

use Illuminate\Container\Container;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Models\TableNames;

function widenContentMigration(): object
{
    return require dirname(__DIR__, 3).'/database/migrations/2026_09_30_000001_widen_capabilities_ai_messages_content.php';
}

/**
 * Schema builder that answers hasTable/hasColumn and records each table() blueprint instead of running SQL.
 */
function recordingSchema(bool $hasTable = true, bool $hasColumn = true): Builder
{
    $schema = new class($hasTable, $hasColumn) extends Builder
    {
        /** @var list<Blueprint> */
        public array $blueprints = [];

        public function __construct(private readonly bool $tableExists, private readonly bool $columnExists) {}

        public function hasTable($table)
        {
            return $this->tableExists && $table === TableNames::messages();
        }

        public function hasColumn($table, $column)
        {
            return $this->columnExists && $table === TableNames::messages() && $column === 'content';
        }

        public function table($table, Closure $callback)
        {
            $blueprint = new class($table) extends Blueprint
            {
                public function __construct(string $table)
                {
                    $this->table = $table;
                }
            };
            $callback($blueprint);
            $this->blueprints[] = $blueprint;
        }
    };
    Facade::setFacadeApplication(new Container);
    Facade::clearResolvedInstances();
    Schema::swap($schema);

    return $schema;
}

/**
 * @return list<array{table: string, type: string, name: string, change: bool}>
 */
function recordedChanges(Builder $schema): array
{
    $changes = [];
    foreach ($schema->blueprints as $blueprint) {
        foreach ($blueprint->getColumns() as $column) {
            $changes[] = [
                'table' => $blueprint->getTable(),
                'type' => $column->type,
                'name' => $column->name,
                'change' => (bool) $column->change,
            ];
        }
    }

    return $changes;
}

afterEach(function () {
    Facade::clearResolvedInstances();
});

it('widens messages.content to longText so long replies fit on MySQL', function () {
    $schema = recordingSchema();

    widenContentMigration()->up();

    expect(recordedChanges($schema))->toBe([
        ['table' => TableNames::messages(), 'type' => 'longText', 'name' => 'content', 'change' => true],
    ]);
});

it('restores messages.content to text on down', function () {
    $schema = recordingSchema();

    widenContentMigration()->down();

    expect(recordedChanges($schema))->toBe([
        ['table' => TableNames::messages(), 'type' => 'text', 'name' => 'content', 'change' => true],
    ]);
});

it('skips when the messages table or content column is missing', function (bool $hasTable) {
    $schema = recordingSchema($hasTable, hasColumn: false);

    $migration = widenContentMigration();
    $migration->up();
    $migration->down();

    expect($schema->blueprints)->toBe([]);
})->with([
    'no table' => [false],
    'no column' => [true],
]);
