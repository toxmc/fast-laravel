<?php

namespace FastLaravel\Http\Context;

use Swoole\Http\Request as SwooleRequest;
use Illuminate\Http\Request as IlluminateRequest;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use FastLaravel\Http\Task\Helper\TaskHelper;
use FastLaravel\Http\Task\TaskInfo;

/**
 * Class TaskRequest
 * Translate Swoole\Http\Request to Illuminate\Http\Request
 *
 * @package FastLaravel\Http\Context
 */
class TaskRequest
{
    /**
     * @var IlluminateRequest
     */
    protected $illuminateRequest;

    protected $taskInfo = null;

    protected $isComplexTask = false;

    /**
     * Make a request.
     *
     * @param string $taskRequest
     *
     * @return TaskRequest
     */
    public static function make(string $taskRequest)
    {
        $data = TaskHelper::unpack($taskRequest);
        if ($data['request_info']) {
            list($get, $post, $cookie, $files, $server, $content) = $data['request_info'];
            unset($data['request_info']);
            $taskRequest = new static($get, $post, $cookie, $files, $server, $content, $data);
            $taskRequest->isComplexTask = true;
        } else {
            $taskRequest = new static([], [], [], [], [], [], $data);
            $taskRequest->isComplexTask = false;
        }
        return $taskRequest;
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
     * @param array $data
     * @throws \LogicException
     */
    public function __construct(array $get, array $post, array $cookie, array $files, array $server, $content = null, $data)
    {
        $this->createIlluminateRequest($get, $post, $cookie, $files, $server, $content);
        $this->createTaskInfo(ucfirst($data['name']), $data['method'], $data['params'], $data['type']);
    }

    /**
     * @param $name
     * @param $type
     * @param $method
     * @param $params
     */
    public function createTaskInfo($name, $method, $params, $type)
    {
        $this->taskInfo = new TaskInfo($name, $method, $params, $type);
    }

    /**
     * @return TaskInfo
     */
    public function getTaskInfo()
    {
        return $this->taskInfo;
    }

    /**
     * @return bool
     */
    public function isComplexTask()
    {
        return $this->isComplexTask;
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

        $request = new SymfonyRequest($get, $post, [], $cookie, $files, $server, $content);

        if (0 === strpos($request->headers->get('CONTENT_TYPE'), 'application/x-www-form-urlencoded')
            && in_array(strtoupper($request->server->get('REQUEST_METHOD', 'GET')), array('PUT', 'DELETE', 'PATCH'))
        ) {
            parse_str($request->getContent(), $data);
            $request->request = new ParameterBag($data);
        }

        $this->illuminateRequest = IlluminateRequest::createFromBase($request);
    }

    /**
     * @return IlluminateRequest
     */
    public function toIlluminate()
    {
        return $this->illuminateRequest;
    }

}
