<?php

namespace FastLaravel\Http\Pool;

/**
 * Class AbstractConnect
 */
abstract class AbstractConnection implements ConnectionInterface
{
    /**
     * @var PoolInterface
     */
    protected $pool;

    /**
     * @var int
     */
    protected $lastTime;

    /**
     * @var string
     */
    protected $connectionId;

    /**
     * @var bool
     */
    protected $autoRelease = true;

    /**
     * Whether or not the package has been recv,default true
     *
     * @var bool
     */
    protected $recv = true;

    /**
     * AbstractConnection constructor.
     *
     * @param PoolInterface $connectPool
     */
    public function __construct(PoolInterface $connectPool)
    {
        $this->lastTime     = time();
        $this->connectionId = uniqid();
        $this->pool         = $connectPool;
        $this->createConnection();
    }

    /**
     * @return int
     */
    public function getLastTime(): int
    {
        return $this->lastTime;
    }

    /**
     * Update last time
     */
    public function updateLastTime()
    {
        $this->lastTime = time();
    }

    /**
     * @return string
     */
    public function getConnectionId(): string
    {
        return $this->connectionId;
    }

    /**
     * @return \FastLaravel\Http\Pool\PoolInterface
     */
    public function getPool(): \FastLaravel\Http\Pool\PoolInterface
    {
        return $this->pool;
    }

    /**
     * @return bool
     */
    public function isAutoRelease(): bool
    {
        return $this->autoRelease;
    }

    /**
     * @param bool $autoRelease
     */
    public function setAutoRelease(bool $autoRelease)
    {
        $this->autoRelease = $autoRelease;
    }

    /**
     * @param bool $release
     */
    public function release($release = false)
    {
        if ($this->isAutoRelease() || $release) {
            $this->pool->release($this);
        }
    }
}
