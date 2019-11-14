```
  ___                   _                             _ 
 / __)          _      | |                           | |
| |__ ____  ___| |_    | | ____  ____ ____ _   _ ____| |
|  __) _  |/___)  _)   | |/ _  |/ ___) _  | | | / _  ) |
| | ( ( | |___ | |__   | ( ( | | |  ( ( | |\ V ( (/ /| |
|_|  \_||_(___/ \___)  |_|\_||_|_|   \_||_| \_/ \____)_|                                             

```
> 🚀[fast-laravel](https://packagist.org/packages/toxmc/fast-laravel). is a package that made you `laravel` application fast.

#### install 
first you mush install [composer](https://getcomposer.org/)

add require info into composer.json and execute `composer install`
```
"require": {
    "toxmc/fast-laravel":"^1.0"
},
```
or
```bash
composer require "toxmc/fast-laravel" -vvv
```

add `Service Provider` into `config/app.php`
```
[
    'providers' => [
        FastLaravel\Http\LaravelServiceProvider::class,
    ],
]
```

Publish configuration and binaries.
```
$ php artisan vendor:publish --tag=fast-laravel
or
$ php artisan http publish

```
config configuration
```
$ php artisan http config
```
start server
```
$ php fast http:start
```

#### command：
```
php artisan http {action : publish|config|infos}.
```
* publish:Publish configuration and binaries.
* config:Generate command(fast) configuration information
* infos:show information

```
php fast http::{action : start|stop|restart|reload|infos} {-d|--daemonize : Whether run as a daemon for start & restart}.
```
* start:start server
* stop:stop server
* restart:restart server
* reload:reload server
* infos:show information

#### supervisor manage services

install
```
brew install supervisor
```

start
```
supervisord -c supervisor/supervisor.conf
```

manage
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

#### docker
Windows Docker Desktop with WSL1's docker client. 

Docker Fedora 31, CPUs:4, Memory:4G.
```
docker build -t=fast-laravel dockerfiles
docker run --rm -p 9100:9100 -d fast-laravel:latest
docker exec -it `docker ps -q` /bin/bash

[root@55c599663c7b www]# wrk -c32 -t16 -d 30 http://127.0.0.1:9100/api/test/info
Running 30s test @ http://127.0.0.1:9100/api/test/info
  16 threads and 32 connections
  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency    12.04ms   34.15ms 540.61ms   97.88%
    Req/Sec   270.91     79.32     0.93k    74.33%
  129701 requests in 30.10s, 31.54MB read
Requests/sec:   4309.00
Transfer/sec:      1.05MB
```

#### hot reload

1：edit `.env` and restart server
```
SWOOLE_HOT_RELOAD=true
```
or edit `swoole_http.php`
```
'hot_reload' => env('SWOOLE_HOT_RELOAD', true),
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