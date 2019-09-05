<?php
namespace FastLaravel\Http\Database\Pool\Config;

/**
 * Master pool
 * thanks swoft
 */
class DbPoolConfig extends DbPoolProperties
{
    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var int
     */
    protected $minActive = 5;

    /**
     * @var int
     */
    protected $maxActive = 10;

    /**
     * @var int
     */
    protected $maxWait = 20;

    /**
     * @var int
     */
    protected $maxIdleTime = 60;

    /**
     * @var int
     */
    protected $maxWaitTime = 3;

    /**
     * @var int
     */
    protected $timeout = 3;

    /**
     * @var string
     */
    protected $driver = 'mysql';

    /**
     * @var bool
     */
    protected $strictType = false;

    /**
     * @var array
     */
    protected $dbConfig = [];

    /**
     * @var string
     */
    protected $nodeName;

    /**
     * DbPoolConfig constructor.
     *
     * @param $poolConfig
     * @param $dbConfig
     */
    public function __construct($poolConfig, $dbConfig)
    {
        foreach ($poolConfig as $key => $val) {
            $this->$key = $val;
        }
        $this->dbConfig = $dbConfig;
    }

    /**
     * @return array
     */
    public function getDbConfig()
    {
        return $this->dbConfig;
    }

    /**
     * @return self
     */
    public function setNodeName($nodeName)
    {
        $this->nodeName = $nodeName;
        return $this;
    }

    /**
     * @return string
     */
    public function getNodeName()
    {
        return $this->nodeName;
    }
}
