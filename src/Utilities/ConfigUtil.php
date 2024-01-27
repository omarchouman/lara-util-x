<?php

namespace omarchouman\LaraUtilX\Utilities;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class ConfigUtil
{
    /**
     * Get all dynamic configuration settings.
     *
     * @param  string  $path
     * @param  string  $key
     * @return array
     */
    public function getAllSettings(string $path = null, string $key = null)
    {
        $filePath = $path ? $path : config('app');

        if($filePath == config('app')) {
            $settings = $this->getAllAppSettings();
            return $settings[$key] ?? null;
        }

        if (Storage::exists($filePath)) {
            $settingsJson = Storage::get($filePath);
            return json_decode($settingsJson, true);
        }

        return [];
    }

    /**
     * Get a specific dynamic configuration setting.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getSetting(string $key)
    {
        $settings = $this->getAllSettings();

        return $settings[$key] ?? null;
    }

    /**
     * Set or update a dynamic configuration setting.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function setSetting(string $key, mixed $value)
    {
        $settings = $this->getAllSettings();
        $settings[$key] = $value;

        $filePath = storage_path('app/config/settings.json');
        Storage::put($filePath, json_encode($settings));
    }


    /**
     * Get all application settings.
     *
     * @return array
     */
    public function getAllAppSettings()
    {
        return config('app');
    }
}