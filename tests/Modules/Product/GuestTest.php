<?php

declare(strict_types=1);

namespace ProductTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class GuestTest extends TestCase
{
    public function testGetList()
    {
        $result = Request::makeRequest('guest/product/get_list');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());
    }

    public function testGetPairs()
    {
        $result = Request::makeRequest('guest/product/get_pairs');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());
    }

    public function testGetMissingRequiredParams()
    {
        $result = Request::makeRequest('guest/product/get_pairs');
        $this->assertFalse($result->wasSuccessful(), "The request succeeded when it should not have");
        $this->assertEquals('Product ID or slug is missing', $result->getResult());
    }
}
