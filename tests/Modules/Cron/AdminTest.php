<?php

declare(strict_types=1);

namespace CronTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase
{
    public function testDoesCronWork(): void
    {
        // Get when cron was last run
        $result = Request::makeRequest('admin/cron/info');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        if (!empty($result->getResult()['last_cron_exec'])) {
            $firstDate = new \DateTime($result->getResult()['last_cron_exec']);
        } else {
            // Cron didn't have a last execution set, so let's just make up that it was an hour ago
            $firstDate = new \DateTime();
            $firstDate->modify('-1 hour');
        }

        // Then run it
        $result = Request::makeRequest('admin/cron/run');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        // This seems to be failing for no real reason, so let's try a slight delay incase we are trying to pull from the DB too soon or something
        sleep(1);

        // Validate the last run date was moved up
        $result = Request::makeRequest('admin/cron/info');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertNotEmpty($result->getResult()['last_cron_exec']);

        $newDate = new \DateTime($result->getResult()['last_cron_exec']);
        $this->assertGreaterThan($firstDate, $newDate, "Cron's last execution time was not incremented");
    }
}
