<?php

namespace FastLaravel\Http\Task;

class TaskInfo
{
    /**
     * 任务名称
     *
     * @var string
     */
    private $name = '';

    /**
     * @var bool
     */
    private $type = '';

    /**
     * @var bool
     */
    private $params = '';

    /**
     * @var bool
     */
    private $method = '';

    /**
     * TaskInfo constructor.
     * @param $name
     * @param $method
     * @param $params
     * @param $type
     */
    public function __construct($name, $method, $params, $type)
    {
        $this->name = $name;
        $this->method = $method;
        $this->params = $params;
        $this->type = $type;
    }

    /**
     * 获取任务名称
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function getType()
    {
        return $this->type;
    }
}