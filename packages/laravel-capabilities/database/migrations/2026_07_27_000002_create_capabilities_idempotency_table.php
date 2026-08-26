<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Persistence\MigrationCatalog;

/**
 * Durable mutating-invoke outcomes (D-005). Composite unique identity is required.
 *
 * `id` is a string primary key (gateway ids are 32-char hex); shape lives in
 * MigrationCatalog::defineIdempotency so fresh installs and corrective
 * migrations converge on the same schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = MigrationCatalog::TABLE_IDEMPOTENCY;
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, static function (Blueprint $blueprint): void {
            MigrationCatalog::defineIdempotency($blueprint);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(MigrationCatalog::TABLE_IDEMPOTENCY);
    }
};
