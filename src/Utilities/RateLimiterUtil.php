<?php

namespace LaraUtilX\Utilities;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

class RateLimiterUtil
{
    /**
     * Attempt to hit the given rate limiter.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  int  $decayMinutes
     * @return bool
     */
    public static function attempt(string $key, int $maxAttempts, int $decayMinutes)
    {
        $limiter = new RateLimiter(app('cache.store'), $key, $maxAttempts, $decayMinutes);

        if ($limiter->tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        $limiter->hit($key, $decayMinutes * 60);

        return true;
    }
}
