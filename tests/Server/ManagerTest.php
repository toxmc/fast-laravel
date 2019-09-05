<?php

namespace Tests\Server;

use Mockery as m;
use FastLaravel\Http\Tests\TestCase;

use ReflectionMethod;
use Swoole\Table;
use Swoole\Http\Server as HttpServer;
use Swoole\Http\Request;
use Swoole\Http\Response;
use FastLaravel\Http\Server\Manager;
use FastLaravel\Http\Server\Sandbox;
use \FastLaravel\Http\Server\Application;
use FastLaravel\Http\Server\AccessOutput;
use \FastLaravel\Http\Output\Output;
use FastLaravel\Http\Util\Logger;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as IlluminateContainer;
use Illuminate\Config\Repository;
use FastLaravel\Http\Table\SwooleTable;
use Laravel\Lumen\Exceptions\Handler;
use Illuminate\Support\Facades\Config;
use FastLaravel\Http\Server\Facades\Server;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Config\Repository as ConfigContract;

class ManagerTest extends TestCase
{
    static $manage = null;
    protected $config = [
        'app.name' => 'Laravel_Test',
        'swoole_http.websocket.enabled' => false,
        'swoole_http.tables' => [
            'table_name' => [
                'size' => 1024,
                'columns' => [
                    ['name' => 'column_name', 'type' => Table::TYPE_STRING, 'size' => 1024],
                ],
            ],
        ],
        'swoole_http.server' => [
            'host' => '127.0.0.1',
            'port' => 9501,
        ],
        'swoole_http.server.options' => [
                'daemonize'             => false,
                'sandbox_mode'          => false,
                'reactor_num'           => 1,
                'worker_num'            => 1,
                'task_worker_num'       => 0,
                'task_enable_coroutine' => 1,
                // The data to receive can't be larger than buffer_output_size.
                'package_max_length'    => 20 * 1024 * 1024,
                // The data to send can't be larger than buffer_output_size.
                'buffer_output_size'    => 10 * 1024 * 1024,
                // Max buffer size for socket connections
                'socket_buffer_size'    => 128 * 1024 * 1024,
                // Worker will restart after processing this number of request
                'max_request'           => 3000,
                // Enable coroutine send
                'send_yield'            => true,
                // You must add --enable-openssl while compiling Swoole
                'ssl_cert_file'         => null,
                'ssl_key_file'          => null,
            ],
        'swoole_http.server.options.pid_file' =>  __DIR__."/../fixtures/laravel/storage/logs/swoole_http.pid",
    ];

    protected static $basePath =  __DIR__ . '/../fixtures';

    public function testGetFramework()
    {
        $manager = $this->getManager(null, 'laravel', static::$basePath);
        $this->assertSame('laravel', $manager->getFramework());
    }

    public function testGetBasePath()
    {
        $path = __DIR__ . '/../fixtures';
        $manager = $this->getManager(null, 'laravel', static::$basePath);
        $this->assertSame($path, $manager->getBasePath());
    }

    public function testStart()
    {
        $server = $this->getServer();
        $server->shouldReceive('start')->once();
        $container = $this->getContainer($server);
        $manager = $this->getManager($container, 'laravel', static::$basePath);
        $manager->start();
    }

    public function testStop()
    {
        $server = $this->getServer();
        $server->shouldReceive('shutdown')->once();

        $container = $this->getContainer($server);
        $manager = $this->getManager($container);
        $manager->stop();
    }

    public function testOnStart()
    {
        $server = $this->getServer();
        $container = $this->getContainer($server);
        $container->singleton('events', function () {
            return $this->getEvent('start');
        });
        $manager = $this->getManager($container, 'laravel', static::$basePath);
        $ret = $manager->onStart($server);
        $this->assertNull($ret);
    }

    public function testOnManagerStart()
    {
        $server = $this->getServer();
        $container = $this->getContainer($server);
        $container->singleton('events', function () {
            return $this->getEvent('managerStart');
        });
        $manager = $this->getManager($container);
        $manager->onManagerStart($server);
    }

    public function testCreateApplication()
    {
        $server = $this->getServer();
        $container = $this->getContainer($server);
        $manager = $this->getManager($container, 'laravel', static::$basePath);

        $application = $this->callFunction($manager, 'createApplication');
        $this->assertTrue($application instanceof Application);
    }

    public function testSetLaravelApp()
    {
        $server = $this->getServer();
        $container = $this->getContainer($server);
        $manager = $this->getManager($container, 'laravel', static::$basePath);

        $this->callFunction($manager, 'createApplication');
        $this->callFunction($manager, 'setLaravelApp');
        $var = $this->callVariable($manager, 'app');

        $this->assertTrue($var instanceof IlluminateContainer);
    }

    public function testGetPidFile()
    {
        $manager = $this->getManager();
        $pidFile = $this->callFunction($manager, 'getPidFile');
        $this->assertEquals($pidFile, __DIR__."/../fixtures/laravel/storage/logs/swoole_http.pid");
    }

    public function testCreatePidFile()
    {
        $manager = $this->getManager();
        $result = $this->callFunction($manager, 'createPidFile');
        $this->assertNotEmpty($result);
    }

    public function testRemovePidFile()
    {
        $manager = $this->getManager();
        $result = $this->callFunction($manager, 'removePidFile');
        $this->assertNotEmpty($result);
    }

    public function testSetProcessName()
    {
        if (PHP_OS === 'Darwin') {
            return;
        }

        $title = 'fast-laravel';
        $manager = $this->getManager();
        $newMethod = function () use ($title) {
            return $this->setProcessName($title);
        };
        $method = $newMethod->bindTo($manager, $manager);
        $method();

        $name = sprintf('%s: %s', $this->config['app.name'], $title);
        $processName = cli_get_process_title();
        $this->assertEquals($name, $processName);
    }

    protected function getManager($container = null, $framework = 'laravel', $path = '/')
    {
         return new Manager($container ?: $this->getContainer(), $framework, $path);
    }

    protected function getContainer($server = null, $config = null)
    {
        $server = $server ?? $this->getServer();
        $config = $config ?? $this->getConfig();
        $container = new Container();

        $container->singleton(ConfigContract::class, function () use ($config) {
            return $config;
        });
        $container->alias(ConfigContract::class, 'config');

        $container->singleton(HttpServer::class, function () use ($server) {
            return $server;
        });
        $container->alias(HttpServer::class, 'swoole.server');
        $container->singleton(ExceptionHandler::class, Handler::class);

        return $container;
    }

    protected function getServer()
    {
        $server = m::mock('HttpServer');
        $server->shouldReceive('on');
        $server->taskworker = false;
        $server->master_pid = -1;

        return $server;
    }

    protected function getEvent($name, $default = true)
    {
        $event = m::mock('event')
            ->shouldReceive('dispatch')
            ->with($name, m::any())
            ->once();

        $event = $default ? $event->with($name, m::any()) : $event->with($name);

        return $event->getMock();
    }

    protected function getConfig($websocket = false)
    {
        $config = new Repository($websocket ? $this->websocketConfig : $this->config);
        return $config;
    }

    protected function mockMethod($name, \Closure $function, $namespace = null)
    {
        parent::mockMethod($name, $function, 'FastLaravel\Http\Server');
    }
}
