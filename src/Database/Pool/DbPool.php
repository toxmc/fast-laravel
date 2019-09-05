<?php

namespace FastLaravel\Http\Database\Pool;

use FastLaravel\Http\Pool\ConnectionPool;
use FastLaravel\Http\Database\ConnectionInterface;
use FastLaravel\Http\Database\Pool\Config\DbPoolConfig;
use FastLaravel\Http\Database\Connectors\ConnectionFactory;
use FastLaravel\Http\Database\Connectors\ConnectionFactoryInterface;

/**
 * Db pool
 * thanks swoft
 */
class DbPool extends ConnectionPool
{
    /**
     * The config of dbPool
     *
     * @var DbPoolConfig
     */
    protected $poolConfig;

    /**
     * @var ConnectionFactoryInterface ConnectionFactory
     */
    protected $connectionFactory;

    /**
     * Create connection
     *
     * @return ConnectionInterface
     */
    public function createConnection() :ConnectionInterface
    {
        $this->connectionFactory = $this->connectionFactory ?: new ConnectionFactory();
        $connection = $this->connectionFactory->make($this->poolConfig->getDbConfig());
        $connection->setNodeName($this->poolConfig->getNodeName());
        return $connection;
    }

}
