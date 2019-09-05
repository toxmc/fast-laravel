<?php

namespace Tests\Process;

use Mockery as m;
use Swoole\Process;
use Illuminate\Config\Repository;
use FastLaravel\Http\Process\BaseProcess;
use FastLaravel\Http\Tests\TestCase;

class UserProcess extends BaseProcess
{
    /**
     * @param Process $process
     *
     * @return mixed
     */
    public function run(Process $process)
    {
    }

    /**
     * @return mixed
     */
    public function onShutDown()
    {
    }

    /**
     * @param string $str
     *
     * @return mixed
     */
    public function onReceive(string $str)
    {
    }
}

class ProcessTest extends TestCase
{
    public $process = null;

    public function setUp()
    {
        $this->process = new UserProcess('test', ['foo' => 'bar'], true);
    }

    public function testGetProcess()
    {
        $this->assertTrue($this->process->getProcess() instanceof Process);
    }

    public function testGetPid()
    {
        $pid = 1;
        $process = m::mock(Process::class);
        $process->pid = $pid;
        $this->process->setProcess($process);
        $this->assertEquals($this->process->getPid(), $pid);
    }

    public function testGetArgs()
    {
        $this->assertEquals($this->process->getArgs(), ['foo' => 'bar']);
    }

    public function testGetArg()
    {
        $this->assertEquals($this->process->getArg('foo'), 'bar');
        $this->assertEquals($this->process->getArg('bar'), null);
    }

    public function testGetProcessName()
    {
        $this->assertEquals($this->process->getProcessName(), 'test');
    }

    public function testGetServerPidPath()
    {
        $pidPath = __DIR__."/../fixtures/laravel/storage/logs/swoole_http.pid";
        $config = m::mock(Repository::class);
        $config->shouldReceive('get')->with('swoole_http.server.options.pid_file', null)
            ->andReturn($pidPath);

        app()->instance('config', $config);
        $this->assertEquals($pidPath, $this->callFunction($this->process, 'getServerPidPath'));
    }




}
