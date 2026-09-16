<?php

namespace LaraUtilX\Utilities;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class ConfigUtil
{
    /**
     * Get all dynamic configuration settings.
     *
     * @param  string|null  $path  Path on the configured disk, relative to its root.
     * @return array
     */
    public function getAllSettings(?string $path = null): array
    {
        $path = $path ?: $this->settingsPath();
        $disk = $this->disk();

        if (! $disk->exists($path)) {
            return [];
        }

        return json_decode($disk->get($path), true) ?: [];
    }

    /**
     * Get a specific dynamic configuration setting. Supports dot notation.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->getAllSettings(), $key, $default);
    }

    /**
     * Set or update a dynamic configuration setting. Supports dot notation.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->getAllSettings();

        data_set($settings, $key, $value);

        $this->disk()->put(
            $this->settingsPath(),
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Remove a dynamic configuration setting.
     *
     * @param  string  $key
     * @return void
     */
    public function forgetSetting(string $key): void
    {
        $settings = $this->getAllSettings();

        // unset() cannot reach a dotted key, which getSetting() and
        // setSetting() both accept.
        Arr::forget($settings, $key);

        $this->disk()->put(
            $this->settingsPath(),
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Get all application settings.
     *
     * @return array
     */
    public function getAllAppSettings(): array
    {
        return config('app', []);
    }

    private function disk(): Filesystem
    {
        $disk = config('lara-util-x.config.disk');

        return $disk ? Storage::disk($disk) : Storage::disk();
    }

    /**
     * Path is relative to the disk root. An absolute path would be appended to
     * that root and silently written somewhere unreadable.
     */
    private function settingsPath(): string
    {
        return config('lara-util-x.config.path', 'config/settings.json');
    }
}
