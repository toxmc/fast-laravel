<?php

namespace FastLaravel\Http\Process;

use Swoole\Process;
use Swoole\Coroutine;
use FastLaravel\Http\Server\ApplicationTask;
use FastLaravel\Http\Context\Debug;
use FastLaravel\Http\Facade\Show;
use FastLaravel\Http\Process\Crontab\Crontab;
use FastLaravel\Http\Process\BaseProcess;

/**
 * Crond
 *
 * Class Crond
 *
 * @package FastLaravel\Http\Process
 */
class Crond extends BaseProcess
{
    /**
     * @var null| Crontab
     */
    protected $crontab = null;

    /**
     * run crond
     *
     * @param Process $process
     *
     * @return null
     */
    public function run(Process $process)
    {
        Show::info("starting crond.");
        Coroutine::create(function() {
            $this->initLaravel();
            $this->initCrontab();
            $this->crontab->tick();
            $this->crontab->dispatch();
       });
    }

    /**
     * init laravel
     */
    private function initLaravel()
    {
        ApplicationTask::make('laravel', base_path());
        app()->instance('context.debug', new Debug());
    }

    /**
     * int crontab
     */
    private function initCrontab()
    {
        if (!$this->crontab) {
            $this->crontab = new Crontab();
        }
    }
}
