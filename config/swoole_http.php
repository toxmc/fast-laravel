<?php

use Swoole\Table;
use FastLaravel\Http\Server\Event;

return [
    /*
    |--------------------------------------------------------------------------
    | HTTP server configurations.
    |--------------------------------------------------------------------------
    | @see https://wiki.swoole.com/wiki/page/274.html
    */
    'server'    => [
        'host'                => env('SWOOLE_HTTP_HOST', '127.0.0.1'),
        'port'                => env('SWOOLE_HTTP_PORT', '9100'),
        'public_path'         => base_path('public'),
        // Determine if to use swoole to respond request for static files
        'handle_static_files' => env('SWOOLE_HANDLE_STATIC', true),
        'hot_reload'          => env('SWOOLE_HOT_RELOAD', false),
        'hot_reload_type'     => env('SWOOLE_HOT_RELOAD_TYPE', ''),// inotify or tick
        'hot_reload_paths'    => [
            base_path('app'),
            base_path('config'),
        ],
        // If the memory limit(byte) is exceeded, the service is restarted at the end of the request
        'worker_memory_limit' => env('SWOOLE_WORKER_MEMORY_LIMIT', 200 * 1024 * 1024),
        'task_memory_limit'   => env('SWOOLE_TASK_MEMORY_LIMIT', 200 * 1024 * 1024),
        'enable_coroutine'    => env('SWOOLE_RUNTIME_ENABLE_COROUTINE', false),
        'enable_access_log'   => env('SWOOLE_ENABLE_ACCESS_LOG', false),
        'options' => [
            'pid_file'              => env('SWOOLE_HTTP_PID_FILE', base_path('storage/logs/swoole_http.pid')),
            'log_file'              => env('SWOOLE_HTTP_LOG_FILE', base_path('storage/logs/swoole_http.log')),
            'daemonize'             => env('SWOOLE_HTTP_DAEMONIZE', false),
            // Normally this value should be 1~4 times larger according to your cpu cores.
            'reactor_num'           => env('SWOOLE_HTTP_REACTOR_NUM', swoole_cpu_num()),
            'worker_num'            => env('SWOOLE_HTTP_WORKER_NUM', swoole_cpu_num()),
            'task_worker_num'       => env('SWOOLE_HTTP_TASK_WORKER_NUM', swoole_cpu_num()),
            'task_enable_coroutine' => env('SWOOLE_HTTP_TASK_ENABLE_CO', false),
            // The data to receive can't be larger than buffer_output_size.
            'package_max_length'    => 20 * 1024 * 1024,
            // The data to send can't be larger than buffer_output_size.
            'buffer_output_size'    => 10 * 1024 * 1024,
            // Max buffer size for socket connections
            'socket_buffer_size'    => 128 * 1024 * 1024,
            // Worker will restart after processing this number of request
            'max_request'           => 3000,
            // Enable coroutine send
            'send_yield'            => true,
            // You must add --enable-openssl while compiling Swoole
            'ssl_cert_file'         => null,
            'ssl_key_file'          => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 开启sandbox
    |--------------------------------------------------------------------------
    */
    'sandbox_mode' => env('SWOOLE_SANDBOX_MODE', true),

    /*
    |--------------------------------------------------------------------------
    | task工作空间
    |--------------------------------------------------------------------------
    */
    'task_space' => "\App\\Tasks\\",

    /*
    |--------------------------------------------------------------------------
    | HTTP server APM tracker.
    |--------------------------------------------------------------------------
    | based on xhgui,tideways or tideways_xhprof.
    | see:https://github.com/tideways/php-xhprof-extension
    | see:https://github.com/perftools/xhgui
    */
    'tracker' => [
        'enable'   => false,
        'handler'  => 'mongodb', // only support mongodb and file
        'filename' => function ($url) {
            $time = microtime(true);
            $url = substr(md5($url), 0, 6);
            return storage_path("tracker/xhgui.data.{$time}_{$url}");
        },

        'db' => [
            'host'    => 'mongodb://localhost:27017/xhprof',
            'db'      => 'xhprof',
            'options' => [],
        ],

        // Profile 1 in 100 requests. You can return true to profile every request.
        'profiler' => [
            'enable'      => function ($illuminateRequest) {
                return (bool) $illuminateRequest->cookie('enable_apm'); //rand(1, 100) === 42;
            },
            'simple_url'  => function ($url) {
                return preg_replace('/\=\d+/', '', $url);
            },
            'filter_path' => [
                '/api/test'
            ]
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 连接池配置信息
    |--------------------------------------------------------------------------
    */
    'pool' => [
        'default' => [
            'name'        => 'mysql',
            'minActive'   => 8,
            'maxActive'   => 8,
            'maxWait'     => 8,
            'timeout'     => 8,
            'maxIdleTime' => 60,
            'maxWaitTime' => 3,
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Enable to turn on websocket server.
    |--------------------------------------------------------------------------
    */
    'websocket' => [
        'enabled' => env('SWOOLE_HTTP_WEBSOCKET', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | User-defined processes
    | eg:  \App\Service\UserProcesses::class => 'processName'
    |--------------------------------------------------------------------------
    */
    'processes' => [
        // FastLaravel\Http\Process\Crond::class => 'crond',
    ],

    /*
    |--------------------------------------------------------------------------
    | User-defined crontab jobs
    | 规则：秒 分 时 日 月 周
    |--------------------------------------------------------------------------
    */
    'crontab' => [
        [
            // 任务说明信息
            'class'  => App\Service\FooTask::class,
            'method' => 'execute',
            'cron'   => '* * * * * *',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Console output will be transferred to response content if enabled.
    |--------------------------------------------------------------------------
    */
    'ob_output' => env('SWOOLE_OB_OUTPUT', true),

    /*
    |--------------------------------------------------------------------------
    | Instances here will be cleared on every request.
    |--------------------------------------------------------------------------
    */
    'instances' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Providers here will be registered on every request.
    |--------------------------------------------------------------------------
    */
    'providers' => [
        Illuminate\Pagination\PaginationServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | The event listener mappings for the application.
    | event => listener
    |
    | @see: class FastLaravel\Http\Server\Event
    |--------------------------------------------------------------------------
    */
    'listens' => [
        // Event::START => [
        //     'App\Listeners\StartListener',
        // ],
        // Event::WORKER_START => [
        //     'App\Listeners\WorkerStartListener',
        // ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Define your swoole tables here.
    |
    | @see https://wiki.swoole.com/wiki/page/p-table.html
    |--------------------------------------------------------------------------
    */
    'tables'    => [
        // 'table_name' => [
        //     'size' => 1024,
        //     'columns' => [
        //         ['name' => 'id', 'type' => Table::TYPE_INT, 'size' => 4],
        //         ['name' => 'name', 'type' => Table::TYPE_STRING, 'size' => 64],
        //         ['name' => 'num', 'type' => Table::TYPE_FLOAT, 'size' => 8],
        //         ['name' => 'column_name', 'type' => Table::TYPE_STRING, 'size' => 1024],
        //     ]
        // ],
    ]
];
