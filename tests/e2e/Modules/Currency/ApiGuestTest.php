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

use FOSSBilling\Tests\Library\E2E\TestCase;
use FOSSBilling\Tests\Library\E2E\ApiClient;

final class ApiGuestTest extends TestCase
{
    public function testCurrencyDefaults(): void
    {
        $result = ApiClient::request('guest/currency/get_currency_defaults', ['code' => 'USD']);
        $this->assertTrue($result->wasSuccessful());

        $defaults = $result->getResult();
        $this->assertEquals($defaults['code'], 'USD');
        $this->assertEquals($defaults['name'], 'US Dollar');
        $this->assertEquals($defaults['symbol'], '$');
        $this->assertEquals($defaults['minorUnits'], 2);
    }
}
