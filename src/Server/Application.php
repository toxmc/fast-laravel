<?php

namespace FastLaravel\Http\Server;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Container\Container;
use Illuminate\Contracts\Http\Kernel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Application
{
    /**
     * load laravel Application.
     *
     * @var Container
     */

    protected static $loadApplication = null;
    /**
     * Current framework.
     *
     * @var string
     */
    protected $framework;

    /**
     * The framework base path.
     *
     * @var string
     */
    protected $basePath;

    /**
     * Laravel Application.
     *
     * @var Container
     */
    protected $application;

    /**
     * @var Kernel
     */
    protected $kernel;

    /**
     * Aliases for pre-resolving.
     *
     * @var array
     */
    protected $resolves = [
        'view', 'files', 'session', 'session.store', 'routes',
        'db', 'db.factory', 'cache', 'cache.store', 'config', 'cookie',
        'encrypter', 'hash', 'router', 'translator', 'url', 'log'
    ];

    /**
     * @var bool
     */
    protected $enableCoroutine = false;

    /**
     * @var bool
     */
    protected $obOutput = false;

    /**
     * Make an application.
     *
     * @param string $framework
     * @param string $basePath
     * @return Application
     */
    public static function make($framework, $basePath = null)
    {
        return new static($framework, $basePath);
    }

    /**
     * Application constructor.
     *
     * @param string $framework
     * @param string $basePath
     * @throws
     */
    public function __construct($framework, $basePath = null)
    {
        $this->setFramework($framework);
        $this->setBasePath($basePath);
        $this->bootstrap();
    }

    /**
     * Bootstrap framework.
     */
    protected function bootstrap()
    {
        $application = $this->getApplication();
        $application->bootstrapWith($this->getBootstrappers());
        $this->enableCoroutine = $application->make('config')->get('swoole_http.enable_coroutine', false);
        $this->obOutput = $application->make('config')->get('swoole_http.ob_output', true);

        $this->preResolveInstances($application);
    }

    /**
     * @return Container
     */
    public function getApplication()
    {
        if (! $this->application instanceof Container) {
            $this->application = $this->loadApplication();
        }
        return $this->application;
    }

    /**
     * Load application.
     *
     * @return Container
     */
    protected function loadApplication()
    {
        if (!static::$loadApplication) {
            static::$loadApplication = require $this->basePath . '/bootstrap/app.php';
        }
        return static::$loadApplication;
    }

    /**
     * @return Kernel
     * @throws
     */
    public function kernel()
    {
        if (! $this->kernel instanceof Kernel) {
            $this->kernel = $this->getApplication()->make(Kernel::class);
        }

        return $this->kernel;
    }

    /**
     * Get application framework.
     */
    public function getFramework()
    {
        return $this->framework;
    }

    /**
     * Run framework.
     *
     * @param Request $request
     * @return SymfonyResponse
     * @throws
     */
    public function handle(Request $request)
    {
        // 检测是否开启runtime::enableCoroutine
        if ($this->enableCoroutine) {
            \Swoole\Runtime::enableCoroutine();
        }
        // 检测是否开启ob_output
        $this->obOutput && ob_start();
        $response = $this->kernel()->handle($request);

        // 处理debug信息
        $debug = $request->get('debug_print', false);
        $debugStack = $debug ? $this->application->make('context.debug')->getAll() : [];

        $content = '';
        $isFile = false;
        if ($isStream = $response instanceof StreamedResponse) {
            $response->sendContent();
            $debug && var_export($debugStack);
        } elseif ($isFile = $response instanceof BinaryFileResponse) {
            $content = $response->getContent();
            $debug && var_export($debugStack);
        } elseif ($response instanceof RedirectResponse) {
            $content = $response->getContent();
            $debug && var_export($debugStack);
        } elseif ($response instanceof JsonResponse) {
            $content = $response->getContent();
            $responseContentArr = json_decode($content, true);
            if ($debug) {
                $responseContentArr['debug'] = $debugStack;
                $content = json_encode($responseContentArr);
            }
            $response->setContent($content);
        } elseif ($response instanceof SymfonyResponse) {
            $content = $response->getContent();
            if (!$content) {
                $content = ob_get_contents();
                ob_clean();
            }
            $content = $debug ? var_export($debugStack, true) . $content : $content;
            $response->setContent($content);
        }

        // set ob content to response
        if (! $isFile && ! $content && ob_get_length() > 0) {
            if ($isStream) {
                $response->output = ob_get_contents();
            } else {
                $response->setContent(ob_get_contents());
            }
            ob_end_clean();
        } elseif (ob_get_length()) {
            $obContent = ob_get_contents();
            ob_end_clean();
            echo $obContent;
        }
        return $response;
    }

    /**
     * Get bootstrappers.
     *
     * @throws
     * @return array
     */
    protected function getBootstrappers()
    {
        $kernel = $this->kernel();

        $reflection = new \ReflectionObject($kernel);

        $bootstrappersMethod = $reflection->getMethod('bootstrappers');
        $bootstrappersMethod->setAccessible(true);

        $bootstrappers = $bootstrappersMethod->invoke($kernel);

        array_splice($bootstrappers, -2, 0, ['Illuminate\Foundation\Bootstrap\SetRequestForConsole']);

        return $bootstrappers;
    }

    /**
     * Set framework.
     *
     * @param string $framework
     * @throws \Exception
     */
    protected function setFramework($framework)
    {
        $framework = strtolower($framework);

        if (! in_array($framework, ['laravel'])) {
            throw new \Exception(sprintf('Not support framework "%s".', $this->framework));
        }

        $this->framework = $framework;
    }

    /**
     * Set base path.
     *
     * @param string $basePath
     */
    protected function setBasePath($basePath)
    {
        $this->basePath = is_null($basePath) ? base_path() : $basePath;
    }

    /**
     * 执行请求生命周期的任何最终操作。
     *
     * @param Request $request
     * @param Response $response
     */
    public function terminate(Request $request, $response)
    {
        $this->kernel()->terminate($request, $response);
    }

    /**
     * 预初始化
     * @param Application $application
     * Resolve some instances before request.
     */
    protected function preResolveInstances($application)
    {
        foreach ($this->resolves as $resolve) {
            if ($application->offsetExists($resolve)) {
                $application->make($resolve);
            }
        }
    }
}
