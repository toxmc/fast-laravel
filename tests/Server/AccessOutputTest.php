<?php
namespace FastLaravel\Http\Server;
function microtime($flag=false)
{
    return '1567389063.5578';
}

namespace Tests\Server;
function microtime($flag=false)
{
    return '1567389063.5578';
}

use Mockery;
use Illuminate\Http\Request;
use FastLaravel\Http\Tests\TestCase;
use FastLaravel\Http\Output\Output;
use FastLaravel\Http\Server\AccessOutput;

class AccessOutputTest extends TestCase
{
    protected $output = null;

    protected $access = null;

    public function setUp()
    {
        $this->access = new AccessOutput($this->output);
    }

    public function testStyle()
    {
        $style = $this->callFunction($this->access, 'style', '400');
        $this->assertEquals($style, 'error');
        $style = $this->callFunction($this->access, 'style', '200');
        $this->assertEquals($style, 'info');
    }

    public function testDate()
    {
        $data = new \DateTime();
        $this->assertEquals($this->callFunction($this->access, 'date', $data), date('Y/m/d H:i:s'));
    }

    public function testLog()
    {
        $host = 'www.test.com';
        $method = 'GET';
        $agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $date = date('Y/m/d H:i:s', $requestTime = time());
        $status = 500;
        $requestTimeFloat = 1567389062.5578;
        $style = 'error';
        $useTime = round((microtime(true) - $requestTimeFloat) * 1000);
        $string = sprintf("<cyan>%s</cyan> <yellow>%s</yellow> %s <$style>%d</$style> %s <yellow>%s</yellow>ms\n",
            $date,
            $method,
            $host,
            $status,
            $agent,
            $useTime
        );
        $this->expectOutputString(\Style()->t((string)$string));

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('url')->andReturn($host);
        $request->shouldReceive('method')->andReturn($method);
        $request->shouldReceive('userAgent')->andReturn($agent);
        $request->shouldReceive('server')->with('REQUEST_TIME')->andReturn($requestTime);
        $request->shouldReceive('server')->with('REQUEST_TIME_FLOAT')->andReturn($requestTimeFloat);
        $this->access->log($request, null);
    }
}