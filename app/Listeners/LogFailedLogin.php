<?php

namespace App\Listeners;

use App\Events\ClientLoginFailed;
use Illuminate\Support\Facades\Log;

class LogFailedLogin
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\ClientLoginFailed  $event
     * @return void
     */
    public function handle(ClientLoginFailed $event)
    {
        // Pretend like we've sent an e-mail here.
        Log::warning('This is a test. Somebody failed to log in.');
    }
}
