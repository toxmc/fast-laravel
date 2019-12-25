<?php

namespace FastLaravel\Http\Server;

use Exception;
use Swoole\Http\Server as HttpServer;
use FastLaravel\Http\Traits\{Logger,TableTrait};
use FastLaravel\Http\Context\Request;
use FastLaravel\Http\Context\TaskRequest;
use FastLaravel\Http\Context\Response;
use FastLaravel\Http\Context\Debug;
use FastLaravel\Http\Process\HotReload;
use FastLaravel\Http\Database\ConnectionResolver;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;

/**
 * 处理server各种回调和框架初始化
 *
 * Class Manager
 *
 * @package FastLaravel\Http\Server
 */
class Manager
{
    use TableTrait, Logger;

    const MAC_OSX = 'Darwin';

    /**
     * @var HttpServer | \Swoole\Websocket\Server
     */
    protected static $server;

    /**
     * Container.
     *
     * @var Container
     */
    protected $container;

    /**
     * @var Application
     */
    protected $application;

    /**
     * Laravel Application.
     *
     * @var \Illuminate\Container\Container
     */
    protected $app;

    /**
     * @var string
     */
    protected $framework;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var Sandbox
     */
    protected $sandbox;

    /**
     * @var AccessOutput
     */
    protected $accessOutput;

    /**
     * Server events.
     *
     * @var array
     */
    protected $events = [
        'start', 'shutDown', 'workerStart', 'workerStop', 'packet',
        'bufferFull', 'bufferEmpty', 'task', 'finish', 'pipeMessage',
        'workerError', 'managerStart', 'managerStop', 'request',
    ];

    /**
     * HTTP server manager constructor.
     *
     * @param Container $container
     * @param string $framework
     * @param string $basePath
     */
    public function __construct(Container $container, $framework, $basePath = null)
    {
        $this->container = $container;
        $this->framework = $framework;
        $this->basePath = $basePath;
        $this->initialize();
    }

    /**
     * @return string
     */
    public function getFramework()
    {
        return $this->framework;
    }

    /**
     * @return string|null
     */
    public function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * start swoole server.
     */
    public function start()
    {
        $this->container->make(HttpServer::class)->start();
    }

    /**
     * Stop swoole server.
     */
    public function stop()
    {
        $this->container->make(HttpServer::class)->shutdown();
    }

    /**
     * Initialize.
     */
    protected function initialize()
    {
        $this->setProcessName('manager process');
        $this->createTables();
        $this->registerServer();
        $this->setServerCallback();
        $this->createAccessOutput();
    }

    /**
     * register server
     */
    protected function registerServer()
    {
        $this->container->bindIf(HttpServer::class, function () {
            if (!static::$server) {
                $this->createServer();
                $this->configureServer();
            }
            return static::$server;
        });
        $this->container->alias(HttpServer::class, 'swoole.server');
    }

    /**
     * Create swoole server.
     */
    protected function createServer()
    {
        $config = $this->container->make('config');
        $host = $config->get('swoole_http.server.host');
        $port = $config->get('swoole_http.server.port');
        $hasCert = $config->get('swoole_http.server.options.ssl_cert_file');
        $hasKey = $config->get('swoole_http.server.options.ssl_key_file');
        $args = $hasCert && $hasKey ? [SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL] : [];

        self::$server = new HttpServer($host, $port, ...$args);
        $this->hotReloadProcess($config);
        $this->userInitProcesses($config);
    }

    /**
     * dev hot reload
     * @param $config
     */
    protected function hotReloadProcess($config)
    {
        if ($config->get('swoole_http.server.hot_reload', false)) {
            self::$server->addProcess((new HotReload(
                'HotReload', [
                    'hot_reload_type'  => $config->get('swoole_http.server.hot_reload_type', ''),
                    'hot_reload_paths' => $config->get('swoole_http.server.hot_reload_paths', []),
                ]
            ))->getProcess());
        }
    }

