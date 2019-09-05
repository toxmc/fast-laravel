### 使用
在composer.json中添加包
```
    "require": {
        ...,
        "toxmc/fast-laravel":"dev-master"
    },
    ...
```

执行`composer install`
添加`Service Provider`到`config/app.php`中
```
[
    'providers' => [
        FastLaravel\Http\LaravelServiceProvider::class,
    ],
]
```

如果想修改默认配置执行如下命令，会在配置文件夹中生成配置文件
```
$ php artisan vendor:publish --tag=fast-laravel
or
$ php artisan http publish

```

命令：
```
php artisan http {action : publish|config|infos}.
```
* publish:发布配置信息
* config:生成fast命令所需的配置(原理复制一份fast启动所需的配置到json文件中主要为了解决config和route重载不生效的问题)
* infos:查看服务信息

```
php fast http::{action : start|stop|restart|reload|infos} {-d|--daemonize : Whether run as a daemon for start & restart}.
```
* start:启动服务
* stop:停止服务
* restart:重启服务
* reload:重载服务
* infos:查看服务信息

### nginx负载均衡灾备

```
upstream sw-backend{
	server 127.0.0.1:9100;
	server local-api.code.iizhu.com:80 backup; # 被份机，sw挂了转php-fpm处理
}

server {
        listen 80;
        server_name sw-code.iizhu.com;

	    access_log /usr/local/var/log/nginx/sw-access.log main;
        error_log /usr/local/var/log/nginx/sw-error.log;
        
        location / {
		root /Users/xmc/PhpstormProjects/iizhu/view/dist;
        	index index.html;
		try_files $uri $uri/ /index.html?$query_string;
	}
	location ~ ^/(uploads)/ {
        	root /Users/xmc/PhpstormProjects/iizhu/api/storage/app/;
        	expires -1;
        }	
	# 开头表示uri以某个常规字符串开头，理解为匹配 url路径即可。nginx不对url做编码，因此请求为/static/20%/aa，可以被规则^~ /static/ /aa匹配到（注意是空格）
	location ^~ /api/ {
		# 转发 把/api/xxx 重写成/api/xxx
		rewrite /api/(.+)$ /api/$1 break;

		proxy_set_header Host local-api.code.iizhu.com;
                proxy_pass http://sw-backend;
                proxy_set_header X-Real-IP $remote_addr;
                proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
                proxy_set_header REMOTE_ADD $remote_addr;
                proxy_set_header X-Is-EDU 0;
                proxy_set_header Via "nginx";
                proxy_set_header Accept-Encoding 'gzip';
        	
		proxy_connect_timeout      90;
        	proxy_send_timeout         90;
        	proxy_read_timeout         90;
        	proxy_buffer_size          4k;
        	proxy_buffers              4 32k;
        	proxy_busy_buffers_size    64k;
        	proxy_temp_file_write_size 64k;
        }
}

```

### supervisor管理服务

#### 安装
```
brew install supervisor
```

#### 运行
```
supervisord -c supervisor/supervisor.conf
```

#### 管理
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

### 本地开发实现热重载（Mac下）

#### 方案1
修改.env文件加入
```
SWOOLE_HOT_RELOAD=true
```
或通过修改配置文件swoole_http.php中的hot_reload选项
```
'hot_reload' => env('SWOOLE_HOT_RELOAD', true),
```


#### 方案2 安装fswatch
```
brew install fswatch
```

然后执行
```
[xmc@mc fast-laravel (master ✗)]$ sh fswatch.sh /Users/xmc/PhpstormProjects/iizhu/api
Starting fswatch...
File /Users/xmc/PhpstormProjects/iizhu/api/app/Service/TestService.php has been modified.
Reloading swoole_http_server...
> success
File /Users/xmc/PhpstormProjects/iizhu/api/app/Service/TestService.php has been modified.
Reloading swoole_http_server...
> success
```
> 通过fswatch监视文件变化，检测到文件变更后重启服务，便于本地开发，不用手动重启服务

#### 替代方案
* [laravel-swoole](https://github.com/swooletw/laravel-swoole)
#### 替代框架
* [Swoft](https://www.swoft.org/)
* [easyswoole](http://www.easyswoole.com/)
* [hyperf](http://www.hyperf.io/)
