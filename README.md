中文 | [English](./README_EN.md)
## 简介
```
  ___                   _                             _ 
 / __)          _      | |                           | |
| |__ ____  ___| |_    | | ____  ____ ____ _   _ ____| |
|  __) _  |/___)  _)   | |/ _  |/ ___) _  | | | / _  ) |
| | ( ( | |___ | |__   | ( ( | | |  ( ( | |\ V ( (/ /| |
|_|  \_||_(___/ \___)  |_|\_||_|_|   \_||_| \_/ \____)_|                                             

```
> 🚀[fast-laravel](https://packagist.org/packages/toxmc/fast-laravel). 
>是基于 [Swoole](https://https://github.com/swoole/swoole-src) 实现的高性能、常驻内存的 `laravel`  框架 `composer` 扩展包，
>性能较传统基于 `PHP-FPM` 的服务有质的提升，提供超高性能的同时，也保持着 `Laravel` 
>框架的优点。基本上无需变更业务代码的前提下即可使原有项目蜕变为常驻内存服务从而提升响应速度。
>扩展提供了 `热加载`、`异步任务`、`Crontab 秒级定时任务`、`性能分析工具`、`自定义进程` 等非常便捷的功能，满足丰富的技术场景和业务场景，开箱即用。


## 扩展初衷

`Laravel` 是优雅的 `PHP Web` 开发框架。具有高效、简洁、富于表达力等优点。采用 `MVC` 设计，
是崇尚开发效率的全栈框架。是最受关注的 `PHP` 框架。然而 `Laravel` 最为人诟病的缺点就是：<b>慢、笨重</b>。
如何改变这种情况？ 实际项目中也遇到接口响应速度不理想，加机器、加缓存、`OPcache`、升级`PHP7`都做了，
还是不理想，还能进一步吗？考虑到各种成本。换 `go` 语言还是换基于 `swoole` 的协程框架。
考虑到业务量，重写业务成本过高。于是 `fast-laravel`诞生了。

## 服务器要求

`Fast-Laravel` 基于 `swoole` 的所以对系统环境有一些要求，仅可运行于 `Linux` 和 `Mac` 环境下，
但由于 `Docker` 虚拟化技术的发展，在 `Windows` 下也可以通过 `Docker for Windows` 来作为运行环境。
以及 `Windows` 下子系统的发展 `WSL` 也是不错的选择，推荐 `Ubuntu`。   

## Docker
[dockerfiles](https://github.com/toxmc/fast-laravel/tree/master/dockerfiles) 项目内已经为您准备好了。可直接构建运行。

Windows Docker Desktop with WSL1's docker client.
Docker Fedora 31, CPUs:4, Memory:4G.
```
docker build -t=fast-laravel dockerfiles
docker run --rm -p 9100:9100 -d fast-laravel:latest
docker exec -it `docker ps -q` /bin/bash

[root@a26c3596e1b8 www]# wrk -c32 -t8 -d 30s http://127.0.0.1:9100/api/test/info
Running 30s test @ http://127.0.0.1:9100/api/test/info
  8 threads and 32 connections
  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency     6.89ms   14.42ms 318.44ms   94.69%
    Req/Sec     0.97k   233.68     2.33k    74.63%
  232532 requests in 30.09s, 52.34MB read
Requests/sec:   7727.54
Transfer/sec:      1.74MB
```


当您不想采用 Docker 来作为运行的环境基础时，您需要确保您的运行环境达到了以下的要求：   

 - PHP >= 7.1
 - Swoole PHP 扩展 >= 4.0
 - JSON PHP 扩展
 - MongoDB PHP 扩展 （性能分析tracker使用）
 - tideways_xhprof 或 tideways PHP 扩展 （性能分析tracker使用）

## 安装 Fast-Laravel

Fast-Laravel 使用 [Composer](https://getcomposer.org) 来管理项目的依赖，在使用 Fast-Laravel 之前，
请确保你的运行环境已经安装好了 Composer。通过 `Composer` 安装。

1. 安装 `fast-laravel`
    ```
    composer require toxmc/fast-laravel -vvv
    ```
2. 发布配置信息
    ```
    php artisan vendor:publish --tag=fast-laravel
    ```
    或者
    ```
    php artisan http publish
    ```
3. 生成 `fast` 命令所需的配置（原理复制一份 `fast-laravel`  启动所需的配置到 `/storage/fast_laravel.json`  文件中主要为了解决 `config` 和 `route` `reload` 重启不生效的问题）
    ```
    php artisan http config
    ```
    
这样 `fast-laravel` 扩展就安装完成了。

## 命令
```
php artisan http {action : publish|config|infos}.
```

|action | 参数意义    | 命令      |
|-------|-------------|-----------|
|publish  |发布配置信息     |php artisan http publish
|config   |生成fast命令所需的配置</br>原理：复制一份fast启动所需的配置到json文件中.<br>主要为了解决config和route重载不生效的问题。|php artisan http config
|infos  |查看服务信息 |php artisan http infos


```
php fast http:{action : start|stop|restart|reload|infos} 
{-d|--daemon: Whether run as a daemon for start & restart}.
{-a|--access_log: It's will display access log on every request.}.
```

|action | 参数意义    | 命令      |
|-------|-------------|-----------|
|start  |启动服务     |php fast http:start
|stop   |停止服务     |php fast http:stop
|restart|重启服务     |php fast http:restart
|reload |重载服务     |php fast http:reload
|infos  |查看服务信息 |php fast http:infos


## supervisor 管理服务

例举MAC下安装
```
brew install supervisor
```

启动
```
supervisord -c supervisor/supervisor.conf
```

管理
```
[xmc@mc fast-laravel (master ✗)]$ supervisorctl -c supervisor/supervisor.conf
fast-laravel-monitor             RUNNING   pid 18131, uptime 0:03:11

supervisor> help

default commands (type help <topic>):
=====================================
add    exit      open  reload  restart   start   tail   
avail  fg        pid   remove  shutdown  status  update 
clear  maintail  quit  reread  signal    stop    version

supervisor> status
fast-laravel-monitor             RUNNING   pid 29146, uptime 3:03:36
```

## 热重载
> 建议生产环境关闭该服务。仅限开发时使用。

* `hot_reload`： 是否开启热重载
* `hot_reload_type`： 热重载类型，支持 `inotify` 和 `tick`
    * `inotify` 依赖 `inotify PHP` 扩展
    * `tick` 是利用 `swoole` 定时器，定时检测文件是否变化，进而进行 `reload` 操作
* `hot_reload_paths`： 监控的文件变更目录。只要属于目录内的文件，发生变动，就会进行 `reload`

```
    'server'    => [
        'hot_reload'          => env('SWOOLE_HOT_RELOAD', false),
        'hot_reload_type'     => env('SWOOLE_HOT_RELOAD_TYPE', ''),// inotify or tick
        'hot_reload_paths'    => [
            base_path('app'),
            base_path('config'),
        ],
    ]
```


2：fswatch
```
brew install fswatch

[xmc@mc fast-laravel (master ✗)]$ sh fswatch.sh /Users/xmc/PhpstormProjects/iizhu/api
Starting fswatch...
File /Users/xmc/PhpstormProjects/iizhu/api/app/Service/TestService.php has been modified.
Reloading swoole_http_server...
> success
File /Users/xmc/PhpstormProjects/iizhu/api/app/Service/TestService.php has been modified.
Reloading swoole_http_server...
> success
```

#### Alternative
* [laravel-swoole](https://github.com/swooletw/laravel-swoole)
#### Alternative Framework
* [Swoft](https://www.swoft.org/)
* [easyswoole](http://www.easyswoole.com/)
* [hyperf](http://www.hyperf.io/)

#### Others
* Q群:190349019