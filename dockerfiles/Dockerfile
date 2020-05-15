FROM toxmc/php:7.3.11

MAINTAINER mc <smalleyes@live.cn>

# dir
WORKDIR /data/www

ENV COMPOSER_ALLOW_SUPERUSER 1
# install laravel
RUN composer create-project --prefer-dist laravel/laravel:5.5  abtest -vvv

# 如果镜像过慢的情况可以注释上面语句，指定国内阿里云的源。
# RUN composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/ && \
# composer create-project --prefer-dist laravel/laravel:5.5  abtest -vvv

# install laravel-swoole
RUN cd abtest && composer require toxmc/fast-laravel -vvv

# install wrk and ab
RUN cd ~ && git clone https://github.com/wg/wrk.git wrk && \
cd wrk &&\
make && \
cp wrk /usr/local/bin

RUN dnf -y install httpd-tools

COPY app/swoole_http.php /data/www/abtest/config/swoole_http.php
COPY app/Kernel.php /data/www/abtest/app/Http/Kernel.php
COPY app/api.php /data/www/abtest/routes/api.php
COPY app/TestController.php /data/www/abtest/app/Http/Controllers/TestController.php

RUN cd abtest &&\
php artisan http publish && \
php artisan http config

EXPOSE 9100
CMD ["php","abtest/fast","http:start"]