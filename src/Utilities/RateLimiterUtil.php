<?php

namespace LaraUtilX\Utilities;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;

class RateLimiterUtil
{
    /**
     * The rate limiter instance.
     *
     * @var \Illuminate\Cache\RateLimiter
     */
    protected RateLimiter $rateLimiter;

    /**
     * Create a new rate limiter utility instance.
     *
     * @param  \Illuminate\Contracts\Cache\Repository  $cache
     * @return void
     */
    public function __construct(Repository $cache)
    {
        $this->rateLimiter = new RateLimiter($cache);
    }

    /**
     * Attempt to hit the given rate limiter.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  int  $decayMinutes
     * @return bool
     */
    public function attempt(string $key, int $maxAttempts, int $decayMinutes): bool
    {
        if ($this->rateLimiter->tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        $this->rateLimiter->hit($key, $decayMinutes * 60);

        return true;
    }

    /**
     * Get the number of attempts for the given key.
     *
     * @param  string  $key
     * @return int
     */
    public function attempts(string $key): int
    {
        return $this->rateLimiter->attempts($key);
    }

    /**
     * Get the number of remaining attempts for the given key.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @return int
     */
    public function remaining(string $key, int $maxAttempts): int
    {
        return $this->rateLimiter->remaining($key, $maxAttempts);
    }

    /**
     * Clear the hits and lockout timer for the given key.
     *
     * @param  string  $key
     * @return void
     */
    public function clear(string $key): void
    {
        $this->rateLimiter->clear($key);
    }

    /**
     * Get the number of seconds until the "key" is accessible again.
     *
     * @param  string  $key
     * @return int
     */
    public function availableIn(string $key): int
    {
        return $this->rateLimiter->availableIn($key);
    }

    /**
     * Determine if the given key has been "accessed" too many times.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @return bool
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->rateLimiter->tooManyAttempts($key, $maxAttempts);
    }

    /**
     * Increment the counter for a given key for a given decay time.
     *
     * @param  string  $key
     * @param  int  $decaySeconds
     * @return int
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        return $this->rateLimiter->hit($key, $decaySeconds);
    }

    /**
     * Get the underlying rate limiter instance.
     *
     * @return \Illuminate\Cache\RateLimiter
     */
    public function getRateLimiter(): RateLimiter
    {
        return $this->rateLimiter;
    }
}
