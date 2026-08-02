<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Models\TableNames;

return new class extends Migration
{
    public function up(): void
    {
        $table = TableNames::messages();
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('conversation_id')->index();
            $blueprint->string('ulid', 26)->unique();
            $blueprint->string('role', 32);
            $blueprint->text('content');
            $blueprint->json('meta')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('conversation_id')
                ->references('id')
                ->on(TableNames::conversations())
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TableNames::messages());
    }
};
