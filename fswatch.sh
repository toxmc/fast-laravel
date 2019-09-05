#!/usr/bin/env bash

DIR=$1
if [ ! -n "$DIR" ] ;then
    echo "you have not choice Application directory !"
    exit
fi

cd $DIR

pid=$(cat "$DIR/storage/logs/swoole_http.pid");
if [ $? -ne 0 ]; then
    # 请开启守护进程模式，否则会阻塞在start阶段导致fswatch失效
    php fast http:stop
    php fast http:start -d
fi

echo "Starting fswatch..."
LOCKING=0
fswatch -e ".*" -i "\\.php$" ${DIR} | while read file
do
    if [ ${LOCKING} -eq 1 ] ;then
        echo "Reloading, skipped."
        continue
    fi
    echo $(date "+%Y-%m-%d %H:%M:%S")": File ${file} has been modified."
    LOCKING=1
    php fast http:reload
    LOCKING=0
done
exit 0