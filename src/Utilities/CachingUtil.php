<?php

namespace omarchouman\LaraUtilX\Utilities;

use Illuminate\Support\Facades\Cache;

class CachingUtil
{
    protected int $defaultExpiration;
    protected array $defaultTags;

    public function __construct(int$defaultExpiration, array $defaultTags)
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
        if (Cache::getStore() instanceof \Illuminate\Cache\TaggableStore) {
            return Cache::tags($tags)->remember($key, $minutes, function () use ($data) {
                return $data;
            });
        }

        return Cache::remember($key, $minutes, function () use ($data) {
            return $data;
        });
    }

    /**
     * Retrieve cached data.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null)
    {
        return Cache::get($key, $default);
    }

    /**
     * Forget cached data.
     *
     * @param  string  $key
     * @return void
     */
    public static function forget(string $key)
    {
        Cache::forget($key);
    }
}
