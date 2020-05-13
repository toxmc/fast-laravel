<?php

namespace App\Listeners;

class StartListener
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
     * @param \Swoole\Http\Server $server
     *
     * @return void
     */
    public function handle($server)
    {
        echo "In StartListener\n";
    }
}
