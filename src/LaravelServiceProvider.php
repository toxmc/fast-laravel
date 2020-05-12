<?php

namespace FastLaravel\Http;

use Illuminate\Support\ServiceProvider;
use FastLaravel\Http\Server\Manager;
use FastLaravel\Http\Commands\HttpServerCommand;

class LaravelServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the service provider. Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole() && isSwooleConsole()) {
            //发布配置
            $this->publishes([
                __DIR__ . '/../config/swoole_http.php'      => base_path('config/swoole_http.php'),
                __DIR__ . '/../fast'                        => base_path('fast'),
            ], 'fast-laravel');
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->runningInConsole() && isSwooleConsole()) {
            $this->mergeConfigs();
            $this->registerManager();
            $this->registerCommands();
        }
    }

    /**
     * Merge configurations.
     */
    protected function mergeConfigs()
    {
        //合并覆盖默认配置，app中只需要填写需要修改的配置
        $this->mergeConfigFrom(__DIR__ . '/../config/swoole_http.php', 'swoole_http');
    }

    /**
     * 注册 manager.
     *
     * @return void
     */
    protected function registerManager()
    {
        // 绑定单例服务
        $this->app->singleton('fast.laravel', function ($app) {
            return new Manager($app, 'laravel');
        });
    }

    /**
     * 注册 commands.
     */
    protected function registerCommands()
    {
        $this->commands([
            HttpServerCommand::class,
        ]);
    }

}
