<?php

namespace FastLaravel\Http\Context;

class Debug
{
    /**
     * @var array
     */
    protected $debugStack = [];

    /**
     * add info to debug stack
     *
     * @param mixed $message
     */
    public function add($message)
    {
        if ($message) {
            $this->debugStack[] = $message;
        }
    }

    /**
     * get all debug info
     *
     * @return array
     */
    public function getAll()
    {
        return $this->debugStack;
    }

    /**
     * rest
     */
    public function reset()
    {
        $this->debugStack = [];
    }
}
