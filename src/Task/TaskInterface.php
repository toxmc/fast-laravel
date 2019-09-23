<?php

namespace FastLaravel\Http\Task;

use FastLaravel\Http\Util\Facades\Logger;
use FastLaravel\Http\Exceptions\TaskException;
use FastLaravel\Http\Task\Helper\TaskHelper;

interface TaskInterface
{
    /**
     * taskco
     */
    const TYPE_CO = 'co';

    /**
     * Async task
     */
    const TYPE_ASYNC = 'async';

    /**
     * Deliver a taskco or async task
     *
     * @param string $taskName 也就是任务类的名称
     * @param string $methodName
     * @param array $params
     * @param string $type
     * @param int $timeout
     *
     * @return bool|array|int
     * @throws TaskException
     */
    public static function deliver($taskName, $methodName, $params = [], $type = self::TYPE_CO, $timeout = 3);

    /**
     * Deliver multiple asynchronous tasks
     *
     * @param array $tasks
     *  $task = [
     *      'name'   => string $taskName,
     *      'method' => string $methodName,
     *      'params' => array $params,
     *  ];
     *
     * @return array
     */
    public static function async(array $tasks): array;


    /**
     * Deliver multiple taskco
     *
     * @param array $tasks
     *  $tasks = [
     *      'name'   => $taskName,
     *      'method' => $methodName,
     *      'params' => $params,
     *  ];
     *
     * @return array
     */
    public static function co(array $tasks, $timeout=3);
}
