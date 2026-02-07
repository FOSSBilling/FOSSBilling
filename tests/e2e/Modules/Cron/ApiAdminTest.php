<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace FOSSBilling\Tests\E2E\Modules\Cron;

use FOSSBilling\Tests\Library\E2E\TestCase;
use FOSSBilling\Tests\Library\E2E\ApiClient;

final class ApiAdminTest extends TestCase
{
    public function testCronExecution(): void
    {
        $result = ApiClient::request('admin/cron/info');
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());

        if (!empty($result->getResult()['last_cron_exec'])) {
            $firstDate = new \DateTime($result->getResult()['last_cron_exec']);
        } else {
            $firstDate = new \DateTime();
            $firstDate->modify('-1 hour');
        }

        $result = ApiClient::request('admin/cron/run');
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());

        sleep(2);

        $result = ApiClient::request('admin/cron/info');
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertNotEmpty($result->getResult()['last_cron_exec']);

        $newDate = new \DateTime($result->getResult()['last_cron_exec']);
        $this->assertGreaterThan($firstDate, $newDate);
    }
}
