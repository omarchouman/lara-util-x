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
    public function cache(string $key, mixed $data, ?int $minutes = null, ?array $tags = null)
    {
        // Use constructor defaults if parameters are null
        $minutes = $minutes ?? $this->defaultExpiration;
        $tags = $tags ?? $this->defaultTags;

        // Convert minutes to seconds for Cache::put()
        $seconds = $minutes * 60;

        $store = $this->taggedStore($tags);

        if ($store) {
            try {
                $store->put($key, $data, $seconds);

                return $data;
            } catch (\Exception $e) {
                // Fallback to regular cache if tags fail
            }
        }

        Cache::put($key, $data, $seconds);

        return $data;
    }

    /**
     * Retrieve cached data.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null, ?array $tags = null)
    {
        $store = $this->taggedStore($tags ?? $this->defaultTags);

        if ($store) {
            try {
                return $store->get($key, $default);
            } catch (\Exception $e) {
                // Fallback to regular cache if tags fail
            }
        }

        return Cache::get($key, $default);
    }

    /**
     * Forget cached data.
     *
     * @param  string  $key
     * @return void
     */
    public function forget(string $key, ?array $tags = null)
    {
        $store = $this->taggedStore($tags ?? $this->defaultTags);

        if ($store) {
            try {
                $store->forget($key);

                return;
            } catch (\Exception $e) {
                // Fallback to regular cache if tags fail
            }
        }

        Cache::forget($key);
    }

    /**
     * Tagged entries live in their own namespace, so reads and writes have to
     * agree on the tags or every lookup misses.
     */
    private function taggedStore(array $tags): ?\Illuminate\Contracts\Cache\Repository
    {
        if (empty($tags) || ! Cache::getStore() instanceof \Illuminate\Cache\TaggableStore) {
            return null;
        }

        return Cache::tags($tags);
    }
}