    /**
     * User-defined processes
     * @param $config
     */
    protected function userInitProcesses($config)
    {
        foreach ($config->get('swoole_http.processes', []) as $processes => $processesName) {
            if (class_exists($processes)) {
                self::$server->addProcess((new $processes($processesName))->getProcess());
            } else {
                Output()->writeln("<red>User-defined processes:{$processes} not exists.</red>");
            }
        }
    }

    /**
     * Set swoole server configurations.
     */
    protected function configureServer()
    {
        self::$server->set($this->container->make('config')->get('swoole_http.server.options'));
    }

    /**
     * Set swoole server callback.
     */
    protected function setServerCallback()
    {
        foreach ($this->events as $event) {
            $method = 'on' . ucfirst($event);
            if ($method == 'onTask' && $this->container->make('config')->get('swoole_http.server.options.task_enable_coroutine')) {
                $method .= "Co";
            }
            if (method_exists($this, $method)) {
                $callBack = [$this, $method];
            } else {
                $callBack = function () use ($event) {
                    $this->container->make('events')->dispatch($event, func_get_args());
                };
            }
            $this->container->make(HttpServer::class)->on($event, $callBack);
        }
    }

    /**
     * create AccessOutput
     */
    protected function createAccessOutput()
    {
        $this->accessOutput = new AccessOutput(Output());
    }

    /**
     * "onStart" callback.
     *
     * @param HttpServer $server
     * @throws
     */
    public function onStart($server)
    {
        $this->container->make('events')->dispatch('start', func_get_args());
        $this->setProcessName('master process');
        $this->createPidFile();
        if (isTesting()) {
            return;
        }
        Output()->writeln('Server has been started. ' .
            "(master PID: <cyan>{$server->master_pid}</cyan>, manager PID: <cyan>{$server->manager_pid}</cyan>)");
    }

    /**
     * "onManagerStart" callback.
     *
     * @param HttpServer $server
     * @throws
     */
    public function onManagerStart($server)
    {
        $this->container->make('events')->dispatch('managerStart', func_get_args());
        $this->setProcessName('manager process');
    }

    /**
     * "onWorkerStart" callback.
     *
     * @param HttpServer $server
     * @throws
     */
    public function onWorkerStart($server)
    {
        $this->container->make('events')->dispatch('workerStart', func_get_args());
        $this->clearCache();

        // init laravel app in task workers
        if ($server->taskworker) {
            $this->setProcessName('task');
            $this->createTaskApplication();
            $this->setLaravelApp();
            $this->bindToLaravelApp();
            return;
        }
        $this->setProcessName('worker');
        $this->createApplication();
        $this->setLaravelApp();
        $this->bindToLaravelApp();
        $this->setSandbox();
    }

    /**
     * "onRequest" callback.
     *
     * @param \Swoole\Http\Request $swooleRequest
     * @param \Swoole\Http\Response $swooleResponse
     * @throws
     */
    public function onRequest($swooleRequest, $swooleResponse)
    {
        // transform swoole request to illuminate request
        $illuminateRequest = Request::make($swooleRequest)->toIlluminate();

        try {
            // handle static file request first
            $handleStatic = $this->container->make('config')->get('swoole_http.handle_static_files', true);
            if ($handleStatic && $this->handleStaticRequest($illuminateRequest, $swooleResponse)) {
                return;
            }

            $application = $this->getFastApplication($illuminateRequest);
            // handle request via laravel's dispatcher
            $illuminateResponse = $application->handle($illuminateRequest);
            $response = Response::make($illuminateResponse, $swooleResponse);
            $response->send();
            $application->terminate($illuminateRequest, $illuminateResponse);
        } catch (Exception $e) {
            try {
                $this->logError($e);
                $exceptionResponse = $this->app[ExceptionHandler::class]->render($illuminateRequest, $e);
                $response = Response::make($exceptionResponse, $swooleResponse);
                $response->send();
            } catch (Exception $e) {
                $this->logServerError($e);
            }
        } finally {
            // request's access log
            if ($this->container->make('config')->get('swoole_http.server.enable_access_log', false)) {
                $this->accessOutput->log($illuminateRequest, $illuminateResponse ?? null);
            }
            // Reset on every request.
            $this->resetOnRequest();
        }
    }

