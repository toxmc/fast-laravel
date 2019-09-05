<?php

namespace FastLaravel\Http\Pool;

/**
 * Interface ConnectInterface
 *
 * @package FastLaravel\Http\Pool
 */
interface ConnectionInterface
{
    /**
     * Create connection
     *
     * @return void
     */
    public function createConnection();

    /**
     * Reconnect
     */
    public function reconnect();

    /**
     * @return int
     */
    public function getLastTime(): int;

    /**
     * @return void
     */
    public function updateLastTime();

    /**
     * @return string
     */
    public function getConnectionId(): string;

    /**
     * @return \FastLaravel\Http\Pool\PoolInterface
     */
    public function getPool(): \FastLaravel\Http\Pool\PoolInterface;

    /**
     * @return bool
     */
    public function isAutoRelease(): bool;

    /**
     * @param bool $autoRelease
     */
    public function setAutoRelease(bool $autoRelease);

    /**
     * @return void
     */
    public function release($release = false);

}
