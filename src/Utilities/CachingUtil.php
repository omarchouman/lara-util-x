<?php

namespace LaraUtilX\Utilities;

use Illuminate\Support\Facades\Cache;

class CachingUtil
{
    protected int $defaultExpiration;
    protected array $defaultTags;

    public function __construct(int $defaultExpiration, array $defaultTags)
    {
        $this->defaultExpiration = $defaultExpiration;
        $this->defaultTags = $defaultTags;
    }

    /**
     * Cache data with configurable options.
     *
     * @param  string  $key
     * @param  mixed   $data
     * @param  int     $minutes
     * @param  array   $tags
     * @return mixed
     */
    public function cache(string $key, mixed $data, int $minutes = null, array $tags = null)
    {
        // Use constructor defaults if parameters are null
        $minutes = $minutes ?? $this->defaultExpiration;
        $tags = $tags ?? $this->defaultTags;

        // Convert minutes to seconds for Cache::put()
        $seconds = $minutes * 60;

        // Try to use tags if the store supports it and tags are provided
        if (Cache::getStore() instanceof \Illuminate\Cache\TaggableStore && !empty($tags)) {
            try {
                Cache::tags($tags)->put($key, $data, $seconds);
            } catch (\Exception $e) {
                // Fallback to regular cache if tags fail
                Cache::put($key, $data, $seconds);
            }
        } else {
            Cache::put($key, $data, $seconds);
        }

        return $data;
    }

    /**
     * Retrieve cached data.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null)
    {
        return Cache::get($key, $default);
    }

    /**
     * Forget cached data.
     *
     * @param  string  $key
     * @return void
     */
    public function forget(string $key)
    {
        Cache::forget($key);
    }
}
