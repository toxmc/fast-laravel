<?php

namespace FastLaravel\Http\Commands;

use Illuminate\Console\Command;
use FastLaravel\Http\Facade\Show;
use Swoole\Process;

/**
 * laravel artisan 命令控制器
 *
 * Class HttpServerCommand
 * @package FastLaravel\Http\Commands
 */
class HttpServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'http {action : start|stop|restart|reload|infos|config|publish} 
    {--d|daemonize : Whether run as a daemon for start & restart}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Swoole HTTP Server controller.';

    /**
     * The console command action. start|stop|restart|reload
     *
     * @var string
     */
    protected $action;

    /**
     *
     * The pid.
     *
     * @var int
     */
    protected $pid;

    /**
     * The configs for this package.
     *
     * @var array
     */
    protected $configs;

    /**
     * 执行控制台命令入口
     *
     * @return void
     */
    public function handle()
    {
        $this->checkEnvironment();
        $this->checkAction();
        $this->loadConfigs();
        $this->runAction();
    }

    /**
     * 环境检测
     */
    protected function checkEnvironment()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            throw new \RuntimeException("Swoole extension doesn't support Windows OS yet.");
        } elseif (!extension_loaded('swoole')) {
            throw new \RuntimeException("Can't detect Swoole extension installed.");
        }
    }

    /**
     * 命令检测
     */
    protected function checkAction()
    {
        $this->action = $this->argument('action');

        if (in_array($this->action, ['start', 'stop', 'restart', 'reload'])) {
            Show::writeLogo();
            $this->error("The argument '{$this->action}'. has been migrated to fast.");
            $this->comment("Please Usage: php fast http:{$this->action}");
            exit(1);
        }
        if (!in_array($this->action, ['infos', 'config', 'publish'])) {
            Show::writeLogo();
            $this->error("Invalid argument '{$this->action}'. Expected 'config', 'infos' or 'publish'.");
            $this->comment("Please Usage: php artisan http config | infos or publish.");
            exit(1);
        }
    }

    /**
     * 加载配置
     */
    protected function loadConfigs()
    {
        $this->configs = $this->laravel['config']->get('swoole_http');
    }

    /**
     * 执行子命令
     */
    protected function runAction()
    {
        $this->{$this->action}();
    }

    public function publish()
    {
        $basePath = base_path();
        $configPath = $basePath . '/config/swoole_http.php';
        $taskMiddlewarePath = $basePath . '/app/Http/Middleware/TaskWorker.php';
        $startListener = $basePath . '/app/Listeners/StartListener.php';
        $workerStartListener = $basePath . '/app/Listeners/WorkerStartListener.php';

        $todoList = [
            ['from' => realpath(__DIR__ . '/../../config/swoole_http.php'), 'to' => $configPath, 'mode' => 0644],
            ['from' => realpath(__DIR__ . '/../../fast'), 'to' => $basePath . '/fast', 'mode' => 0755, 'link' => true],
            ['from' => realpath(__DIR__ . '/../Middleware/TaskWorker.php'), 'to' => $taskMiddlewarePath, 'mode' => 0755, 'link' => true],
            ['from' => realpath(__DIR__ . '/../Listeners/StartListener.php'), 'to' => $startListener, 'mode' => 0755, 'link' => true],
            ['from' => realpath(__DIR__ . '/../Listeners/WorkerStartListener.php'), 'to' => $workerStartListener, 'mode' => 0755, 'link' => true],
        ];

        foreach ($todoList as $id => $todo) {
            $configPath = $todo['to'];
            if (file_exists($configPath)) {
                $choice = $this->anticipate("<error>{$configPath}</error> already exists, do you want to override it ? Y/N",
                    ['Y', 'N'],
                    'N'
                );
                if (!$choice || strtoupper($choice) !== 'Y') {
                    unset($todoList[$id]);
                }
            }
        }

        foreach ($todoList as $todo) {
            $toDir = dirname($todo['to']);
            if (!is_dir($toDir)) {
                mkdir($toDir, 0755, true);
            }
            if (file_exists($todo['to'])) {
                unlink($todo['to']);
            }
            $operation = 'Copied';
            if (empty($todo['link'])) {
                copy($todo['from'], $todo['to']);
            } else {
                if (@link($todo['from'], $todo['to'])) {
                    $operation = 'Linked';
                } else {
                    copy($todo['from'], $todo['to']);
                }

            }
            chmod($todo['to'], $todo['mode']);
            $this->line("<info>{$operation} file</info> <comment>[{$todo['from']}]</comment> <info>To</info> <comment>[{$todo['to']}]</comment>");
        }
        return true;
    }

    /**
     * 显示信息.
     */
    protected function infos()
    {
        $pid = $this->getPid();
        $isRunning = $this->isRunning($pid);
        $appName = $this->laravel['config']->get('app.name');
        $port = $this->configs['server']['port'];
        $host = $this->configs['server']['host'];
        $reactorNum = $this->configs['server']['options']['reactor_num'];
        $workerNum = $this->configs['server']['options']['worker_num'];
        $taskWorkerNum = $this->configs['server']['options']['task_worker_num'];
        $sandboxMode = $this->config('server')['sandbox_mode'] ?? true;
        $logFile = $this->configs['server']['options']['log_file'];

        $table = [
            ['App Name', $appName],
            ['PHP Version', phpversion()],
            ['Swoole Version', swoole_version()],
            ['Laravel Version', $this->getApplication()->getVersion()],
            ['Listen IP', $host],
            ['Listen Port', $port],
            ['Reactor Num', $reactorNum],
            ['Worker Num', $workerNum],
            ['Task Worker Num', $taskWorkerNum],
            ['Sandbox Mode', $sandboxMode ? 'On' : 'Off'],
            ['Log Path', $logFile],
            ['Server Status', $isRunning ? 'Online' : 'Offline'],
            ['PID', $isRunning ? $pid : 'None'],
        ];

        $this->table(['Name', 'Value'], $table);
    }

    /**
     * prepare config
     *
     * @return int
     */
    protected function config()
    {
        $config = [
            'app'       => [
                'name'  => $this->laravel['config']->get('app.name'),
                'env'   => $this->laravel['config']->get('app.env'),
                'debug' => $this->laravel['config']->get('app.debug'),
            ],
            'server'    => $this->configs,
            'websocket' => $this->laravel['config']->get('swoole_websocket'),
            'laravel'   => [
                'root_path' => base_path(),
                'version'   => $this->getApplication()->getVersion(),
                '_SERVER'   => $_SERVER,
                '_ENV'      => $_ENV,
            ]
        ];
        $config = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $filePath = base_path('storage/fast_laravel.json');
        $ret = file_put_contents($filePath, $config);
        if ($ret) {
            $this->info("The configuration file was saved successfully.");
            $this->info("file path:".$filePath);
        } else {
            $this->error("Failed to save the configuration file.");
        }
        return $ret;
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

        Process::kill($pid, 0);

        return !swoole_errno();
    }

    /**
     * Get pid.
     *
     * @return int|null
     */
    protected function getPid()
    {
        if ($this->pid) {
            return $this->pid;
        }

        $pid = null;
        $path = $this->getPidPath();

        if (file_exists($path)) {
            $pid = (int)file_get_contents($path);

            if (!$pid) {
                $this->removePidFile();
            } else {
                $this->pid = $pid;
            }
        }

        return $this->pid;
    }

    /**
     * Get Pid file path.
     *
     * @return string
     */
    protected function getPidPath()
    {
        return $this->configs['server']['options']['pid_file'];
    }

    /**
     * Remove Pid file.
     */
    protected function removePidFile()
    {
        if (file_exists($this->getPidPath())) {
            unlink($this->getPidPath());
        }
    }

    /**
     * Return daemon config.
     */
    protected function isDaemon()
    {
        return $this->configs['server']['options']['daemonize'];
    }
}
