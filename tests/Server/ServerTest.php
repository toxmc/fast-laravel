<?php

namespace Tests\Server;

use Mockery;
use PHPUnit\Framework\TestCase;
use Swoole\Http\Server as HttpServer;

class ServerTest extends TestCase
{
    public function testStart()
    {
        $server = Mockery::mock(Server::class);
        $server->shouldReceive('on');
        $server->taskworker = false;
        $server->master_pid = -1;
        $server->shouldReceive('start')->once();
        $this->assertNull($server->start());
    }
}

class Server {
    public static $server = null;

    public function __construct()
    {
        $this->createServer();
        $this->configServer();
    }

    public function createServer()
    {
        static::$server = new HttpServer("0.0.0.0", 9501);
        static::$server->on('request', function ($req, $resp)
        {
            $info = file_get_contents("/root/code/test/info.log");
            echo strlen($info);
            $resp->end($info);
        });

        static::$server->on('task', function ($serv, $id)
        {
            //var_dump($serv);
        });

        static::$server->on('workerStart', function ($serv, $id)
        {
            //var_dump($serv);
        });
    }
    public function configServer(){
        static::$server->set([
            "pid_file" => "/mnt/c/Users/MeetYou/code/storage/logs/swoole_http.pid",
            "log_file" => "/mnt/c/Users/MeetYou/code/storage/logs/swoole_http.log",
            "daemonize" => false,
            "sandbox_mode" => false,
            "reactor_num" => 8,
            "worker_num" => 10,
            "task_worker_num" => 4,
            "package_max_length" => 20971520,
            "buffer_output_size" => 104857600,
            "socket_buffer_size" => 134217728,
            "max_request" => 3000,
            "send_yield" => true,
            "ssl_cert_file" => NULL,
            "ssl_key_file" => NULL,]);
    }

    public function start()
    {
        static::$server->start();
    }

}