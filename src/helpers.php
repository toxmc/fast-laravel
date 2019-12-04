<?php

/**
 * This is only for `function not exists` in config/swoole_http.php.
 */
if (! function_exists('swoole_cpu_num')) {
    function swoole_cpu_num()
    {
        return;
    }
}

/**
 * 输出辅助函数
 */
if (! function_exists('output')) {
    function Output()
    {
        // 绑定单例服务
        app()->singleton('fast.laravel.output', function ($app) {
            return new FastLaravel\Http\Output\Output();
        });
        return app('fast.laravel.output');
    }
}

if (! function_exists('style')) {
    function Style()
    {
        // 绑定单例服务
        app()->singleton('fast.laravel.style', function ($app) {
            return new FastLaravel\Http\Output\Style();
        });
        return app('fast.laravel.style');
    }
}

if (! function_exists('supportColor')) {
    /**
     * Returns true if STDOUT supports colorization.
     * This code has been copied and adapted from
     * \Symfony\Component\Console\Output\OutputStream.
     * @return boolean
     */
    function supportColor(): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return
                '10.0.10586' === PHP_WINDOWS_VERSION_MAJOR . '.' . PHP_WINDOWS_VERSION_MINOR . '.' . PHP_WINDOWS_VERSION_BUILD ||
                // 0 == strpos(PHP_WINDOWS_VERSION_MAJOR . '.' . PHP_WINDOWS_VERSION_MINOR . PHP_WINDOWS_VERSION_BUILD, '10.') ||
                false !== getenv('ANSICON') ||
                'ON' === getenv('ConEmuANSI') ||
                'xterm' === getenv('TERM')// || 'cygwin' === getenv('TERM')
                ;
        }

        if (!\defined('STDOUT')) {
            return false;
        }

        return isInteractive(STDOUT);
    }
}
if (! function_exists('isInteractive')) {
    /**
     * Returns if the file descriptor is an interactive terminal or not.
     *
     * @param  int|resource $fileDescriptor
     *
     * @return boolean
     */
    function isInteractive($fileDescriptor): bool
    {
        return \function_exists('posix_isatty') && @posix_isatty($fileDescriptor);
    }
}

if (! function_exists('isSwooleConsole')) {
    /**
     * 判断是否启动swoole服务
     *
     * @return boolean
     */
    function isSwooleConsole()
    {
        global $argv;

        if (is_array($argv)) {
            foreach ($argv as $arg) {
                if (in_array($arg, ['http', 'fast', 'http:start'])) {
                    return true;
                }
            }
        }
        
        return false;
    }
}

if (! function_exists('isCoContext')) {
    /**
     * Whether it is coroutine context
     *
     * @return bool
     */
    function isCoContext(): bool
    {
        return Swoole\Coroutine::getuid() > 0;
    }
}


if (! function_exists('isWorkerStatus')) {
    /**
     * 当前是否是worker状态
     * @see https://wiki.swoole.com/wiki/page/424.html
     *
     * @return bool
     */
    function isWorkerStatus(): bool
    {
        $server = app('swoole.server') ?? null;
        if ($server && \property_exists($server, 'taskworker') && ($server->taskworker === false)) {
            return true;
        }
        return false;
    }
}

if (! function_exists('isTaskWorkerStatus')) {
    /**
     * 当前是否是task worker状态
     * @see https://wiki.swoole.com/wiki/page/424.html
     *
     * @return bool
     */
    function isTaskWorkerStatus(): bool
    {
        $server = app('swoole.server') ?? null;
        if ($server && \property_exists($server, 'taskworker') && ($server->taskworker === true)) {
            return true;
        }
        return false;
    }
}

if (! function_exists('isTesting')) {
    /**
     * 判断是否处于测试状态
     * @return bool
     */
    function isTesting()
    {
        return defined('PHPUNIT') && PHPUNIT;
    }
}

