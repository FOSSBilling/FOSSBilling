<?php

declare(strict_types=1);

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class CronAdminTest extends TestCase
{
    public function testDoesCronWork(): void
    {
        // Get when cron was last run
        $response = Request::makeRequest('admin/cron/info');
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        if (!empty($response->getResult()['last_cron_exec'])) {
            $firstDate = new DateTime($response->getResult()['last_cron_exec']);
        } else {
            // Cron didn't have a last execution set, so let's just make up that it was an hour ago
            $firstDate = new DateTime();
            $firstDate->modify('-1 hour');
        }

        // Then run it
        $response = Request::makeRequest('admin/cron/run');
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());

        // Validate the last run date was moved up
        $response = Request::makeRequest('admin/cron/info');
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        $this->assertNotEmpty($response->getResult()['last_cron_exec']);
        $newDate = new DateTime($response->getResult()['last_cron_exec']);
        $this->assertGreaterThan($newDate, $firstDate, "Cron's last execution time was not incremented");
    }
}
