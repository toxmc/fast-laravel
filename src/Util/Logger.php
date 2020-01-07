<?php

namespace FastLaravel\Http\Util;

use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Psr\Log\InvalidArgumentException;
use FastLaravel\Http\Facade\Show;

/**
 * Class Logger
 *
 * @package FastLaravel\Http\Util
 */
class Logger extends LogLevel implements LoggerInterface
{
    /**
     * @var
     */
    public $output;

    /**
     * Logging levels from syslog protocol defined in RFC 5424
     *
     * @var array $levels Logging levels
     */
    protected static $levels = array(
        self::DEBUG     => 'DEBUG',
        self::INFO      => 'INFO',
        self::NOTICE    => 'NOTICE',
        self::WARNING   => 'WARNING',
        self::ERROR     => 'ERROR',
        self::CRITICAL  => 'CRITICAL',
        self::ALERT     => 'ALERT',
        self::EMERGENCY => 'EMERGENCY',
    );

    public function __construct()
    {}

    public function error($message, array $context = array())
    {
        $this->log(self::ERROR, $message, $context);
    }

    public function warning($message, array $context = array())
    {
        $this->log(self::WARNING, $message, $context);
    }

    public function debug($message, array $context = array())
    {
        $this->log(self::DEBUG, $message, $context);
    }

    public function critical($message, array $context = array())
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    public function emergency($message, array $context = array())
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function notice($message, array $context = array())
    {
        $this->log(self::NOTICE, $message, $context);
    }

    public function info($message, array $context = array())
    {
        $this->log(self::INFO, $message, $context);
    }

    public function alert($message, array $context = array())
    {
        $this->log(self::ALERT, $message, $context);
    }

    public function log($level, $message, array $context = array())
    {
        if (!isset(self::$levels[$level]) || in_array($level, self::$levels)) {
            throw new InvalidArgumentException("logger level:{$level} not exists");
        }
        $message = sprintf('[%s] [%s] fast-laravel: %s.', date('Y-m-d H:i:s'), $level, $message);
        if ($context) {
            $message .= 'context=' . var_export($context, true);
        }
        Show::writeln($message);
    }

}