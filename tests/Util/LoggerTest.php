<?php

namespace FastLaravel\Http\Tests\Util;

use Mockery;
use FastLaravel\Http\Tests\TestCase;
use FastLaravel\Http\Util\Logger;
use FastLaravel\Http\Output\Output;

class LoggerTest extends TestCase
{
    protected $logger = null;

    public function setUp()
    {
        $this->logger = new Logger();
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testLogException()
    {
        $this->logger->log('DEBUGg', '这是错误');
    }

    public function testLog()
    {
        $this->expectOutputRegex("#\[[0-9\:\- ]{19}\].\[debug\].fast-laravel\:#");
        $this->logger->log('debug', '这是错误', [
            'test' => 123,
            'foo' => 'bar',
            'func1' => [
                'method' => 'foo'
            ]
        ]);
    }

}