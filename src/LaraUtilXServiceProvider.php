<?php

namespace omarchouman\LaraUtilX;

use Illuminate\Support\ServiceProvider;
use omarchouman\LaraUtilX\Http\Middleware\AccessLogMiddleware;
use omarchouman\LaraUtilX\Models\AccessLog;
use omarchouman\LaraUtilX\Traits\ApiResponseTrait;
use omarchouman\LaraUtilX\Traits\FileProcessingTrait;
use omarchouman\LaraUtilX\Utilities\CachingUtil;
use omarchouman\LaraUtilX\Utilities\ConfigUtil;
use omarchouman\LaraUtilX\Utilities\FeatureToggleUtil;
use omarchouman\LaraUtilX\Utilities\FilteringUtil;
use omarchouman\LaraUtilX\Utilities\PaginationUtil;
use omarchouman\LaraUtilX\Utilities\QueryParameterUtil;
use omarchouman\LaraUtilX\Utilities\RateLimiterUtil;
use omarchouman\LaraUtilX\Utilities\SchedulerUtil;

class LaraUtilXServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind('AccessLog', AccessLog::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Publish configs
        $this->publishes([
            __DIR__ . '/../config/lara-util-x.php' => config_path('lara-util-x.php'),
        ], 'lara-util-x-config');

        $this->publishes([
            __DIR__ . '/../config/feature-toggles.php' => config_path('feature-toggles.php'),
        ], 'lara-util-x-feature-toggles');

        $this->mergeConfigFrom(__DIR__ . '/../config/lara-util-x.php', 'lara-util-x');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'lara-util-x-migrations');

        // Publish models
        $this->publishes([
            __DIR__ . '\Models' => app_path('Models'),
        ], 'lara-util-x-models');

        // Publish traits
        $this->publishes([
            __DIR__ . '/Traits/ApiResponseTrait.php' => app_path('Traits/ApiResponseTrait.php'),
        ], 'lara-util-x-api-response-trait');

        $this->loadClass(ApiResponseTrait::class);
        $this->loadClass(FileProcessingTrait::class);

        // Publish utilities
        $this->publishUtility('CachingUtil', 'caching');
        $this->publishUtility('ConfigUtil', 'config');
        $this->publishUtility('SchedulerUtil', 'scheduler');
        $this->publishUtility('QueryParameterUtil', 'query-parameter');
        $this->publishUtility('RateLimiterUtil', 'rate-limiter');
        $this->publishUtility('PaginationUtil', 'paginator');
        $this->publishUtility('FilteringUtil', 'filtering');

        // Load utilities
        $classes = [
            ConfigUtil::class,
            SchedulerUtil::class,
            QueryParameterUtil::class,
            RateLimiterUtil::class,
            PaginationUtil::class,
            FilteringUtil::class,
            FeatureToggleUtil::class
        ];

        $this->loadUtilityClasses($classes);
        $this->loadCachingUtility();

        // Register middleware
        $this->app['router']->aliasMiddleware('access.log', AccessLogMiddleware::class);
    }


    /**
     * Dynamically load the given class.
     *
     * @param string $class
     */
    private function loadClass(string $class)
    {
        $this->app->bind($class, function () use ($class) {
            return new $class();
        });
    }

    /**
     * Dynamically load the given utility classes.
     *
     * @param array $classes
     */
    private function loadUtilityClasses(array $classes)
    {
        foreach ($classes as $class) {
            $this->app->bind($class, function () use ($class) {
                return new $class();
            });
        }
    }

    /**
     * Load the caching utility with configured options.
     */
    private function loadCachingUtility()
    {
        $config = config('lara-util-x.cache');

        $this->app->bind(CachingUtil::class, function () use ($config) {
            return new CachingUtil($config['default_expiration'], $config['default_tags']);
        });
    }

    private function publishUtility(string $utility, string $name)
    {
        $this->publishes([
            __DIR__ . '/Utilities/' . $utility . '.php' => app_path('Utilities/' . $utility . '.php'),
        ], 'lara-util-x-' . $name);
    }
}
