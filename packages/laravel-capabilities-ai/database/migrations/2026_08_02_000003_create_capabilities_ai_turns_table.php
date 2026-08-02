<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Models\TableNames;

return new class extends Migration
{
    public function up(): void
    {
        $table = TableNames::turns();
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('conversation_id')->index();
            $blueprint->string('ulid', 26)->unique();
            $blueprint->string('status', 32)->default('queued')->index();
            $blueprint->string('idempotency_key', 191)->nullable()->index();
            $blueprint->string('request_hash', 128)->nullable();
            $blueprint->timestamp('claimed_at')->nullable();
            $blueprint->string('claim_owner', 191)->nullable();
            $blueprint->text('error')->nullable();
            $blueprint->timestamp('started_at')->nullable();
            $blueprint->timestamp('finished_at')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('conversation_id')
                ->references('id')
                ->on(TableNames::conversations())
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TableNames::turns());
    }
};
