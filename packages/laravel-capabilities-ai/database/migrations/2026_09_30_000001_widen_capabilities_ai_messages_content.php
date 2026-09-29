<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Models\TableNames;

/**
 * messages.content text → longText: MySQL TEXT holds 65,535 bytes (~16k tokens), so a long
 * assistant reply failed the insert after the LLM call and its tool invokes had run.
 * Guards keep this safe on hosts without the table or column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->hasContentColumn()) {
            return;
        }

        Schema::table(TableNames::messages(), function (Blueprint $blueprint): void {
            $blueprint->longText('content')->change();
        });
    }

    public function down(): void
    {
        if (! $this->hasContentColumn()) {
            return;
        }

        Schema::table(TableNames::messages(), function (Blueprint $blueprint): void {
            $blueprint->text('content')->change();
        });
    }

    private function hasContentColumn(): bool
    {
        $table = TableNames::messages();

        return Schema::hasTable($table) && Schema::hasColumn($table, 'content');
    }
};
