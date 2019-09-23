<?php

namespace FastLaravel\Http\Task;

use FastLaravel\Http\Util\Facades\Logger;
use FastLaravel\Http\Exceptions\TaskException;
use FastLaravel\Http\Task\Helper\TaskHelper;
use FastLaravel\Http\Context\Request;

/**
 * 复杂的task，主要用于实现task中也可以使用worker中的请求信息
 *
 * Class ComplexTask
 * @package FastLaravel\Http\Task
 */
class ComplexTask implements TaskInterface
{
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
    public static function deliver($taskName, $methodName, $params = [], $type = self::TYPE_CO, $timeout = 3)
    {
        if (!in_array($type, [static::TYPE_CO, static::TYPE_ASYNC], false)) {
            throw new TaskException('Invalid task type.');
        }

        $data = TaskHelper::pack($taskName, $methodName, $params, $type, Request::getRequestInfo());
        if (!isWorkerStatus() && !isCoContext()) {
            return static::deliverByQueue($data);
        }

        if (!isWorkerStatus() && isCoContext()) {
            throw new TaskException('Deliver in non-worker environment, please deliver the task via HTTP request.');
        }

        $server = app('swoole.server');

        switch ($type) {
            case static::TYPE_CO:{
                $result = $server->taskCo([$data], $timeout)[0];
                break;
            }
            case static::TYPE_ASYNC:
            default:{
                // Deliver async task
                $result =  $server->task($data);
                break;
            }
        }
        return $result;
    }

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
    public static function async(array $tasks): array
    {
        $server = app('swoole.server');
        $result = [];
        foreach ($tasks as $task) {
            if (! isset($task['name']) || ! isset($task['method']) || ! isset($task['params'])) {
                Logger::warning(sprintf('Task %s format error.', $task['name'] ?? '[UNKNOWN]'));
                continue;
            }

            $data = TaskHelper::pack($task['name'], $task['method'], $task['params'], static::TYPE_ASYNC, Request::getRequestInfo());
            $result[] = $server->task($data);
        }

        return $result;
    }

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
    public static function co(array $tasks, $timeout=3)
    {
        $taskCos = [];
        foreach ($tasks as $task) {
            if (! isset($task['name']) || ! isset($task['method']) || ! isset($task['params'])) {
                Logger::warning(sprintf('Task %s format error.', $task['name'] ?? '[UNKNOWN]'));
                continue;
            }

            $taskCos[] = TaskHelper::pack($task['name'], $task['method'], $task['params'], static::TYPE_CO, Request::getRequestInfo());
        }

        $result = [];
        if (! empty($taskCos)) {
            $result = app('swoole.server')->taskCo($taskCos, $timeout);
        }

        return $result;
    }

}
