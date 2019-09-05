<?php

namespace FastLaravel\Http\Task;

use FastLaravel\Http\Task\Helper\TaskHelper;
use FastLaravel\Http\Task\Helper\PhpHelper;

/**
 * Class TaskExecutor
 *
 * @package FastLaravel\Http\Task
 */
class TaskExecutor
{
    /**
     * Coroutine task
     */
    const TYPE_CO = 'co';

    /**
     * Async task
     */
    const TYPE_ASYNC = 'async';

    private $taskSpace;

    public function __construct($taskSpace="\\App\\Tasks\\")
    {
        $this->taskSpace = $taskSpace;
    }

    /**
     * 执行任务
     * @param string $data
     * @return mixed
     */
    public function run(string $data)
    {
        $data = TaskHelper::unpack($data);

        $name = ucfirst($data['name']);
        $type = $data['type'];
        $method = $data['method'];
        $params = $data['params'];
        if (strpos($name, ltrim($this->taskSpace, '\\')) !== false) {
            $class = $name;
        } else {
            $class = $this->taskSpace . $name;
        }
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("class:{$class} not exist, please check it!");
        }
        $task = new $class();

        $result = $this->runTask($task, $method, $params, $name, $type);
        return $result;
    }

    /**
     * 执行task任务
     *
     * @param object $task
     * @param string $method
     * @param array $params
     * @param string $name
     * @param string $type
     *
     * @return mixed
     */
    private function runTask($task, $method, $params, $name, $type)
    {
        $result = PhpHelper::call([$task, $method], $params);
        return $result;
    }
}