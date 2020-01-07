<?php
namespace FastLaravel\Http\Server;

use DateTimeInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use FastLaravel\Http\Facade\Show;

/**
 * Class AccessLog
 *
 * @codeCoverageIgnore
 */
class AccessOutput
{
    /**
     * AccessOutput constructor.
     */
    public function __construct()
    {}

    /**
     * Access log.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Symfony\Component\HttpFoundation\Response $response
     */
    public function log(Request $request, Response $response=null): void
    {
        $host = $request->url();
        $method = $request->method();
        $agent = $request->userAgent();
        if ($response) {
            $date = $this->date($response->getDate());
            $status = $response->status();
        } else {
            $date = date('Y/m/d H:i:s', $request->server('REQUEST_TIME'));
            $status = 500;
        }
        $style = $this->style($status);
        $useTime = round((microtime(true) - $request->server('REQUEST_TIME_FLOAT')) * 1000);
        Show::writeln(
            sprintf("<cyan>%s</cyan> <yellow>%s</yellow> %s <$style>%d</$style> %s <yellow>%s</yellow>ms",
                $date,
                $method,
                $host,
                $status,
                $agent,
                $useTime
            )
        );
    }

    /**
     * @param \DateTimeInterface $date
     *
     * @return string
     */
    protected function date(DateTimeInterface $date): string
    {
        return $date->format('Y/m/d H:i:s');
    }

    /**
     * @param int $status
     *
     * @return string
     */
    protected function style(int $status): string
    {
        return $status !== Response::HTTP_OK ? 'error' : 'info';
    }
}