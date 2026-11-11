<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rawphp\CapabilitiesAi\Models\TableNames;

/**
 * Client message key for POST chat/messages dedupe: one key per conversation owner.
 * The owner is copied onto the message because the unique index cannot reach the
 * conversations table. Unkeyed messages leave both columns null and never collide.
 * hasColumn guards keep this idempotent for hosts on either side of the change.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = TableNames::messages();
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'client_message_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            $blueprint->string('client_message_owner_id', 191)->nullable();
            $blueprint->string('client_message_id', 191)->nullable();
            $blueprint->unique(['client_message_owner_id', 'client_message_id'], self::index($table));
        });
    }

    public function down(): void
    {
        $table = TableNames::messages();
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'client_message_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            $blueprint->dropUnique(self::index($table));
        });
        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn(['client_message_owner_id', 'client_message_id']);
        });
    }

    /** Explicit name: the default one passes MySQL's 64-character limit. */
    private static function index(string $table): string
    {
        return $table.'_client_message_unique';
    }
};
