<?php

namespace FastLaravel\Http\Task;

/**
 * Task annotation collector
 */
class TaskCollector
{

    /**
     * @var array
     */
    private static $tasks = [];

    /**
     * @var array
     */
    private static $crons = [];

    /**
     * collect the annotation of task
     *
     * @param string $className
     * @param boolean $coroutine
     */
    public static function collectTask($className, $coroutine)
    {
        if (isset(self::$tasks['mapping'][$className])) {
            return;
        }
        self::$tasks['mapping'][$className] = $className;
        self::$tasks['task'][$className] = [
            $className,
            $coroutine
        ];
    }

    /**
     * collect the annotation of Scheduled
     *
     * @param string $className
     * @param string $methodName
     * @param string $taskName
     * @param string $cron
     */
    public static function collectScheduled($className, $methodName, $taskName, $cron)
    {
        if (isset(self::$tasks['mapping'][$className])) {
            return;
        }

        self::$tasks['mapping'][$className] = $taskName;

        self::$tasks['crons'][] = [
            'cron'      => $cron,
            'task'      => $taskName,
            'method'    => $methodName,
            'className' => $className,
        ];
    }

    /**
     * @return array
     */
    public static function getCollector(): array
    {
        return self::$tasks;
    }

}