<?php

namespace FastLaravel\Http\Task\Helper;

use FastLaravel\Http\Task\Task;

/**
 * The task helper
 */
class TaskHelper
{
    /**
     * @param string $taskName
     * @param string $methodName
     * @param array  $params
     * @param string $type
     *
     * @return string
     */
    public static function pack(string $taskName, string $methodName, array $params, string $type = Task::TYPE_CO): string
    {
        $task = [
            'name'   => $taskName,
            'method' => $methodName,
            'params' => $params,
            'type'   => $type,
        ];

        return serialize($task);
    }

    /**
     * @param string $data
     *
     * @return array
     */
    public static function unpack(string $data):array
    {
        return unserialize($data);
    }
}