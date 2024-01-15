<?php

declare(strict_types=1);

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class CronGuestTest extends TestCase
{
    public function testCronGuestBehavior(): void
    {
        // Ensure cron does not run for guests when disabled
        Request::makeRequest('admin/system/update_params', ['guest_cron' => false]);
        $response = Request::makeRequest('guest/cron/run');
        $this->assertFalse($response->wasSuccessful(), 'Cron was run when it was not supposed to');

        // Now enable it, set the last exec back into the past, and then validate it does run
        Request::makeRequest('admin/system/update_params', ['guest_cron' => true]);
        Request::makeRequest('admin/system/update_params', ['last_cron_exec' => date('Y-m-d H:i:s', time() - 6400)]);
        $response = Request::makeRequest('guest/cron/run');
        $this->assertTrue($response->wasSuccessful(), 'Cron should have run, but did not');

        // Finally, ensure the rate limit is working
        $response = Request::makeRequest('guest/cron/run');
        $this->assertFalse($response->wasSuccessful(), 'Cron ran when it should have been rate limited');
    }
}
