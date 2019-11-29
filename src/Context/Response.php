<?php

namespace FastLaravel\Http\Context;

use Illuminate\Http\Response as IlluminateResponse;
use InvalidArgumentException;
use Swoole\Http\Response as SwooleResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Response
{
    /**
     * @var SwooleResponse
     */
    protected $response;

    /**
     * @var IlluminateResponse
     */
    protected $illuminateResponse;

    /**
     * @var Response or null
     */
    protected static $instance = null;

    /**
     * Make a response.
     *
     * @param $illuminateResponse
     * @param SwooleResponse $response
     *
     * @return Response
     */
    public static function make($illuminateResponse, SwooleResponse $response)
    {
        if (!static::$instance) {
            static::$instance = new static($illuminateResponse, $response);
        } else {
            static::$instance->setIlluminateResponse($illuminateResponse)
                ->setResponse($response);
        }
        return static::$instance;
    }

    /**
     * Response constructor.
     *
     * @param mixed $illuminateResponse
     * @param SwooleResponse $response
     */
    public function __construct($illuminateResponse, SwooleResponse $response)
    {
        $this->setIlluminateResponse($illuminateResponse)
            ->setResponse($response);
    }

    /**
     * Sends HTTP headers and content.
     *
     * @throws InvalidArgumentException
     */
    public function send()
    {
        $this->sendHeaders();
        $this->sendContent();
    }

    /**
     * Sends HTTP headers.
     *
     * @throws InvalidArgumentException
     */
    protected function sendHeaders()
    {
        $illuminateResponse = $this->getIlluminateResponse();

        /* RFC2616 - 14.18 says all Responses need to have a Date */
        if (! $illuminateResponse->headers->has('Date')) {
            $illuminateResponse->setDate(\DateTime::createFromFormat('U', time()));
        }

        // headers
        // allPreserveCaseWithoutCookies() doesn't exist before Laravel 5.3
        $headers = $illuminateResponse->headers->allPreserveCase();
        if (isset($headers['Set-Cookie'])) {
            unset($headers['Set-Cookie']);
        }
        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $this->response->header($name, $value);
            }
        }

        // status
        $this->response->status($illuminateResponse->getStatusCode());

        // cookies
        foreach ($illuminateResponse->headers->getCookies() as $cookie) {
            // may need to consider rawcookie
            $this->response->cookie(
                $cookie->getName(), $cookie->getValue(),
                $cookie->getExpiresTime(), $cookie->getPath(),
                $cookie->getDomain(), $cookie->isSecure(),
                $cookie->isHttpOnly()
            );
        }
    }

    /**
     * Sends HTTP content.
     */
    protected function sendContent()
    {
        $illuminateResponse = $this->getIlluminateResponse();

        if ($illuminateResponse instanceof StreamedResponse && property_exists($illuminateResponse, 'output')) {
            $this->response->end($illuminateResponse->output);
        } elseif ($illuminateResponse instanceof BinaryFileResponse) {
            $this->response->sendfile($illuminateResponse->getFile()->getPathname());
        } else {
            // 分段发送每次发送100k
            if (strlen($illuminateResponse->getContent()) > 102400) {
                $contents = str_split($illuminateResponse->getContent(), 102400);
                foreach ($contents as $content) {
                    $isOk = $this->response->write($content);
                    if (!$isOk) {
                        $retry = 3;
                        while ($retry--) {
                            $isOk = $this->response->write($content);
                            if ($isOk) {
                                break;
                            }
                        }
                    }
                }
                $this->response->end();
            } else {
                $this->response->end($illuminateResponse->getContent());
            }
        }
    }

    /**
     * @param SwooleResponse $response
     * @return Response
     */
    protected function setResponse(SwooleResponse $response)
    {
        $this->response = $response;

        return $this;
    }

    /**
     * @return SwooleResponse
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param mixed illuminateResponse
     * @return Response
     */
    protected function setIlluminateResponse($illuminateResponse)
    {
        if (! $illuminateResponse instanceof SymfonyResponse) {
            $content = (string) $illuminateResponse;
            $illuminateResponse = new IlluminateResponse($content);
        }

        $this->illuminateResponse = $illuminateResponse;

        return $this;
    }

    /**
     * @return IlluminateResponse
     */
    public function getIlluminateResponse()
    {
        return $this->illuminateResponse;
    }
}
