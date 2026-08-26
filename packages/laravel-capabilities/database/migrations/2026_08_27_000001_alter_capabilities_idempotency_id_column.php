<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Persistence\MigrationCatalog;

/**
 * Correct capabilities_idempotency.id from BIGINT auto-increment (the original
 * create migration) to a VARCHAR(64) primary key. Gateway ids are 32-char hex
 * (QueryTableGateway::newId), which MySQL strict mode rejects on BIGINT
 * (SQLSTATE 22003 / 1264), failing every CLI idempotency insert.
 *
 * Guards converge both install paths on identical schema:
 * - table missing → no-op (fresh installs already get the corrected shape from
 *   the amended create migration);
 * - id already a string column → no-op (already corrected).
 *
 * Legacy rows are dropped, not converted: the table is an expiring idempotency
 * cache (D-005), not domain data, and any rows on non-strict installs hold
 * truncated garbage ids.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = MigrationCatalog::TABLE_IDEMPOTENCY;

        if (! Schema::hasTable($table)) {
            return;
        }

        if ($this->idColumnIsString($table)) {
            return;
        }

        Schema::drop($table);

        Schema::create($table, static function (Blueprint $blueprint): void {
            MigrationCatalog::defineIdempotency($blueprint);
        });
    }

    public function down(): void
    {
        // Intentionally irreversible: the previous BIGINT auto-increment shape
        // could not store gateway ids. Rolling back would reintroduce the defect.
    }

    private function idColumnIsString(string $table): bool
    {
        try {
            return in_array(
                Schema::getColumnType($table, 'id'),
                ['string', 'varchar', 'character varying', 'guid', 'uuid', 'text'],
                true,
            );
        } catch (Throwable) {
            // Unreadable or partial schema → rebuild.
            return false;
        }
    }
};
