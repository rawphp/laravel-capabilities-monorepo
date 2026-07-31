<?php

namespace Rawphp\Capabilities\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use JsonException;

/**
 * First-party {@see TableGateway} backed by Illuminate query builder / connection.
 *
 * Not Eloquent — uses {@see ConnectionInterface::table()} only. Host apps construct:
 *
 * ```php
 * new QueryTableGateway($connection, MigrationCatalog::TABLE_APPROVALS, columnMap: [
 *     'scope' => 'scope_json',
 *     'messaging' => 'channel_meta_json',
 * ]);
 * ```
 *
 * **JSON columns:** arrays/objects are json_encoded on write and decoded on read
 * for names in `$jsonColumns` (defaults cover Database*Store shapes + MigrationCatalog
 * `*_json` names). Logical keys from stores may map to physical columns via `$columnMap`.
 *
 * **No silent fallback:** a null connection throws {@see InvalidArgumentException};
 * this class never substitutes {@see ArrayTableGateway}.
 */
final class QueryTableGateway implements TableGateway
{
    /**
     * Default columns stored as JSON text (store field names + migration names).
     *
     * @var list<string>
     */
    public const DEFAULT_JSON_COLUMNS = [
        'input_json',
        'result_json',
        'scope_json',
        'channel_meta_json',
        'payload_json',
        'scope',
        'messaging',
    ];

    private readonly ConnectionInterface $connection;

    /** @var list<string> */
    private readonly array $jsonColumns;

    /** @var array<string, string> physical => logical */
    private readonly array $reverseColumnMap;

    /** @var array<string, true> */
    private readonly array $jsonColumnSet;

    /**
     * @param  list<string>|null  $jsonColumns  null uses {@see DEFAULT_JSON_COLUMNS}
     * @param  array<string, string>  $columnMap  logical store key => physical DB column
     */
    public function __construct(
        ?ConnectionInterface $connection,
        private readonly string $table,
        ?array $jsonColumns = null,
        private readonly array $columnMap = [],
        private readonly string $primaryKey = 'id',
    ) {
        if ($connection === null) {
            throw new InvalidArgumentException(
                'QueryTableGateway requires an Illuminate\\Database\\ConnectionInterface; refusing silent ArrayTableGateway fallback.'
            );
        }
        if ($this->table === '') {
            throw new InvalidArgumentException('QueryTableGateway requires a non-empty table name.');
        }

        $this->connection = $connection;
        $this->jsonColumns = array_values($jsonColumns ?? self::DEFAULT_JSON_COLUMNS);
        $this->jsonColumnSet = array_fill_keys($this->jsonColumns, true);
        $this->reverseColumnMap = array_flip($this->columnMap);
    }

    /**
     * Physical table this gateway targets (MigrationCatalog names in production).
     */
    public function tableName(): string
    {
        return $this->table;
    }

    public function insert(array $row): array
    {
        $id = $this->resolveId($row);
        $row[$this->primaryKey] = $id;
        $this->query()->insert($this->encodePhysical($row));

        return $this->find($id) ?? $this->decodeLogical($row);
    }

    public function find(string $id): ?array
    {
        $row = $this->query()
            ->where($this->physicalColumn($this->primaryKey), $id)
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->decodeLogical((array) $row);
    }

    public function replace(string $id, array $row): ?array
    {
        if ($this->find($id) === null) {
            return null;
        }

        $row[$this->primaryKey] = $id;
        $this->query()
            ->where($this->physicalColumn($this->primaryKey), $id)
            ->update($this->encodePhysical($row));

        return $this->find($id);
    }

    public function updateWhere(array $where, array $attributes): ?array
    {
        if ($where === []) {
            return null;
        }

        $query = $this->query();
        $this->applyWhere($query, $where);

        $affected = $query->limit(1)->update($this->encodePhysical($attributes));
        if ($affected < 1) {
            return null;
        }

        if (array_key_exists($this->primaryKey, $where)) {
            return $this->find((string) $where[$this->primaryKey]);
        }

        $lookup = array_merge($where, $attributes);
        $rows = $this->findWhere($lookup);

        return $rows[0] ?? null;
    }

