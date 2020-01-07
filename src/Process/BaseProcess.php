<?php

namespace FastLaravel\Http\Process;

use Swoole\Process;
use FastLaravel\Http\Facade\Show;

/**
 * Class BaseProcess
 *
 * @package FastLaravel\Http\Process
 */
abstract class BaseProcess
{
    /**
     * @var
     */
    protected $serverPid;

    /**
     * @var \swoole_process
     */
    private $swooleProcess;

    /**
     * @var string
     */
    private $processName;

    /**
     * @var bool|null
     */
    private $async = null;

    /**
     * @var array
     */
    private $args = [];

    /**
     * BaseProcess constructor.
     * @param string $processName
     * @param array $args
     * @param bool $async
     */
    final function __construct(string $processName, array $args = [], $async = true)
    {
        $this->processName = $processName;
        $this->args = $args;
        $this->async = $async;
        $this->setProcess(new \swoole_process([$this, 'start']));
    }

    /**
     * @param Process $process
     */
    public function setProcess(Process $process)
    {
        $this->swooleProcess = $process;
    }

    /**
     * @return Process
     */
    public function getProcess(): Process
    {
        return $this->swooleProcess;
    }

    /**
     * 服务启动后才能获得到pid
     */
    public function getPid():?int
    {
        if (isset($this->swooleProcess->pid)) {
            return $this->swooleProcess->pid;
        } else {
            return null;
        }
    }

    /**
     * 启动自定义进程
     *
     * @param Process $process
     */
    public function start(Process $process)
    {
        if (PHP_OS != 'Darwin') {
            //swoole_set_process_name别名
            $process->name($this->getProcessName());
        }

        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
        }

        Process::signal(SIGTERM, function () use ($process) {
            $this->onShutDown();
            swoole_event_del($process->pipe);
            $this->swooleProcess->exit(0);
        });
        if ($this->async) {
            swoole_event_add($this->swooleProcess->pipe, function () {
                $msg = $this->swooleProcess->read(64 * 1024);
                $this->onReceive($msg);
            });
        }
        $this->run($this->swooleProcess);
    }

    /**
     * @return array
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed|null
     */
    public function getArg($key, $default=null)
    {
        if (isset($this->args[$key]) && !empty($this->args[$key])) {
            return $this->args[$key];
        } else {
            return $default;
        }
    }

    /**
     * @return string
     */
    public function getProcessName()
    {
        return $this->processName;
    }

    /**
     * Reload server.
     */
    protected function reloadServer()
    {
        $pid = $this->getServerPid();

        if (!$this->isRunning($pid)) {
            Show::error("Failed! There is no swoole_http_server process running.");
            exit(1);
        }

        Show::writeln('Reloading swoole_http_server...');

        $isRunning = $this->killProcess($pid, SIGUSR1);

        if (!$isRunning) {
            Show::writeln('> failure');
            exit(1);
        }

        Show::writeln('> success');
    }

    /**
     * Kill process.
     *
     * @param int $pid
     * @param int $sig
     * @param int $wait
     *
     * @return bool
     */
    protected function killProcess($pid, $sig, $wait = 0)
    {
        Process::kill($pid, $sig);

        if ($wait) {
            $start = time();

            do {
                if (!$this->isRunning($pid)) {
                    break;
                }

                usleep(100000);
            } while (time() < $start + $wait);
        }

        return $this->isRunning($pid);
    }

    /**
     * If Swoole process is running.
     *
     * @param int $pid
     *
     * @return bool
     */
    protected function isRunning($pid)
    {
        if (!$pid) {
            return false;
        }

        return Process::kill($pid, 0);
    }

    /**
     * Get pid.
     *
     * @return int|null
     */
    protected function getServerPid()
    {
        if ($this->serverPid) {
            return $this->serverPid;
        }

        $pid = null;
        $path = $this->getServerPidPath();

        if (file_exists($path)) {
            $pid = (int)file_get_contents($path);

            if (!$pid) {
                $this->removePidFile();
            } else {
                $this->serverPid = $pid;
            }
        }

        return $this->serverPid;
    }

    /**
     * Get Pid file path.
     *
     * @return string
     */
    protected function getServerPidPath()
    {
        return config('swoole_http.server.options.pid_file');
    }

    /**
     * Remove Pid file.
     * @return boolean
     */
    protected function removePidFile()
    {
        if (file_exists($this->getServerPidPath())) {
            return unlink($this->getServerPidPath());
        }
        return false;
    }

    /**
     * @param Process $process
     *
     * @return mixed
     */
    public abstract function run(Process $process);

}