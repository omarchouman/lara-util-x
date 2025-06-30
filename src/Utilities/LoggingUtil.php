<?php

namespace LaraUtilX\Utilities;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class LoggingUtil
{
    private static ?Logger $customLogger = null;

    /**
     * Initialize a custom logger instance if needed.
     *
     * @param string|null $channel Custom log channel
     * @return Logger
     */
    private static function getLogger(?string $channel = null): Logger
    {
        if ($channel) {
            return Log::channel($channel);
        }

        if (!self::$customLogger) {
            $logPath = storage_path('logs/custom.log');
            $handler = new StreamHandler($logPath, Logger::DEBUG);
            $handler->setFormatter(new JsonFormatter());
            
            
            self::$customLogger = new Logger('custom');
            self::$customLogger->pushHandler($handler);
        }
        
        return self::$customLogger;
    }

    /**
     * Log a message with context and formatting.
     *
     * @param string $level Log level (debug, info, warning, error, critical)
     * @param string $message Log message
     * @param array $context Additional context data
     * @param string|null $channel Log channel (default, single, daily, custom, etc.)
     */
    public static function log(string $level, string $message, array $context = [], ?string $channel = null): void
    {
        $logger = self::getLogger($channel);
        $context['timestamp'] = now()->toDateTimeString();
        $context['env'] = Config::get('app.env');
        
        $logger->$level($message, $context);
    }

    public static function info(string $message, array $context = [], ?string $channel = null): void
    {
        self::log('info', $message, $context, $channel);
    }

    public static function debug(string $message, array $context = [], ?string $channel = null): void
    {
        self::log('debug', $message, $context, $channel);
    }

    public static function warning(string $message, array $context = [], ?string $channel = null): void
    {
        self::log('warning', $message, $context, $channel);
    }

    public static function error(string $message, array $context = [], ?string $channel = null): void
    {
        self::log('error', $message, $context, $channel);
    }

    public static function critical(string $message, array $context = [], ?string $channel = null): void
    {
        self::log('critical', $message, $context, $channel);
    }
}
