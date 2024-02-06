<?php

declare(strict_types=1);

namespace FormbuilderTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class GuestTest extends TestCase
{
    public function testGet()
    {
        $result = Request::makeRequest('guest/formbuilder/get');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());
    }
}
