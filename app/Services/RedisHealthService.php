<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class RedisHealthService
{
    /**
     * Whether the default Redis connection accepts a trivial command without error.
     */
    public function isAvailable(): bool
    {
        try {
            $reply = Redis::connection()->ping();

            return $reply !== false && $reply !== null;
        } catch (\Throwable) {
            return false;
        }
    }
}
