<?php

declare(strict_types=1);

namespace SystemTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class GuestTest extends TestCase
{
    public function testTemplateExists(): void
    {
        $result = Request::makeRequest('guest/system/template_exists', ['file' => 'layout_default.html.twig']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertTrue($result->getResult());
    }

    public function testTemplateDoesNotExist(): void
    {
        $result = Request::makeRequest('guest/system/template_exists', ['file' => 'thisfiledoesnotexist.txt']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertFalse($result->getResult());
    }

    public function testPeriods(): void
    {
        $result = Request::makeRequest('guest/system/periods');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());
    }

    public function testPhoneCodes(): void
    {
        $result = Request::makeRequest('guest/system/phone_codes');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());
    }
}
