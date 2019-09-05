<?php

namespace FastLaravel\Http\Coroutine;

use Swoole\Coroutine\Channel;

class WaitGroup
{
    /**
     * @var int
     */
    private $count = 0;

    /**
     * @var channel
     */
    private $chan;

    /**
     * waitgroup constructor.
     * @desc 初始化一个channel
     */
    public function __construct()
    {
        $this->chan = new Channel();
    }

    /**
     * @desc 计数+1
     * @调用时机：在起一个协程前
     */
    public function add()
    {
        $this->count++;
    }

    /**
     * @param $data
     * @desc 协程处理完成时调用,把数据存入channel
     */
    public function done($data)
    {
        $this->chan->push($data);
    }

    /**
     * @desc 堵塞的等待所有的协程处理完成并返回结果
     */
    public function wait()
    {
        $result = [];
        for ($i = 0; $i < $this->count; $i++) {
            //调用pop方法时，如果没有数据，此协程会挂起
            //当往chan中push数据后，协程会被恢复
            $result[] = $this->chan->pop();
        }

        return $result;
    }

}