    /**
     * Set onTask callback.
     *
     * @param HttpServer $server
     * @param int $taskId
     * @param int $srcWorkerId
     * @param mixed $data
     *
     * @return mixed
     */
    public function onTask(HttpServer $server, $taskId, $srcWorkerId, $data)
    {
        try {
            // transform swoole task data to illuminate request
            $taskRequest = TaskRequest::make($data);
            $result = $this->getApplication()->handle($taskRequest);
        } catch (Exception $e) {
            $this->warning($e->getMessage());
            $this->warning($e->getTraceAsString());
            $this->logServerError($e);
        } finally {
            $this->resetOnRequest();
        }
        return [
            'success' => isset($e) ? false : true,
            'result' => isset($e) ? $e->getMessage() : $result
        ];
    }

    /**
     * Set onTask callback for task coroutine.
     *
     * @param HttpServer $server
     * @param Swoole\Server\Task $task
     *
     * @return mixed
     */
    public function onTaskCo(HttpServer $server, $task)
    {
        try {
            // transform swoole task data to illuminate request
            $taskRequest = TaskRequest::make($task->data);
            $result = $this->getApplication()->handle($taskRequest);
        } catch (Exception $e) {
            $this->warning($e->getMessage());
            $this->warning($e->getTraceAsString());
            $this->logServerError($e);
        } finally {
            $this->resetOnRequest();
        }
        $task->finish([
            'success' => isset($e) ? false : true,
            'result' => isset($e) ? $e->getMessage() : $result
        ]);
    }

    /**
     * Set onFinish callback.
     */
    public function onFinish(HttpServer $server, $taskId, $data)
    {
    }

    /**
     * Set onWorkerStop callback.
     */
    public function onWorkerStop()
    {
    }

    /**
     * Set onShutdown callback.
     */
    public function onShutdown()
    {
        $this->removePidFile();
    }


    /**
     * Handle static file request.
     *
     * @param \Illuminate\Http\Request $illuminateRequest
     * @param \Swoole\Http\Response $swooleResponse
     * @throws
     * @return boolean
     */
    protected function handleStaticRequest($illuminateRequest, $swooleResponse)
    {
        $uri = $illuminateRequest->getRequestUri();
        $blackList = ['php', 'htaccess', 'config'];
        $extension = substr(strrchr($uri, '.'), 1);
        if ($extension && in_array($extension, $blackList)) {
            return;
        }

        $publicPath = $this->container->make('config')->get('swoole_http.server.public_path', base_path('public'));
        $filename = $publicPath . $uri;

        if (! is_file($filename) || filesize($filename) === 0) {
            return;
        }

        $swooleResponse->status(200);
        $mime = mime_content_type($filename);
        if ($extension === 'js') {
            $mime = 'text/javascript';
        } elseif ($extension === 'css') {
            $mime = 'text/css';
        }
        $swooleResponse->header('Content-Type', $mime);
        $swooleResponse->sendfile($filename);

        return true;
    }

    /**
     * Reset on every request.
     */
    protected function resetOnRequest()
    {
        if ($this->container->make('config')->get('swoole_http.sandbox_mode', true)) {
            // disable and recycle sandbox resource
            $this->sandbox->disable();
        }
        $this->app->make('context.debug')->reset();
        $this->memoryLeakCheck();
    }

    /**
     * Create application.
     */
    protected function createApplication()
    {
        return $this->application = Application::make(
            $this->getFramework(),
            $this->getBasePath()
        );
    }

    /**
     * Create task application.
     */
    protected function createTaskApplication()
    {
        return $this->application = ApplicationTask::make(
            $this->getFramework(),
            $this->getBasePath()
        );
    }

    /**
     * Get application.
     *
     * @return Application
     */
    protected function getApplication()
    {
        return $this->application;
    }

    /**
     * Set Laravel app.
     */
    protected function setLaravelApp()
    {
        $this->app = $this->getApplication()->getApplication();
    }

