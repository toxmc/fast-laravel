<?php

namespace FastLaravel\Http\Pool;

use Swoole\Coroutine\Channel;
use FastLaravel\Http\Exceptions\ConnectionException;
use FastLaravel\Http\Exceptions\PoolException;

/**
 * Class ConnectPool
 */
abstract class ConnectionPool implements PoolInterface
{
    /**
     * Current connection count
     *
     * @var int
     */
    protected $currentCount = 0;

    /**
     * Pool config
     *
     * @var PoolConfigInterface
     */
    protected $poolConfig;

    /**
     * @var Channel
     */
    protected $channel;

    /**
     * @var \SplQueue
     */
    protected $queue;

    /**
     * Initialization
     *
     * @param PoolConfigInterface $poolConfig
     *
     * @throws PoolException
     */
    public function init(PoolConfigInterface $poolConfig=null)
    {
        if (empty($poolConfig)) {
            throw new PoolException('You must to set poolConfig not empty!');
        }
        $this->poolConfig = $poolConfig;

        if (isWorkerStatus()) {
            $this->channel = new Channel($this->poolConfig->getMaxActive());
        } else {
            $this->queue = new \SplQueue();
        }
    }

    /**
     * Get connection
     *
     * @throws ConnectionException;
     * @return ConnectionInterface
     */
    public function getConnection()
    {
        if (isCoContext()) {
            $connection = $this->getConnectionByChannel();
        } else {
            $connection = $this->getConnectionByQueue();
        }

        $this->addContextConnection($connection);
        return $connection;
    }

    /**
     * Release connection
     *
     * @param ConnectionInterface $connection
     */
    public function release($connection)
    {
        $connectionId = $connection->getConnectionId();
        $connection->updateLastTime();
//        $connection->setAutoRelease(true);

        if (isCoContext()) {
            $this->releaseToChannel($connection);
        } else {
            $this->releaseToQueue($connection);
        }

        $this->removeContextConnection($connectionId);
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->poolConfig->getTimeout();
    }

    /**
     * @return PoolConfigInterface
     */
    public function getPoolConfig(): PoolConfigInterface
    {
        return $this->poolConfig;
    }

    /**
     * Release to queue
     *
     * @param $connection
     */
    private function releaseToQueue(ConnectionInterface $connection)
    {
        if ($this->queue->count() < $this->poolConfig->getMaxActive()) {
            $this->queue->push($connection);
        }
    }

    /**
     * Release to channel
     *
     * @param $connection
     */
    private function releaseToChannel($connection)
    {
        $stats     = $this->channel->stats();
        $maxActive = $this->poolConfig->getMaxActive();
        if ($stats['queue_num'] < $maxActive) {
            $this->channel->push($connection);
        }
    }

    /**
     * Get connection by queue
     *
     * @return ConnectionInterface
     * @throws ConnectionException
     */
    private function getConnectionByQueue(): ConnectionInterface
    {
        if($this->queue == null){
            $this->queue = new \SplQueue();
        }
        if (!$this->queue->isEmpty()) {
            return $this->getEffectiveConnection($this->queue->count(), false);
        }

        if ($this->currentCount >= $this->poolConfig->getMaxActive()) {
            throw new ConnectionException('Connection pool queue is full');
        }

        $connect = $this->createConnection();
        $this->currentCount++;

        return $connect;
    }

    /***
     * Get connection by channel
     *
     * @return ConnectionInterface
     * @throws ConnectionException
     */
    private function getConnectionByChannel()
    {
        if($this->channel === null){
            $this->channel = new Channel($this->poolConfig->getMaxActive());
        }

        $stats = $this->channel->stats();
        if ($stats['queue_num'] > 0) {
            return $this->getEffectiveConnection($stats['queue_num']);
        }

        $maxActive = $this->poolConfig->getMaxActive();
        if ($this->currentCount < $maxActive) {
            $connection = $this->createConnection();
            $this->currentCount++;

            return $connection;
        }

        $maxWait = $this->poolConfig->getMaxWait();
        if ($maxWait != 0 && $stats['consumer_num'] >= $maxWait) {
            throw new ConnectionException(sprintf('Connection pool waiting queue is full, maxActive=%d,maxWait=%d,currentCount=%d', $maxActive, $maxWait, $this->currentCount));
        }

        $maxWaitTime = $this->poolConfig->getMaxWaitTime();
        if ($maxWaitTime == 0) {
            return $this->channel->pop();
        }

        // When swoole version is larger than 4.0.3, Channel->select is removed.
        if (version_compare(swoole_version(), '4.0.3', '>=')) {
            $result = $this->channel->pop($maxWaitTime);
            if ($result === false) {
                throw new ConnectionException('Connection pool waiting queue timeout, timeout=' . $maxWaitTime);
            }
            return $result;
        }

        $writes = [];
        $reads  = [$this->channel];
        $result = $this->channel->select($reads, $writes, $maxWaitTime);

        if ($result === false || empty($reads)) {
            throw new ConnectionException('Connection pool waiting queue timeout, timeout='.$maxWaitTime);
        }

        $readChannel = $reads[0];

        return $readChannel->pop();
    }

    /**
     * Get effective connection
     *
     * @param int  $queueNum
     * @param bool $isChannel
     *
     * @return ConnectionInterface
     */
    private function getEffectiveConnection(int $queueNum, bool $isChannel = true)
    {
        $minActive = $this->poolConfig->getMinActive();
        if ($queueNum <= $minActive) {
            return $this->getOriginalConnection($isChannel);
        }

        $time        = time();
        $moreActive  = $queueNum - $minActive;
        $maxWaitTime = $this->poolConfig->getMaxWaitTime();
        for ($i = 0; $i < $moreActive; $i++) {
            /* @var ConnectionInterface $connection */
            $connection = $this->getOriginalConnection($isChannel);;
            $lastTime = $connection->getLastTime();
            if ($time - $lastTime < $maxWaitTime) {
                return $connection;
            }
            $this->currentCount--;
        }

        return $this->getOriginalConnection($isChannel);
    }

    /**
     * Get original connection
     *
     * @param bool $isChannel
     *
     * @return ConnectionInterface
     */
    private function getOriginalConnection(bool $isChannel = true)
    {
        if ($isChannel) {
            return $this->channel->pop();
        }

        return $this->queue->shift();
    }

    /**
     * @param \FastLaravel\Http\Pool\ConnectionInterface $connection
     */
    private function addContextConnection($connection)
    {
        $connectionId  = $connection->getConnectionId();
        $connectionKey = $this->getContextCntKey();
        RequestContext::setContextDataByChildKey($connectionKey, $connectionId, $connection);
    }

    /**
     * @param string $connectionId
     */
    private function removeContextConnection(string $connectionId)
    {
        $connectionKey = $this->getContextCntKey();
        RequestContext::removeContextDataByChildKey($connectionKey, $connectionId);
    }

    /**
     * @return string
     */
    private function getContextCntKey()
    {
        return sprintf('connections');
    }
}
