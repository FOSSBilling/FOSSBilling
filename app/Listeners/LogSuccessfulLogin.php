<?php

namespace App\Listeners;

use App\Events\AfterClientLogin;
use Illuminate\Support\Facades\Log;

class LogSuccessfulLogin
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
     * @param  \App\Events\AfterClientLogin  $event
     * @return void
     */
    public function handle(AfterClientLogin $event)
    {
        // Pretend like we've sent an e-mail here.
        Log::info('User '.$event->user['name'].' ('.$event->user['email'].') has logged in.');
    }
}
