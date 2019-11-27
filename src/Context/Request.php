<?php

namespace FastLaravel\Http\Context;

use Swoole\Http\Request as SwooleRequest;
use Illuminate\Http\Request as IlluminateRequest;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Class Request
 * Translate Swoole\Http\Request to Illuminate\Http\Request
 *
 * @package FastLaravel\Http\Context
 */
class Request
{
    /**
     * @var IlluminateRequest
     */
    protected $illuminateRequest;

    /**
     * @var Request or null
     */
    protected static $instance = null;

    protected static $requestInfo = [];

    /**
     * @var SymfonyRequest or null
     */
    protected $symfonyRequest = null;

    /**
     * Make a request.
     *
     * @param SwooleRequest $swooleRequest
     *
     * @return Request
     */
    public static function make(SwooleRequest $swooleRequest)
    {
        list($get, $post, $cookie, $files, $server, $content) = self::toIlluminateParameters($swooleRequest);

        if (!self::$instance) {
            self::$instance = new static($get, $post, $cookie, $files, $server, $content);
        } else {
            self::$instance->createIlluminateRequest($get, $post, $cookie, $files, $server, $content);
        }
        return self::$instance;
    }


    /**
     * @param array $requestInfo
     */
    public static function setRequestInfo(array $requestInfo)
    {
        self::$requestInfo = $requestInfo;
    }

    /**
     * @return array
     */
    public static function getRequestInfo()
    {
        return self::$requestInfo;
    }

    /**
     * Request constructor.
     *
     * @param array $get
     * @param array $post
     * @param array $cookie
     * @param array $files
     * @param array $server
     * @param string $content
     * @throws \LogicException
     */
    public function __construct(array $get, array $post, array $cookie, array $files, array $server, $content = null)
    {
        $this->createIlluminateRequest($get, $post, $cookie, $files, $server, $content);
    }

    /**
     * Create Illuminate Request.
     *
     * @param array $get
     * @param array $post
     * @param array $cookie
     * @param array $files
     * @param array $server
     * @param string $content
     *
     * @return void
     */
    protected function createIlluminateRequest($get, $post, $cookie, $files, $server, $content = null)
    {
        IlluminateRequest::enableHttpMethodParameterOverride();
        $request = $this->getSymfonyRequest($get, $post, [], $cookie, $files, $server, $content);

        if (0 === strpos($request->headers->get('CONTENT_TYPE'), 'application/x-www-form-urlencoded')
            && in_array(strtoupper($request->server->get('REQUEST_METHOD', 'GET')), array('PUT', 'DELETE', 'PATCH'))
        ) {
            parse_str($request->getContent(), $data);
            $request->request = new ParameterBag($data);
        }

        $this->illuminateRequest = IlluminateRequest::createFromBase($request);
    }

    /**
     * @param $query
     * @param $request
     * @param $attributes
     * @param $cookies
     * @param $files
     * @param $server
     * @param $content
     * @return SymfonyRequest
     */
    public function getSymfonyRequest($query, $request, $attributes, $cookies, $files, $server, $content)
    {
        if (!$this->symfonyRequest) {
            $this->symfonyRequest = new SymfonyRequest($query, $request, $attributes, $cookies, $files, $server, $content);
        } else {
            $this->symfonyRequest->initialize($query, $request, $attributes, $cookies, $files, $server, $content);
        }
        return $this->symfonyRequest;
    }

    /**
     * @return IlluminateRequest
     */
    public function toIlluminate()
    {
        return $this->illuminateRequest;
    }

    /**
     * Transforms request parameters.
     *
     * @param SwooleRequest $request
     *
     * @return array
     */
    protected static function toIlluminateParameters(SwooleRequest $request)
    {
        $get = $request->get ?? [];
        $post = $request->post ?? [];
        $files = $request->files ?? [];
        $cookie = $request->cookie ?? [];
        $header = $request->header ?? [];
        $server = $request->server ?? [];
        $server = self::transformServerParameters($server, $header);
        $content = $request->rawContent();

        $requestInfo = [$get, $post, $cookie, $files, $server, $content];
        self::setRequestInfo($requestInfo);
        return $requestInfo;
    }

    /**
     * Transforms $_SERVER array.
     *
     * @param array $server
     * @param array $header
     * @return array
     */
    protected static function transformServerParameters(array $server, array $header)
    {
        $SERVER = [];

        foreach ($server as $key => $value) {
            $key = strtoupper($key);
            $SERVER[$key] = $value;
        }

        foreach ($header as $key => $value) {
            $key = str_replace('-', '_', $key);
            $key = strtoupper($key);

            if (! in_array($key, ['REMOTE_ADDR', 'SERVER_PORT', 'HTTPS'])) {
                $key = 'HTTP_' . $key;
            }

            $SERVER[$key] = $value;
        }

        return $SERVER;
    }
}
