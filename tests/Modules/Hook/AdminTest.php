<?php

declare(strict_types=1);

namespace HookTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase
{
    public function testBatchConnect(): void
    {
        $result = Request::makeRequest('admin/hook/batch_connect');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertTrue($result->getResult());
    }

    public function testGetHookList(): void
    {
        $result = Request::makeRequest('admin/hook/get_list');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());
        $this->assertNotEmpty($result->getResult());
    }

    public function testHookCall(): void
    {
        $result = Request::makeRequest('admin/hook/call', ['event' => 'onBeforeAdminCronRun']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
    }
}
