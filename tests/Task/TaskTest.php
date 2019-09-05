<?php

namespace Tests\task;

use Mockery;
use Swoole\Http\Server as HttpServer;
use FastLaravel\Http\Task\Task;
use FastLaravel\Http\Exceptions\TaskException;
use FastLaravel\Http\Tests\TestCase;

class TaskTest extends TestCase
{
    /**
     * @expectedException FastLaravel\Http\Exceptions\TaskException
     */
    public function testIllegalDeliverType()
    {
        Task::deliver('test', 'func', [], 'test');
    }

    public function testDeliverCo()
    {
        $server = $this->getServer();
        $server->shouldReceive('taskCo')->once()->andReturn([['test']]);
        app()->instance('swoole.server', $server);
        Task::deliver('test', 'func', [], Task::TYPE_CO);
    }

    public function testDeliverAsync()
    {
        $server = $this->getServer();
        $server->shouldReceive('task')->once()->andReturn(1);
        app()->instance('swoole.server', $server);
        Task::deliver('test', 'func', [], Task::TYPE_ASYNC);
    }

    public function testCo()
    {
        $server = $this->getServer();
        $server->shouldReceive('taskCo')->once()->andReturn([['test1'], ['test2']]);
        app()->instance('swoole.server', $server);

        $tasks = [
            [
                 'name'   => 'name1',
                 'method' => 'method1',
                 'params' => ['1'],
            ],
            [
                'name'   => 'name2',
                'method' => 'method2',
                'params' => ['2'],
            ],
        ];

        $result = Task::co($tasks);
        $this->assertEquals(count($result), 2);
    }

    public function testAsync()
    {
        $server = $this->getServer();
        $server->shouldReceive('task')->twice()->andReturn(1, 2);

        $tasks = [
            [
                'name'   => 'name1',
                'method' => 'method1',
                'params' => ['1'],
            ],
            [
                'name'   => 'name2',
                'method' => 'method2',
                'params' => ['2'],
            ],
        ];

        app()->instance('swoole.server', $server);
        $result = Task::async($tasks);
        $this->assertEquals(count($result), 2);
    }

    protected function getServer()
    {
        $server = Mockery::mock('HttpServer');
        $server->shouldReceive('on');
        $server->taskworker = false;
        $server->master_pid = -1;

        return $server;
    }
}
