<?php

namespace FastLaravel\Http\Coroutine;

use Swoole\Coroutine as SwooleCo;

/**
 * Class Context
 *
 * @package FastLaravel\Http\Coroutine
 */
class Context
{
    /**
     * @var array
     */
    private $context = [];

    /**
     * @param string $key
     * @param mixed  $value
     * @param null $cid
     *
     * @return Context
     */
    public function set($key, $value, $cid = null): Context
    {
        $cid = $this->getCid($cid);
        $this->context[$cid][$key] = $value;
        return $this;
    }

    /**
     * @param string $key
     * @param null $cid
     *
     * @return null
     */
    public function get($key, $cid = null)
    {
        $cid = $this->getCid($cid);
        if (isset($this->context[$cid][$key])) {
            return $this->context[$cid][$key];
        }
        return null;
    }

    /**
     * check key exists
     *
     * @param string $key
     * @param null $cid
     *
     * @return boolean
     */
    public function has($key, $cid = null)
    {
        $cid = $this->getCid($cid);
        if (isset($this->context[$cid][$key])) {
            return true;
        }
        return false;
    }

    /**
     * @param string $key
     * @param null $cid
     *
     * @return bool
     */
    public function delete($key, $cid = null)
    {
        $cid = $this->getCid($cid);
        if (isset($this->context[$cid][$key])) {
            unset($this->context[$cid][$key]);
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param null $cid
     */
    public function destroy($cid = null)
    {
        $cid = $this->getCid($cid);
        if (isset($this->context[$cid])) {
            $data = $this->context[$cid];
            foreach ($data as $key => $val) {
                $this->delete($key, $cid);
            }
        }
        unset($this->context[$cid]);
    }

    /**
     * @param null $cid
     *
     * @return int
     */
    public function getCid($cid = null): int
    {
        if ($cid === null) {
            $cid = SwooleCo::getCid();
            return $cid == -1 ? 0 : $cid;
        }
        return $cid;
    }

    /**
     * @param bool $force
     */
    public function destroyAll($force = false)
    {
        if ($force) {
            $this->context = [];
        } else {
            foreach ($this->context as $cid => $data) {
                $this->destroy($cid);
            }
        }
    }

    /**
     * @param null $cid
     *
     * @return array|null
     */
    public function getContext($cid = null):?array
    {
        $cid = $this->getCid($cid);
        if (isset($this->context[$cid])) {
            return $this->context[$cid];
        } else {
            return null;
        }
    }
}