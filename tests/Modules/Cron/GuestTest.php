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
        Request::makeRequest('admin/cron/save_settings', ['ext' => 'mod_cron', 'guest_cron' => false]);
        $result = Request::makeRequest('guest/cron/run');
        $this->assertFalse($result->wasSuccessful(), 'Cron was run when it was not supposed to');

        // Enable guest cron (auto-generates hash), set last exec into the past, then validate it runs
        Request::makeRequest('admin/cron/save_settings', ['ext' => 'mod_cron', 'guest_cron' => true]);
        $config = Request::makeRequest('admin/extension/config_get', ['ext' => 'mod_cron']);
        $this->assertTrue($config->wasSuccessful(), $config->generatePHPUnitMessage());
        $hash = $config->getResult()['cron_hash'] ?? '';
        $this->assertNotEmpty($hash, 'cron_hash was not generated when guest cron was enabled');

        Request::makeRequest('admin/system/update_params', ['last_cron_exec' => date('Y-m-d H:i:s', time() - 6400)]);
        $result = Request::makeRequest('guest/cron/run', ['hash' => $hash]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        // Validate that the rate limit is working
        $result = Request::makeRequest('guest/cron/run', ['hash' => $hash]);
        $this->assertTrue($result->wasSuccessful(), 'Cron ran when it should have been rate limited');
        $this->assertFalse($result->getResult());

        // Validate that a wrong hash is rejected
        $result = Request::makeRequest('guest/cron/run', ['hash' => 'invalid']);
        $this->assertFalse($result->wasSuccessful(), 'Cron ran with an invalid hash');

        // Validate that a missing hash is rejected
        $result = Request::makeRequest('guest/cron/run');
        $this->assertFalse($result->wasSuccessful(), 'Cron ran without a hash');
    }
}
