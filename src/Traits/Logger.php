<?php
namespace FastLaravel\Http\Traits;

use FastLaravel\Http\Util\Logger as Log;

/**
 * Trait Logger
 * @method void debug(string $message, array $context = array())
 * @method void info(string $message, array $context = array())
 * @method void notice(string $message, array $context = array())
 * @method void warning(string $message, array $context = array())
 * @method void error(string $message, array $context = array())
 * @method void critical(string $message, array $context = array())
 * @method void alert(string $message, array $context = array())
 * @method void emergency(string $message, array $context = array())
 * @method void log(string $level, string $message, array $context = array())
 * @package FastLaravel\Http\Traits
 */
trait Logger
{
    /**
     * bind logger
     */
    public function bindLogger()
    {
        $this->app->singleton('fast.laravel.logger', function () {
            return new Log();
        });
    }

    /**
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        $logMethods = [
            'debug',
            'info',
            'notice',
            'warning',
            'error',
            'log',
            'critical',
            'alert',
            'emergency'
        ];
        if (in_array($name, $logMethods)) {
            $class = $this->app->make('fast.laravel.logger');
            call_user_func_array([$class, $name], $arguments);
        }
    }
}