<?php

namespace LaraUtilX;

use Illuminate\Support\ServiceProvider;
use LaraUtilX\Http\Middleware\AccessLogMiddleware;
use LaraUtilX\Models\AccessLog;
use LaraUtilX\Traits\ApiResponseTrait;
use LaraUtilX\Traits\FileProcessingTrait;
use LaraUtilX\Utilities\CachingUtil;
use LaraUtilX\Utilities\ConfigUtil;
use LaraUtilX\Utilities\FeatureToggleUtil;
use LaraUtilX\Utilities\FilteringUtil;
use LaraUtilX\Utilities\LoggingUtil;
use LaraUtilX\Utilities\PaginationUtil;
use LaraUtilX\Utilities\QueryParameterUtil;
use LaraUtilX\Utilities\RateLimiterUtil;
use LaraUtilX\Utilities\SchedulerUtil;
use LaraUtilX\LLMProviders\OpenAI\OpenAIProvider;
use LaraUtilX\LLMProviders\OpenAI\Contracts\OpenAIProviderInterface;
use LaraUtilX\LLMProviders\Contracts\LLMProviderInterface;

class LaraUtilXServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind('AccessLog', AccessLog::class);
        
        // Register OpenAI Provider
        $this->app->bind(OpenAIProviderInterface::class, function ($app) {
            return new OpenAIProvider(
                apiKey: config('lara-util-x.openai.api_key'),
                maxRetries: config('lara-util-x.openai.max_retries', 3),
                retryDelay: config('lara-util-x.openai.retry_delay', 2)
            );
        });

        // Register base LLM Provider interface
        $this->app->bind(LLMProviderInterface::class, function ($app) {
            return $app->make(OpenAIProviderInterface::class);
        });

        $this->app->singleton('xhelper', function () {
            return new XHelper();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish Service Provider
        $this->publishes([
            __DIR__ . '/LaraUtilXServiceProvider.php' => app_path('Providers/LaraUtilXServiceProvider.php'),
        ], 'lara-util-x');

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
        $this->publishUtility('LoggingUtil', 'logging');

        // Load utilities
        $classes = [
            ConfigUtil::class,
            SchedulerUtil::class,
            QueryParameterUtil::class,
            RateLimiterUtil::class,
            PaginationUtil::class,
            FilteringUtil::class,
            FeatureToggleUtil::class,
            LoggingUtil::class
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
