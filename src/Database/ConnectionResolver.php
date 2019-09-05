<?php

namespace FastLaravel\Http\Database;

use FastLaravel\Http\Coroutine\Context;
use FastLaravel\Http\Database\Pool\DbPool;
use FastLaravel\Http\Database\Pool\Config\DbPoolConfig;
use FastLaravel\Http\Exceptions\DbException;

class ConnectionResolver
{
    /**
     * All of the registered connection configs.
     *
     * @var array
     */
    protected $connections = array();

    /**
     * All of the registered pool configs.
     *
     * @var array
     */
    protected $poolConfigs = array();

    /**
     * All of the registered connection Pools.
     *
     * @var array
     */
    protected $connectionPoolCache = array();

    /**
     * @var Context
     */
    protected $coContext = null;

    /**
     * The default connection name.
     *
     * @var string
     */
    protected $default;

    /**
     * Create a new connection resolver instance.
     *
     * @param  array $connections
     * @param  array $poolConfigs
     */
    public function __construct(array $connections = array(), array $poolConfigs=[], $defaultConnection='default')
    {
        $this->coContext = new Context();
        $this->poolConfigs = $poolConfigs;
        $this->setDefaultConnection($defaultConnection);
        foreach ($connections as $name => $connection) {
            $this->addConnection($name, $connection);
        }
    }

    /**
     * Get a database connection instance.
     *
     * @param  string $name
     * @return \FastLaravel\Http\Database\Connection
     */
    public function connection($name = null)
    {
        if (is_null($name))
            $name = $this->getDefaultConnection();

        if (!isset($this->connectionPoolCache[$name])) {
            $poolConfig = $this->poolConfigs[$name] ?? ($this->poolConfigs['default'] ?? null);
            $dbConfig = $this->connectionConfig($name);

            $dbPool = new DbPool();
            $dbPool->init((new DbPoolConfig($poolConfig, $dbConfig))->setNodeName($name));

            $this->connectionPoolCache[$name] = $dbPool;
        }

        return $this->getConnection($name);
    }

    /**
     * @param $name
     *
     * @return null
     */
    public function getConnection($name)
    {
        // 协程环境
        if (isCoContext()) {
            $key = "connection_{$name}";
            // 相同协程使用同一个连接
            if ($this->coContext->has($key)) {
                $connection = $this->coContext->get($key);
            } else {
                $connection = $this->connectionPoolCache[$name]->getConnection();
                // 协程结束后自动放回池中
                defer(function () use ($name, $connection){
                    $this->connectionPoolCache[$name]->release($connection);
                });
                $this->coContext->set('node', $name);
                $this->coContext->set($key, $connection);
            }
        } else {
            $connection = $this->connectionPoolCache[$name]->getConnection();
        }
        return $connection;
    }

    /**
     * release database connection
     */
    public function release()
    {
        $name = $this->coContext->get('node');
        $key = "connection_{$name}";
        $connection = $this->coContext->get($key);
        $this->connectionPoolCache[$name]->release($connection);
    }

    /**
     * Get a database connection instance.
     *
     * @param  string $name
     *
     * @throws DbException
     * @return array
     */
    public function connectionConfig($name = null)
    {
        if (is_null($name))
            $name = $this->getDefaultConnection();

        if (! isset($this->connections[$name])) {
            throw new DbException("database connection {$name} config not exists...");
        }
        return $this->value($this->connections[$name]);
    }

    /**
     * Add a connection to the resolver.
     *
     * Can be an instance of FastLaravel\Http\Database\Connection or a valid config array, if a connection factory has been set
     *
     * @param  string $name
     * @param  array $connection
     * @return void
     */
    public function addConnection($name, $connection)
    {
        $this->connections[$name] = $connection;
    }

    /**
     * Check if a connection has been registered.
     *
     * @param  string $name
     * @return bool
     */
    public function hasConnection($name)
    {
        return isset($this->connections[$name]);
    }

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnection()
    {
        return $this->default;
    }

    /**
     * Set the default connection name.
     *
     * @param  string $name
     * @return void
     */
    public function setDefaultConnection($name)
    {
        $this->default = $name;
    }

    /**
     * @param $value
     * @return mixed
     */
    protected function value($value)
    {
        return $value instanceof \Closure ? $value() : $value;
    }


    /**
     * Dynamically pass methods to the default connection.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->connection()->$method(...$parameters);
    }
}
