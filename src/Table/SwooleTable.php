<?php

namespace FastLaravel\Http\Table;

use Swoole\Table;

/**
 * table 集合
 *
 * Class SwooleTable
 *
 * @package FastLaravel\Http\Table
 */
class SwooleTable
{
    /**
     * Registered swoole tables.
     *
     * @var array
     */
    protected $tables = [];

    /**
     * Add a swoole table to existing tables.
     *
     * @param string        $name
     * @param \Swoole\Table $table
     *
     * @return \FastLaravel\Http\Table\SwooleTable
     */
    public function add(string $name, Table $table)
    {
        // 避免覆盖
        if (!isset($this->tables[$name])) {
            $this->tables[$name] = $table;
        }

        return $this;
    }

    /**
     * delete a swoole table from existing tables.
     *
     * @param string $name
     *
     * @return true
     */
    public function del(string $name)
    {
        if (isset($this->tables[$name])) {
            unset($this->tables[$name]);
        }
        return true;
    }

    /**
     * count swoole table from existing tables.
     *
     * @param string $name
     *
     * @return int
     */
    public function count()
    {
        return count($this->tables);
    }

    /**
     * Get a swoole table by its name from existing tables.
     *
     * @param string $name
     *
     * @return \Swoole\Table $table
     */
    public function get(string $name)
    {
        return $this->tables[$name] ?? null;
    }

    /**
     * Get all existing swoole tables.
     *
     * @return array
     */
    public function getAll()
    {
        return $this->tables;
    }

    /**
     * Dynamically access table.
     *
     * @param  string $key
     *
     * @return table
     */
    public function __get($key)
    {
        return $this->get($key);
    }
}
