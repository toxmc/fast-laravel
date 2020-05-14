<?php

namespace FastLaravel\Http\Commands;

use Swoole\Process;
use Inhere\Console\Controller;
use Illuminate\Config\Repository;
use Illuminate\Events\Dispatcher;
use Illuminate\Contracts\Queue\Factory as QueueFactoryContract;
use FastLaravel\Http\Server\Manager;

/**
 * fast-laravel 自带命令行工具控制器
 *
 * Class HttpController
 * @package FastLaravel\Http\Commands
 */
class HttpController extends Controller
{
    /**
     * 命令组名称
     *
     * @var string
     */
    protected static $name = 'http';

    /**
     * The logo.
     *
     * @var string
     */
    protected static $logo = '        
  ___                   _                             _ 
 / __)          _      | |                           | |
| |__ ____  ___| |_    | | ____  ____ ____ _   _ ____| |
|  __) _  |/___)  _)   | |/ _  |/ ___) _  | | | / _  ) |
| | ( ( | |___ | |__   | ( ( | | |  ( ( | |\ V ( (/ /| |
|_|  \_||_(___/ \___)  |_|\_||_|_|   \_||_| \_/ \____)_|                                             
';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $description = 'Swoole HTTP Server for laravel\'s controller.';

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
     * laravel container app
     *
     * @var \Illuminate\Container\Container
     */
    protected $laravelApp;

    /**
     * 执行控制台命令入口
     *
     * @return void
     */
    protected function init() :void
    {
        self::$description .= self::$logo;
        parent::init();
        $this->checkEnvironment();
        $this->loadConfigs();
        $this->laravelApp = require_once BASE_PATH.'/bootstrap/app.php';
    }

    /**
     * Start fast-laravel http server
     *
     * @usage {command} [--opt ...]
     * @options
     *  -d, --daemon  Whether run as a daemon for start & restart
     *  -a, --access_log It's will display access log on every request.
     *
     * @example php fast http:start
     *  php fast http:start -d
     */
    public function startCommand()
    {
        if ($this->isRunning($this->getPid())) {
            $this->output->error('> Failed! swoole_http_server process is already running.');
            exit(1);
        }

        $host = $this->config('server')['server']['host'];
        $port = $this->config('server')['server']['port'];

        if ($this->input->getOpt('d') || $this->input->getLongOpt('daemon')) {
            $this->configs['server']['server']['options']['daemonize'] = true;
        }

        if ($this->input->getOpt('a') || $this->input->getLongOpt('access_log')) {
            $this->configs['server']['server']['enable_access_log'] = true;
        }

        $this->output->info('> Starting swoole http server...');
        $this->output->info("> Swoole http server started: <http://{$host}:{$port}>");
        if ($this->isDaemon()) {
            $this->output->info('> (You can run this command to ensure the ' .
                'swoole_http_server process is running: php fast http:infos)');
        } else {
            $this->output->writeln("> You can use <info>CTRL + C </info> to stop run.\n");
        }
        $this->laravelApp->instance('config', new Repository([
            'app'         => $this->config('app'),
            'swoole_http' => $this->config('server')
        ]));

        // 注册swoole回调监听事件
        $listens = $this->config('server')['listens'] ?? [];
        $this->laravelApp->singleton('events', function ($app) use($listens) {
            $event = (new Dispatcher($app))->setQueueResolver(function () use ($app) {
                return $app->make(QueueFactoryContract::class);
            });
            foreach ($listens as $eventName => $listeners) {
                foreach ($listeners as $listener) {
                    $event->listen($eventName, $listener);
                }
            }
            return $event;
        });

        $this->output->table($this->getInfos(false), 'fast laravel http info.');
        (new Manager($this->laravelApp, 'laravel'))->start();
    }

    /**
     * Stop fast-laravel server
     *
     * @usage {command} [--opt ...]
     * @options
     *  -f, --force  Whether force kill process.
     *
     * @example php fast http:stop [-f or --force]
     */
    public function stopCommand()
    {
        $sig = SIGTERM;
        if ($this->input->getOpt('f') || $this->input->getLongOpt('force')) {
            $sig = SIGKILL;
        }

        $pid = $this->getPid();
        if (!$this->isRunning($pid)) {
            $this->output->error("> Failed! There is no swoole_http_server process running.");
            exit(1);
        }
        $this->output->info('> Stopping swoole http server...');

        $isRunning = $this->killProcess($pid, $sig, 15);
        if ($isRunning) {
            $this->output->error('> Unable to stop the swoole_http_server process.');
            exit(1);
        }

        $this->removePidFile();
        $this->output->info('> success');
    }

    /**
     * Restart fast-laravel server
     *
     * @usage {command} [--opt ...]
     * @options
     *  -f, --force  Whether force kill process.
     *
     * @example php fast http:stop [-f or --force]
     */
    public function restartCommand()
    {
        $pid = $this->getPid();

        if ($this->isRunning($pid)) {
            $this->stopCommand();
        }

        $this->startCommand();
    }

    /**
     * Reload fast-laravel server
     *
     * @example php fast http:reload
     */
    public function reloadCommand()
    {
        $pid = $this->getPid();

        if (!$this->isRunning($pid)) {
            $this->output->error("> Failed! There is no swoole_http_server process running.");
            exit(1);
        }

        $this->output->info('> Reloading swoole_http_server...');

        $isRunning = $this->killProcess($pid, SIGUSR1);

        if (!$isRunning) {
            $this->output->error('> failure');
            exit(1);
        }

        $this->output->info('> success');
    }

    /**
     * Display fast-laravel information
     *
     * @example php fast http:infos
     */
    public function infosCommand()
    {
        $this->output->table($this->getInfos(true), 'fast laravel http info.');
    }

    /**
     * get infos
     * @param bool $printStatus
     * @return array
     */
    protected function getInfos($printStatus=true)
    {
        $pid = $this->getPid();
        $isRunning = $this->isRunning($pid);
        $appName = $this->config('app')['name'];
        $host = $this->config('server')['server']['host'];
        $port = $this->config('server')['server']['port'];
        $reactorNum = $this->config('server')['server']['options']['reactor_num'];
        $workerNum = $this->config('server')['server']['options']['worker_num'];
        $taskWorkerNum = $this->config('server')['server']['options']['task_worker_num'];
        $sandboxMode = $this->config('server')['sandbox_mode'] ?? true;
        $logFile = $this->config('server')['server']['options']['log_file'];
        $data = [
            ['id' => 1,'name' => 'App Name','value' => $appName],
            ['id' => 1,'name' => 'PHP Version','value' => phpversion()],
            ['id' => 2,'name' => 'Swoole Version','value' => swoole_version()],
            ['id' => 3,'name' => 'Laravel Version','value' => $this->config('laravel')['version']],
            ['id' => 4,'name' => 'Listen IP','value' => $host],
            ['id' => 5,'name' => 'Listen Port','value' => $port],
            ['id' => 6,'name' => 'Reactor Num','value' => $reactorNum],
            ['id' => 7,'name' => 'Worker Num','value' => $workerNum],
            ['id' => 8,'name' => 'Task Worker Num','value' => $taskWorkerNum],
            ['id' => 9,'name' => 'Sandbox Mode','value' => $sandboxMode ? 'On' : 'Off'],
            ['id' => 10,'name' => 'Log Path','value' => $logFile],
        ];

        if ($printStatus) {
            $data[] = ['id' => 11,'name' => 'Server Status','value' => $isRunning ? 'Online' : 'Offline'];
            $data[] = ['id' => 12,'name' => 'PID','value' => $isRunning ? $pid : 'None'];
        }
        return $data;
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
     * 加载配置
     */
    protected function loadConfigs()
    {
        $file = BASE_PATH . '/storage/fast_laravel.json';
        if (!file_exists($file)) {
            $this->output->error("> first, you need use command(php artisan http config) to generate a json config for fast-laravel.");
            exit(1);
        }
        $json = file_get_contents($file);
        $this->configs = (array)json_decode($json, true);
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
        return $this->config('server')['server']['options']['pid_file'];
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
        return $this->config('server')['server']['options']['daemonize'];
    }

    /**
     * 返回配置信息
     *
     * @param $name
     *
     * @return array
     */
    public function config($name)
    {
        return $this->configs[$name] ?? [];
    }
}