    /**
     * Get fast application.
     * @param \Illuminate\Http\Request $illuminateRequest
     * @return Application
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getFastApplication($illuminateRequest)
    {
        if ($this->container->make('config')->get('swoole_http.sandbox_mode', true)) {
            // set current request to sandbox and enable sandbox
            $this->sandbox->setRequest($illuminateRequest);
            $this->sandbox->enable();
            return $this->sandbox->getApplication();
        } else {
            return clone $this->getApplication();
        }
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function setSandbox()
    {
        // set application to sandbox environment
        if ($this->container->make('config')->get('swoole_http.sandbox_mode', true)) {
            $this->sandbox = Sandbox::make($this->getApplication());
        }
    }

    /**
     * 绑定服务容器
     *
     * Set bindings to Laravel app.
     */
    protected function bindToLaravelApp()
    {
        $this->bindLogger();
        $this->bindServer();
        $this->bindDebug();
        $this->bindTable();
        $this->bindDb();
    }

    /**
     * 绑定server到laravel服务容器 (Bind swoole server to Laravel app container.)
     */
    protected function bindServer()
    {
        $this->app->singleton(HttpServer::class, function () {
            return $this->container->make(HttpServer::class);
        });
        $this->app->alias(HttpServer::class, 'swoole.server');
    }

    /**
     * 绑定debug到laravel服务容器
     */
    protected function bindDebug()
    {
        $this->app->instance('context.debug', new Debug());
    }

    /**
     * 绑定pool到laravel服务容器
     */
    protected function bindDb()
    {
        $pool = $this->container->config['swoole_http']['pool'] ?? [];
        if ($pool) {
            $this->app->instance('pool.db', new ConnectionResolver(
                $this->app->config['database']['connections'],
                $pool,
                $this->app->config['database']['default']
            ));
        }
    }

    /**
     * Gets pid file path.
     *
     * @throws
     * @return string
     */
    protected function getPidFile()
    {
        return $this->container->make('config')->get('swoole_http.server.options.pid_file');
    }

    /**
     * Create pid file.
     */
    protected function createPidFile()
    {
        $pidFile = $this->getPidFile();
        $pid = $this->container->make(HttpServer::class)->master_pid;

        return file_put_contents($pidFile, $pid);
    }

    /**
     * Remove pid file.
     * @return boolean
     */
    protected function removePidFile()
    {
        $pidFile = $this->getPidFile();

        if (file_exists($pidFile)) {
            return unlink($pidFile);
        }
        return true;
    }

    /**
     * Clear APC or OPCache.
     *
     * onWorkerStart中执行apc_clear_cache或opcache_reset刷新OpCode缓存,否则reload失败
     */
    protected function clearCache()
    {
        if (function_exists('apc_clear_cache')) {
            apc_clear_cache();
        }

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }

    /**
     * Set process name.
     *
     * @param string $process
     * @throws
     */
    protected function setProcessName($process)
    {
        if (PHP_OS === static::MAC_OSX) {
            return;
        }
        $appName = $this->container->make('config')->get('app.name', 'fast_laravel');
        $name = sprintf('%s: %s', $appName, $process);
        swoole_set_process_name($name);
    }

    /**
     * Log server error.
     *
     * @throws
     * @param Exception $e
     */
    protected function logServerError(Exception $e)
    {
        if (isTesting()) {
            return;
        }
        $this->logError($e);
        $this->app->make(ExceptionHandler::class)->report($e);
    }

    /**
     * @param Exception $e
     */
    protected function logError(Exception $e)
    {
        Output()->writeln("<red>code:{$e->getCode()}</red>");
        Output()->writeln("<red>file:{$e->getFile()} {$e->getLine()}</red>");
        Output()->writeln("<red>message:{$e->getMessage()}</red>");
    }

    /**
     * memory leak check
     * If the memory limit is exceeded, the service is restarted at the end of the request
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function memoryLeakCheck()
    {
        if (isTaskWorkerStatus()) {
            $limit = $this->container->make('config')->get('swoole_http.server.task_memory_limit');
        } else {
            $limit = $this->container->make('config')->get('swoole_http.server.worker_memory_limit');
        }
        if ($limit) {
            $memory = memory_get_usage(true);
            if ($memory > $limit) {
                self::$server->stop(self::$server->worker_id, true);
            }
        }
    }
}
