<?php

namespace Tests\Server;


use Illuminate\Http\Request;
use Mockery;
use FastLaravel\Http\Server\Application;
use FastLaravel\Http\Server\Sandbox;
use FastLaravel\Http\Tests\TestCase;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\View\View;

class SandboxTest extends TestCase
{
    protected $application;

    protected $sandbox;

    protected static $basePath =  __DIR__ . '/../fixtures';

    /**
     * 建立基境
     */
    protected function setUp()
    {
        $this->application = new Application('laravel', self::$basePath);
        $this->sandbox = Sandbox::make($this->application);
    }

    public function testMake()
    {
        $this->assertTrue(Sandbox::make($this->application) instanceof Sandbox);
    }

    public function testIsFramework()
    {
        $result = $this->callFunction($this->sandbox, 'isFramework', 'laravel');
        $this->assertTrue($result);
    }

    public function testSetApplication()
    {
        $this->sandbox->setApplication($this->application);
        $application = $this->callVariable($this->sandbox, 'application');
        $this->assertTrue($application instanceof Application);
    }

    public function testGetApplicationAndGetLaravelApp()
    {
        $view = mockery::mock(View::class);
        $view->shouldReceive('with');

        $container = Mockery::mock(Container::class);
        $container->shouldReceive('make')->with('config')->andReturn(new Repository());
        $container->shouldReceive('make')->with('view')->andReturn($view);
        $container->shouldReceive('instance');
        $container->shouldReceive('offsetExists');

        $kernel = Mockery::mock(Kernel::class);

        $application = Mockery::mock(Application::class);
        $application->shouldReceive('getApplication')->andReturn($container);
        $application->shouldReceive('getFramework')->andReturn('Laravel');
        $application->shouldReceive('kernel')->andReturn($kernel);

        $this->sandbox->setApplication($application);

        $this->callFunction($this->sandbox, 'setInitialConfig');
        $application = $this->callFunction($this->sandbox, 'getApplication');

        $this->assertTrue($application instanceof Application);

        $container = $this->callFunction($this->sandbox, 'getLaravelApp');
        $this->assertTrue($container instanceof Container);
    }

    public function testResetConfigInstance()
    {
        $this->callFunction($this->sandbox, 'setInitialConfig');
        $contain = new Container();
        $this->callFunction($this->sandbox, 'resetConfigInstance', $contain);

        $newConfig = $contain->make('config');
        $this->assertTrue($newConfig instanceof Repository);
    }

    public function testRebindRequest()
    {
        $request = mockery::mock(Request::class);
        $this->sandbox->setRequest($request);
        $contain = new Container();
        $this->callFunction($this->sandbox, 'rebindRequest', $contain);

        $request = $contain->make('request');
        $this->assertTrue($request instanceof Request);
    }

    public function testDisable()
    {
        $request = mockery::mock(Request::class);
        $this->sandbox->setRequest($request);

        $this->callFunction($this->sandbox, 'disable');

        $this->assertNull($this->callVariable($this->sandbox, 'snapshot'));
        $this->assertNull($this->callVariable($this->sandbox, 'request'));
    }
}
