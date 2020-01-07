<?php declare(strict_types=1);

namespace FastLaravel\Http\Process\Crontab;

use FastLaravel\Http\Exceptions\CrontabException;
use FastLaravel\Http\Task\Helper\PhpHelper;
use FastLaravel\Http\Facade\Show;
use Swoole\Timer;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/**
 * Class Crontab
 */
class Crontab
{
    /**
     * Seconds
     *
     * @var float
     */
    private $tickTime = 1;

    /**
     * @var int
     */
    private $maxTask = 10;

    /**
     * @var Channel
     */
    private $channel;

    public function __construct()
    {
        $this->init();
    }

    /**
     * Init
     */
    public function init(): void
    {
        $this->channel = new Channel($this->maxTask);
    }

    /**
     * Tick task
     */
    public function tick(): void
    {
        $crontab = config('swoole_http.crontab');
        CrontabRegister::registerCron($crontab);
        Timer::tick($this->tickTime * 1000, function () {
            // All task
            $tasks = CrontabRegister::getCronTasks(time());
            // Push task to channel
            foreach ($tasks as $task) {
                $this->channel->push($task);
            }
        });
    }

    /**
     * Exec task
     */
    public function dispatch(): void
    {
        while (true) {
            $task = $this->channel->pop();
            Coroutine::create(function () use ($task) {
                try {
                    // Execute task
                    list($className, $methodName) = $task;
                    $this->execute($className, $methodName);
                } catch (CrontabException $e) {
                    Show::error($e->getMessage());
                }
            });
        }
    }

    /**
     * @param string $className
     * @param string $methodName
     *
     * @throws CrontabException
     */
    public function execute(string $className, string $methodName): void
    {
        if (!class_exists($className)) {
            throw new CrontabException(
                sprintf('Crontab(name=%s method=%s) class is not exist!', $className, $methodName)
            );
        }

        $object = app($className);
        if (!method_exists($object, $methodName)) {
            throw new CrontabException(
                sprintf('Crontab(name=%s method=%s) method is not exist!', $className, $methodName)
            );
        }

        PhpHelper::call([$object, $methodName]);
    }
}
