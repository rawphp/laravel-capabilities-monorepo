<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Models\TableNames;

return new class extends Migration
{
    public function up(): void
    {
        $table = TableNames::proposals();
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('turn_id')->index();
            $blueprint->unsignedBigInteger('conversation_id')->index();
            $blueprint->string('ulid', 26)->unique();
            $blueprint->string('type', 64)->nullable();
            $blueprint->json('payload')->nullable();
            $blueprint->string('target_capability', 191)->nullable();
            $blueprint->string('status', 32)->default('pending')->index();
            $blueprint->timestamp('accepted_at')->nullable();
            $blueprint->text('last_error')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('turn_id')
                ->references('id')
                ->on(TableNames::turns())
                ->cascadeOnDelete();
            $blueprint->foreign('conversation_id')
                ->references('id')
                ->on(TableNames::conversations())
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TableNames::proposals());
    }
};
