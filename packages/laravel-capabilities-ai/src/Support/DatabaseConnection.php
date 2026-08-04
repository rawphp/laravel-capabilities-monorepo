<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Package-local connection resolver for claim/cancel writers.
 * Prefers Laravel DB facade when the container is available; Capsule otherwise.
 */
final class DatabaseConnection
{
    public static function resolve(): Connection
    {
        if (function_exists('app') && class_exists(DB::class)) {
            try {
                if (app()->bound('db')) {
                    return DB::connection();
                }
            } catch (\Throwable) {
                // fall through to Capsule
            }
        }

        return Manager::connection();
    }
}
