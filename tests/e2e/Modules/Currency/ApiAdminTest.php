<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace FOSSBilling\Tests\E2E\Modules\Currency;

use FOSSBilling\Tests\E2E\TestCase;
use FOSSBilling\Tests\E2E\ApiClient;

final class ApiAdminTest extends TestCase
{
    public function testGetAvailableCurrencies(): void
    {
        $result = ApiClient::request('admin/currency/get_pairs');
        $this->assertTrue($result->wasSuccessful());

        $list = $result->getResult();
        $this->assertArrayHasKey('USD', $list);
        $this->assertArrayHasKey('EUR', $list);
        $this->assertArrayHasKey('GBP', $list);
        $this->assertArrayHasKey('JPY', $list);
        $this->assertArrayHasKey('CHF', $list);
        $this->assertArrayHasKey('AUD', $list);
        $this->assertArrayHasKey('CAD', $list);
        $this->assertArrayHasKey('NZD', $list);
        $this->assertArrayHasKey('INR', $list);
        $this->assertArrayHasKey('HKD', $list);

        $this->assertArrayNotHasKey('XXX', $list);
        $this->assertArrayNotHasKey('XTS', $list);
        $this->assertArrayNotHasKey('VES', $list);
        $this->assertArrayNotHasKey('BZR', $list);
    }
}
