<?php
namespace FastLaravel\Http\Database\Pool\Config;

use FastLaravel\Http\Pool\PoolProperties;

/**
 * The pool properties of database
 *
 */
class DbPoolProperties extends PoolProperties
{
    /**
     * The default of driver
     *
     * @var string
     */
    protected $driver = 'mysql';

    /**
     * 开启严格模式，返回的字段将自动转为数字类型
     *
     * @var bool
     */
    protected $strictType = false;

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function isStrictType(): bool
    {
        return $this->strictType;
    }
}
