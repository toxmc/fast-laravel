<?php
namespace Tests\Server;

use Mockery;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use FastLaravel\Http\Server\Application;
use FastLaravel\Http\Tests\TestCase;
use Illuminate\Contracts\Container\Container;

class ApplicationTest extends TestCase
{
    protected $application;

    protected $framework = 'Laravel';

    protected $sandbox;

    protected static $basePath =  __DIR__ . '/../fixtures';

    /**
     * 建立基境
     */
    protected function setUp()
    {
        $this->application = Application::make($this->framework, static::$basePath);
    }

    public function testMake()
    {
        $this->assertTrue($this->application instanceof Application);
    }

    public function testGetFramework()
    {
        $this->assertEquals($this->application->getFramework(), strtolower($this->framework));
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Not support framework "laravel".
     */
    public function testSetFrameworkException()
    {
        $this->callFunction($this->application, 'setFramework', 'lartasaa');
    }


    public function testGetBootstrappers()
    {
        $bootstrappers = $this->callFunction($this->application, 'getBootstrappers');
        $this->assertTrue(is_array($bootstrappers));
    }

    public function testKernel()
    {
        $this->assertTrue($this->application->kernel() instanceof \TestKernel);
    }

    public function testLoadApplication()
    {
        $app = $this->callFunction($this->application, 'loadApplication');
        $this->assertTrue($app instanceof Container);
    }
}
