<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Models\TableNames;

return new class extends Migration
{
    public function up(): void
    {
        $table = TableNames::conversations();
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('ulid', 26)->unique();
            $blueprint->string('app_id', 191)->nullable()->index();
            $blueprint->string('user_id', 191)->nullable()->index();
            $blueprint->string('status', 32)->default('open')->index();
            $blueprint->json('meta')->nullable();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TableNames::conversations());
    }
};
