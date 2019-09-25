<?php

namespace Tests\Table;

use Mockery as m;
use FastLaravel\Http\Task\Task;
use FastLaravel\Http\Task\Helper\TaskHelper;
use FastLaravel\Http\Tests\TestCase;

class TaskHelperTest extends TestCase
{
    public $task = null;

    public $taskData = null;

    public function setUp()
    {
        $taskName = 'test';
        $methodName = 'foo';
        $params = [
            '1',
            '3'
        ];
        $type = Task::TYPE_CO;
        $requestInfo = ['test' => 'test'];

        $this->task = [
            'name'         => $taskName,
            'method'       => $methodName,
            'params'       => $params,
            'type'         => $type,
            'request_info' => $requestInfo
        ];
        $this->taskData = TaskHelper::pack($taskName, $methodName, $params, $type, $requestInfo);
    }

    public function testPack()
    {
        $this->assertEquals($this->taskData, serialize($this->task));
    }

    public function testUnpack()
    {
        $this->assertEquals(TaskHelper::unpack($this->taskData), $this->task);
    }

}