    public function updateWhereLeaseFree(
        array $where,
        string $leaseColumn,
        string $nowIso,
        array $attributes,
    ): ?array {
        if ($where === []) {
            return null;
        }

        $physicalLease = $this->physicalColumn($leaseColumn);
        $query = $this->query();
        $this->applyWhere($query, $where);

        // Atomic free-lease predicate: null, empty, or not held past $nowIso (DATE_ATOM-safe).
        $query->where(function (Builder $q) use ($physicalLease, $nowIso): void {
            $q->whereNull($physicalLease)
                ->orWhere($physicalLease, '=', '')
                ->orWhere($physicalLease, '<=', $nowIso);
        });

        $affected = $query->limit(1)->update($this->encodePhysical($attributes));
        if ($affected < 1) {
            return null;
        }

        if (array_key_exists($this->primaryKey, $where)) {
            return $this->find((string) $where[$this->primaryKey]);
        }

        $lookup = array_merge($where, $attributes);
        $rows = $this->findWhere($lookup);

        return $rows[0] ?? null;
    }

    public function findWhere(array $where): array
    {
        $query = $this->query();
        $this->applyWhere($query, $where);
        $rows = $query->get()->all();

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->decodeLogical((array) $row);
        }

        return $out;
    }

    public function upsert(array $identity, array $row): array
    {
        if ($identity === []) {
            throw new InvalidArgumentException('QueryTableGateway::upsert requires a non-empty identity map.');
        }

        $existingQuery = $this->query();
        $this->applyWhere($existingQuery, $identity);
        $existing = $existingQuery->first();

        if ($existing !== null) {
            $logical = $this->decodeLogical((array) $existing);
            $id = (string) ($logical[$this->primaryKey] ?? '');
            $merged = array_merge($logical, $row, $identity);
            if ($id !== '') {
                $merged[$this->primaryKey] = $id;
            }

            $attributes = $merged;
            unset($attributes[$this->primaryKey]);

            // Single UPDATE with identity WHERE — gateway-level atomicity for store semantics.
            $updated = $this->updateWhere($identity, $attributes);
            if ($updated !== null) {
                return $updated;
            }

            return $id !== '' ? ($this->find($id) ?? $merged) : $merged;
        }

        return $this->insert(array_merge($row, $identity));
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private function applyWhere(Builder $query, array $where): void
    {
        foreach ($where as $logical => $value) {
            $physical = $this->physicalColumn((string) $logical);
            $query->where($physical, $this->encodeValue((string) $logical, $value));
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function encodePhysical(array $row): array
    {
        $out = [];
        foreach ($row as $logical => $value) {
            $physical = $this->physicalColumn((string) $logical);
            $out[$physical] = $this->encodeValue((string) $logical, $value);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row  physical keys from DB
     * @return array<string, mixed> logical keys for stores
     */
    private function decodeLogical(array $row): array
    {
        $out = [];

        // Unmapped physical columns first.
        foreach ($row as $physical => $value) {
            $physical = (string) $physical;
            if (isset($this->reverseColumnMap[$physical])) {
                continue;
            }
            $out[$physical] = $this->decodeValue($physical, $value);
        }

        // Mapped physical columns win (avoids null twin columns overwriting JSON).
        foreach ($this->reverseColumnMap as $physical => $logical) {
            if (! array_key_exists($physical, $row)) {
                continue;
            }
            $out[$logical] = $this->decodeValue($logical, $row[$physical]);
        }

        return $out;
    }

    private function encodeValue(string $logicalKey, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($this->isJsonColumn($logicalKey) && (is_array($value) || is_object($value))) {
            try {
                return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } catch (JsonException $e) {
                throw new InvalidArgumentException(
                    'QueryTableGateway could not JSON-encode column '.$logicalKey.': '.$e->getMessage(),
                    0,
                    $e,
                );
            }
        }

        return $value;
    }

    private function decodeValue(string $logicalKey, mixed $value): mixed
    {
        if ($value === null || ! $this->isJsonColumn($logicalKey)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return $value;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $value;
        }

        return $decoded;
    }

    private function isJsonColumn(string $logicalKey): bool
    {
        if (isset($this->jsonColumnSet[$logicalKey])) {
            return true;
        }

        $physical = $this->physicalColumn($logicalKey);
        if (isset($this->jsonColumnSet[$physical])) {
            return true;
        }

        return str_ends_with($logicalKey, '_json') || str_ends_with($physical, '_json');
    }

    private function physicalColumn(string $logical): string
    {
        return $this->columnMap[$logical] ?? $logical;
    }

    private function logicalColumn(string $physical): string
    {
        return $this->reverseColumnMap[$physical] ?? $physical;
    }

    private function query(): Builder
    {
        return $this->connection->table($this->table);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveId(array $row): string
    {
        if (isset($row[$this->primaryKey]) && is_string($row[$this->primaryKey]) && $row[$this->primaryKey] !== '') {
            return $row[$this->primaryKey];
        }
        if (isset($row[$this->primaryKey]) && is_int($row[$this->primaryKey])) {
            return (string) $row[$this->primaryKey];
        }

        return $this->newId();
    }

    private function newId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
