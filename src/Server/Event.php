<?php

namespace FastLaravel\Http\Server;

/**
 * Class Event
 * fast laravel支持监听的事件
 * @package FastLaravel\Http\Server
 */
class Event
{
    /**
     * 对应 onStart 回调
     */
    const START = 'start';

    /**
     * 对应 onManagerStart 回调
     */
    const MANAGER_START = 'managerStart';

    /**
     * 对应 onWorkerStart 回调
     */
    const WORKER_START = 'workerStart';

    /**
     * 对应 onFinish 回调
     */
    const FINISH = 'finish';

    /**
     * 对应 onWorkerStop 回调
     */
    const WORKER_STOP = 'workerStop';

    /**
     * 对应 onShutdown 回调
     */
    const SHUTDOWN = 'shutdown';
}
