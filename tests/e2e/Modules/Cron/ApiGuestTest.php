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

final class ApiGuestTest extends TestCase
{
    public function testCronGuestBehavior(): void
    {
        ApiClient::request('admin/extension/config_save', ['ext' => 'mod_cron', 'guest_cron' => false]);
        $result = ApiClient::request('guest/cron/run');
        $this->assertFalse($result->wasSuccessful());

        ApiClient::request('admin/extension/config_save', ['ext' => 'mod_cron', 'guest_cron' => true]);
        ApiClient::request('admin/system/update_params', ['last_cron_exec' => date('Y-m-d H:i:s', time() - 6400)]);
        $result = ApiClient::request('guest/cron/run');
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());

        $result = ApiClient::request('guest/cron/run');
        $this->assertTrue($result->wasSuccessful());
        $this->assertFalse($result->getResult());
    }
}
