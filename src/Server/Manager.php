<?php

namespace FastLaravel\Http\Server;

use Exception;
use Swoole\Http\Server as HttpServer;
use Swoole\WebSocket\Server as WebSocketServer;
use FastLaravel\Http\Task\TaskExecutor;
use FastLaravel\Http\Traits\{Logger,TableTrait};
use FastLaravel\Http\Context\Request;
use FastLaravel\Http\Context\Response;
use FastLaravel\Http\Context\Debug;
use FastLaravel\Http\Process\HotReload;
use FastLaravel\Http\Database\ConnectionResolver;
use Illuminate\Support\Facades\Facade;
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
        $serverClass = HttpServer::class;
        $host = $this->container['config']->get('swoole_http.server.host');
        $port = $this->container['config']->get('swoole_http.server.port');
        $hasCert = $this->container['config']->get('swoole_http.server.options.ssl_cert_file');
        $hasKey = $this->container['config']->get('swoole_http.server.options.ssl_key_file');
        $args = $hasCert && $hasKey ? [SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL] : [];

        self::$server = new $serverClass($host, $port, ...$args);

        if ($this->container['config']->get('swoole_http.server.hot_reload', false)) {
            self::$server->addProcess((new HotReload('HotReload'))->getProcess());
        }
    }

    /**
     * Set swoole server configurations.
     */
    protected function configureServer()
    {
        $config = $this->container['config']->get('swoole_http.server.options');
        self::$server->set($config);
    }

    /**
     * Set swoole server callback.
     */
    protected function setServerCallback()
    {
        foreach ($this->events as $event) {
            $method = 'on' . ucfirst($event);
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

        // set application to sandbox environment
        $this->sandbox = Sandbox::make($this->getApplication());
    }

    /**
     * "onRequest" callback.
     *
     * @param \Swoole\Http\Request $swooleRequest
     * @param \Swoole\Http\Response $swooleResponse
     */
    public function onRequest($swooleRequest, $swooleResponse)
    {
        // transform swoole request to illuminate request
        $illuminateRequest = Request::make($swooleRequest)->toIlluminate();

        try {
            // handle static file request first
            $handleStatic = $this->container['config']->get('swoole_http.handle_static_files', true);
            if ($handleStatic && $this->handleStaticRequest($illuminateRequest, $swooleResponse)) {
                return;
            }

            // set current request to sandbox
            $this->sandbox->setRequest($illuminateRequest);
            $application = $this->sandbox->getApplication();

            // enable sandbox
            $this->sandbox->enable();

            // handle request via laravel's dispatcher
            $illuminateResponse = $application->handle($illuminateRequest);
            $response = Response::make($illuminateResponse, $swooleResponse);
            $response->send();
            $application->terminate($illuminateRequest, $illuminateResponse);

        } catch (Exception $e) {
            try {
                $exceptionResponse = $this->app[ExceptionHandler::class]->render($illuminateRequest, $e);
                $response = Response::make($exceptionResponse, $swooleResponse);
                $response->send();
            } catch (Exception $e) {
                $this->logServerError($e);
            }
        } finally {
            // request's access log
            if ($this->container['config']->get('swoole_http.server.enable_access_log', false)) {
                $this->accessOutput->log($illuminateRequest, $illuminateResponse ?? null);
            }
            // disable and recycle sandbox resource
            $this->sandbox->disable();
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
            $taskExecutor = $this->app->instance('task.executor', new TaskExecutor(
                app('config')->get('swoole_http.task_space')
            ));
            $result = $taskExecutor->run($data);
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
     *
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

        $publicPath = $this->container['config']->get('swoole_http.server.public_path', base_path('public'));
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
        $this->app->make('context.debug')->reset();
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
     */
    protected function setProcessName($process)
    {
        if (PHP_OS === static::MAC_OSX) {
            return;
        }
        $appName = $this->container['config']->get('app.name', 'fast_laravel');
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
        $this->app->make(ExceptionHandler::class)->report($e);
    }
}
