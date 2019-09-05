<?php

namespace FastLaravel\Http\Server;

use Illuminate\Http\Request;
use Illuminate\Container\Container;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ApplicationTask
{
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
     * @var \Illuminate\Container\Container
     */
    protected $application;

    /**
     * @var \Illuminate\Contracts\Http\Kernel
     */
    protected $kernel;

    /**
     * Aliases for pre-resolving.
     *
     * @var array
     */
    protected $resolves = [
        'view', 'files','db', 'db.factory', 'cache', 'cache.store',
        'config', 'encrypter', 'hash', 'translator', 'log'
    ];

    /**
     * Make an application.
     *
     * @param string $framework
     * @param string $basePath
     * @return \FastLaravel\Http\Server\Application
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

        if ($this->framework === 'laravel') {
            $bootstrappers = $this->getBootstrappers();
            $application->bootstrapWith($bootstrappers);
        } elseif (is_null(Facade::getFacadeApplication())) {
            $application->withFacades();
        }

        $this->preResolveInstances($application);
    }

    /**
     * Load application.
     *
     * @return \Illuminate\Contracts\Foundation\Application
     */
    protected function loadApplication()
    {
        return require $this->basePath . '/bootstrap/app.php';
    }

    /**
     * @return \Illuminate\Container\Container
     */
    public function getApplication()
    {
        if (! $this->application instanceof Container) {
            $this->application = $this->loadApplication();
        }

        return $this->application;
    }

    /**
     * @return \Illuminate\Contracts\Http\Kernel
     */
    public function getKernel()
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
     * @param \Illuminate\Http\Request $request
     * @return SymfonyResponse
     */
    public function run(Request $request)
    {
        // handle request with laravel (runLaravel)
        $response = $this->getKernel()->handle($request);

        // 处理终止逻辑
        $this->terminate($request, $response);
        return $response;
    }

    /**
     * Get bootstrappers.
     *
     * @return array
     */
    protected function getBootstrappers()
    {
        $kernel = $this->getKernel();

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
     * @param \Illuminate\Http\Request $request
     * @param \Illuminate\Http\Response $response
     */
    public function terminate(Request $request, $response)
    {
        $this->getKernel()->terminate($request, $response);
    }

    /**
     * Resolve some instances before request
     */
    protected function preResolveInstances($application)
    {
        foreach ($this->resolves as $resolve) {
            if ($application->offsetExists($resolve)) {
                $application->make($resolve);
            }
        }
    }

    /**
     * Rebind laravel's container in kernel.
     */
    protected function rebindKernelContainer($kernel)
    {
        $application = $this->application;

        $closure = function () use ($application) {
            $this->app = $application;
        };

        $resetKernel = $closure->bindTo($kernel, $kernel);
        $resetKernel();
    }

    /**
     * Clone laravel app and kernel while being cloned.
     */
    public function __clone()
    {
        $application = clone $this->application;

        $this->application = $application;

        if ($this->framework === 'laravel') {
            $this->rebindKernelContainer($this->getKernel());
        }
    }
}
