<?php

namespace FastLaravel\Http\Util\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool error(string $message, array $context = [])
 * @method static bool warning(string $message, array $context = [])
 * @method static bool debug(string $message, array $context = [])
 * @method static bool critical(string $message, array $context = [])
 * @method static bool emergency(string $message, array $context = [])
 * @method static bool notice(string $message, array $context = [])
 * @method static bool info(string $message, array $context = [])
 * @method static bool alert(string $message, array $context = [])
 * @method static bool log(string $level, string $message, array $context = [])
 *
 * @see \FastLaravel\Http\Util\Logger
 */
class Logger extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'fast.laravel.logger';
    }
}