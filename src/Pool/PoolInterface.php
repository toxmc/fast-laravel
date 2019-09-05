<?php

namespace FastLaravel\Http\Pool;

/**
 * Interface PoolInterface
 */
interface PoolInterface
{
    /**
     * @return ConnectionInterface
     */
    public function createConnection();

    /**
     * Get a connection
     *
     * @return ConnectionInterface
     */
    public function getConnection();

    /**
     * Relesea the connection
     *
     * @param ConnectionInterface $connection
     */
    public function release($connection);

    /**
     * @return PoolConfigInterface
     */
    public function getPoolConfig(): PoolConfigInterface;

    /**
     * @return int
     */
    public function getTimeout(): int;
}
