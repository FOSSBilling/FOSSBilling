<?php

declare(strict_types=1);

namespace CronTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class GuestTest extends TestCase
{
    public function testCronGuestBehavior(): void
    {
        // Ensure cron does not run for guests when disabled
        Request::makeRequest('admin/extension/config_save', ['ext' => 'mod_cron', 'guest_cron' => false]);
        $result = Request::makeRequest('guest/cron/run');
        $this->assertFalse($result->wasSuccessful(), 'Cron was run when it was not supposed to');

        // Now enable it, set the last exec back into the past, and then validate it does run
        Request::makeRequest('admin/extension/config_save', ['ext' => 'mod_cron', 'guest_cron' => true]);
        Request::makeRequest('admin/system/update_params', ['last_cron_exec' => date('Y-m-d H:i:s', time() - 6400)]);
        $result = Request::makeRequest('guest/cron/run');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        // Finally, ensure the rate limit is working
        $result = Request::makeRequest('guest/cron/run');
        $this->assertTrue($result->wasSuccessful(), 'Cron ran when it should have been rate limited');
        $this->assertFalse($result->getResult()); // Returns false when rate-limited
    }
}
