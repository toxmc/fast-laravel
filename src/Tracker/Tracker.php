<?php

namespace FastLaravel\Http\Tracker;

use MongoDB\Client;
use Illuminate\Http\Request;
use Illuminate\Config\Repository;
use FastLaravel\Http\Facade\Show;
use FastLaravel\Http\Tracker\SaverInterface;
/**
 * Class Tracker
 *
 * @package FastLaravel\Http\Tracker
 */
class Tracker
{
    /**
     * @var Tracker or null
     */
    protected static $instance = null;

    /**
     * @var Repository
     */
    protected $config = null;

    /**
     * @var bool
     */
    protected $shouldRun = false;

    /**
     * @var bool
     */
    protected $enable = true;

    protected $tideways_xhprof = false;

    protected $tideways = false;

    protected $profilerFilterPath;

    /**
     * Make a Tracker.
     * @return Tracker
     */
    public static function make()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Tracker constructor.
     */
    private function __construct()
    {
        $this->enable = (bool) config('swoole_http.tracker.enable');
        $this->profilerFilterPath = config('swoole_http.tracker.profiler.filter_path');

        $this->tideways = extension_loaded('tideways');
        $this->tideways_xhprof = extension_loaded('tideways_xhprof');

        // this file should not - under no circumstances - interfere with any other application
        if ($this->enable && !$this->tideways && !$this->tideways_xhprof) {
            Show::error("please install tideways or tideways_xhprof extension.");
            $this->enable = false;
        }
        if ($this->enable && config('swoole_http.tracker.handler') == 'mongodb') {
            if (!extension_loaded('mongodb')) {
                Show::error("please install mongodb extension.");
                $this->enable = false;
            }
            if (!class_exists("MongoDB\Client")) {
                Show::error("please install composer mongodb/mongodb extension.");
                $this->enable = false;
            }
        }
    }

    /**
     * should run
     * @param Request $illuminateRequest
     * @return bool
     */
    public function shouldRun($illuminateRequest)
    {
        if (!$this->enable) {
            return false;
        }
        $callback = config('swoole_http.tracker.profiler.enable');
        if (!is_callable($callback)) {
            $ret =  (bool) $callback;
        } else {
            $ret = (bool) $callback($illuminateRequest);
        }

        // filter path
        $path = $illuminateRequest->getPathInfo();
        if (is_array($this->profilerFilterPath) && in_array($path, $this->profilerFilterPath)) {
            Show::error("filter path:{$path}");
            $ret = false;
        }
        return $this->shouldRun=$ret;
    }

    /**
     * collecter info
     * @param Request $illuminateRequest
     * @return void
     */
    public function collecter(Request $illuminateRequest)
    {
        if (!$this->shouldRun($illuminateRequest)) {
            return;
        }

        if ($this->tideways_xhprof) {
            tideways_xhprof_enable(TIDEWAYS_XHPROF_FLAGS_MEMORY | TIDEWAYS_XHPROF_FLAGS_MEMORY_MU | TIDEWAYS_XHPROF_FLAGS_MEMORY_PMU | TIDEWAYS_XHPROF_FLAGS_CPU);
        } elseif ($this->tideways) {
            tideways_enable(TIDEWAYS_FLAGS_CPU | TIDEWAYS_FLAGS_MEMORY);
            tideways_span_create('sql');
        }
        return;
    }

    /**
     * 上报
     * @param Request $illuminateRequest
     * @return void
     */
    public function report(Request $illuminateRequest)
    {
        if (!$this->shouldRun) {
            return;
        }
        $data = [];
        if ($this->tideways_xhprof) {
            // tideways_xhprof 5.x 暂不支持sql，tideways公司未开源相关代码
            $data['profile'] = tideways_xhprof_disable();
        } elseif ($this->tideways) {
            $data['profile'] = tideways_disable();
            $sqlData = tideways_get_spans();
            $data['sql'] = [];
            foreach ($sqlData as $val) {
                if (isset($val['n']) && $val['n'] === 'sql' && isset($val['a']) && isset($val['a']['sql'])) {
                    if (empty($val['a']['sql'])) {
                        continue;
                    }
                    $data['sql'][] = [
                        'time' => (isset($val['b'][0]) && isset($val['e'][0])) ? ($val['e'][0] - $val['b'][0]) : 0,
                        'sql'  => $val['a']['sql']
                    ];
                }
            }
        } else {
            return;
        }

        $uri = $illuminateRequest->getRequestUri();
        $time = $illuminateRequest->server('REQUEST_TIME');
        $requestTimeFloat = $illuminateRequest->server('REQUEST_TIME_FLOAT');
        if (config('swoole_http.tracker.handler') === 'file') {
            $requestTs = $time;
            $requestTsMicro = $requestTimeFloat;
        } else {
            //2019-12-26 09:23:58
            //2019-12-26 09:23:58.009Z
            $requestTs = new \MongoDB\BSON\UTCDateTime($time * 1000);
            $requestTsMicro = new \MongoDB\BSON\UTCDateTime($requestTimeFloat * 1000);
        }

        $data['meta'] = array(
            'url'              => urldecode($uri),
            'SERVER'           => $illuminateRequest->server(),
            'get'              => $illuminateRequest->all(),
            'env'              => getenv(),
            'simple_url'       => urldecode($this->simpleUrl($uri)),
            'request_ts'       => $requestTs,
            'request_ts_micro' => $requestTsMicro,
            'request_date'     => date('Y-m-d', $time),
        );

        try {
            $saver = $this->saver($uri);
            $saver->save($data);
        } catch (\Exception $e) {
            Show::error($e->getMessage());
        }
    }

    public function simpleUrl($url)
    {
        $callable = config('swoole_http.tracker.profiler.simple_url');
        if (is_callable($callable)) {
            return call_user_func($callable, $url);
        }
        return preg_replace('/\=\d+/', '', $url);
    }

    /**
     * Get a saver instance based on configuration data.
     *
     * @param string $uri The configuration data.
     * @throws \Exception
     * @return SaverInterface
     */
    public function saver($uri)
    {
        $handler = config('swoole_http.tracker.handler');
        $saverClass = __NAMESPACE__."\\Saver\\".ucfirst($handler);
        switch ($handler) {
            case 'file':
                $filename = config('swoole_http.tracker.filename');
                if (is_callable($filename)) {
                    $filename = call_user_func($filename, $uri);
                }
                return new $saverClass($filename);
            case 'mongodb':
                $config = config('swoole_http.tracker.db');
                $mongo = new Client($config['host'], $config['options']);
                return new $saverClass($mongo->{$config['db']});
            default:
                throw new \Exception("only support file or mongodb saver. not {$handler}.");
        }
    }

}
