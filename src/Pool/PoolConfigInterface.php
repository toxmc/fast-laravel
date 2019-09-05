<?php

namespace FastLaravel\Http\Pool;

/**
 * Interface PoolConfigInterface
 *
 * @package FastLaravel\Http\Pool
 */
interface PoolConfigInterface
{
    /**
     * @return array
     */
    public function toArray(): array;

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @return int
     */
    public function getMaxActive(): int;

    /**
     * @return int
     */
    public function getMaxWait(): int;

    /**
     * @return float
     */
    public function getTimeout(): float;

    /**
     * @return array
     */
    public function getUri(): array;

    /**
     * @return bool
     */
    public function isUseProvider(): bool;

    /**
     * @return string
     */
    public function getBalancer(): string;

    /**
     * @return string
     */
    public function getProvider(): string;

    /**
     * @return int
     */
    public function getMinActive(): int;

    /**
     * @return int
     */
    public function getMaxWaitTime(): int;

    /**
     * @return int
     */
    public function getMaxIdleTime(): int;
}
