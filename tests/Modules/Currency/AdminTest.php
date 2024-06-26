<?php

declare(strict_types=1);

namespace CurrencyTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase
{
    public function testGetAvailableCurrencies()
    {
        $result = Request::makeRequest('admin/currency/get_pairs');
        $this->assertFalse($result->wasSuccessful());

        $list = $result->getResult();
        // These for sure should exist
        $this->assertArrayHasKey('USD', $list);
        $this->assertArrayHasKey('EUR', $list);
        $this->assertArrayHasKey('GBP', $list);
        $this->assertArrayHasKey('JPY', $list);
        $this->assertArrayHasKey('CHF', $list);
        $this->assertArrayHasKey('AUD', $list);
        $this->assertArrayHasKey('CAD', $list);
        $this->assertArrayHasKey('NZD', $list);
        $this->assertArrayHasKey('INR', $list);
        $this->assertArrayHasKey('BZR', $list);

        // These should not exist
        $this->assertArrayNotHasKey('XXX', $list);
        $this->assertArrayNotHasKey('XTS', $list);
        $this->assertArrayNotHasKey('VES', $list);
    }
}
