<?php

namespace App\Listeners;

class WorkerStartListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \Swoole\Http\Server  $server
     * @params int $workerId
     *
     * @return void
     */
    public function handle($server, $workerId)
    {
        echo "In workerStartListener:worker id:{$workerId}\n";
    }
}
