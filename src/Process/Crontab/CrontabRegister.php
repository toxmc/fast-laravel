<?php declare(strict_types=1);

namespace FastLaravel\Http\Process\Crontab;

use FastLaravel\Http\Exceptions\CrontabException;
use FastLaravel\Http\Process\Crontab\CrontabExpression;

class CrontabRegister
{
    /**
     * @var array
     * @example
     * [
     *      [
     *          'class'=>xxx,
     *          'method'=>xxx,
     *          'cron'=>'* * * * * *'
     *      ]
     * ]
     */
    private static $crontabs = [];

    /**
     * @param array $crontab
     *
     * @throws CrontabException
     */
    public static function registerCron(array $crontab): void
    {
        foreach ($crontab as $cron) {
            $className = $cron['class'];
            $methodName = $cron['method'];
            $cronExpression = $cron['cron'];
            if (!CrontabExpression::parse($cronExpression)) {
                throw new CrontabException(
                    sprintf('`%s::%s()` `@Cron()` expression format is error', $className, $methodName)
                );
            }
            self::$crontabs[] = ['class' => $className, 'method' => $methodName, 'cron' => $cronExpression];
        }
    }

    /**
     * @param int $time
     *
     * @return array
     */
    public static function getCronTasks(int $time): array
    {
        $tasks = [];
        foreach (self::$crontabs as $crontab) {
            ['class' => $className, 'method' => $methodName, 'cron' => $cron] = $crontab;
            if (!CrontabExpression::parseObj($cron, $time)) {
                continue;
            }

            $tasks[] = [
                $className,
                $methodName
            ];
        }

        return $tasks;
    }
}
