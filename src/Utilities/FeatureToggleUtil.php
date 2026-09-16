<?php

namespace LaraUtilX\Utilities;

use Illuminate\Support\Facades\Config;

class FeatureToggleUtil
{
    /**
     * Check if a feature is enabled.
     *
     * A feature may be declared either as a plain boolean:
     *
     *     'new-billing' => true,
     *
     * or as an array carrying per-user and per-environment overrides:
     *
     *     'new-billing' => [
     *         'enabled'     => false,
     *         'user'        => [42 => true],
     *         'environment' => ['local' => true],
     *     ],
     *
     * The most specific match wins: user, then environment, then 'enabled'.
     *
     * @param  string  $feature
     * @return bool
     */
    public static function isEnabled(string $feature): bool
    {
        $value = Config::get("feature-toggles.{$feature}", false);

        if (! is_array($value)) {
            return (bool) $value;
        }

        $user = auth()->user();

        if ($user && array_key_exists('user', $value) && array_key_exists($user->id, (array) $value['user'])) {
            return (bool) $value['user'][$user->id];
        }

        $environment = app()->environment();

        if (array_key_exists('environment', $value) && array_key_exists($environment, (array) $value['environment'])) {
            return (bool) $value['environment'][$environment];
        }

        return (bool) ($value['enabled'] ?? false);
    }
}
