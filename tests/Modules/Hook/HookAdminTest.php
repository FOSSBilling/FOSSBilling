<?php

declare(strict_types=1);

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class HookAdminTest extends TestCase
{
    public function testBatchConnect(): void
    {
        $response = Request::makeRequest('admin/hook/batch_connect');
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
    }

    public function testGetHookList(): void
    {
        $response = Request::makeRequest('admin/hook/get_list');
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        $this->assertIsArray($response->getResult());
        $this->assertNotEmpty($response->getResult());
    }

    public function testHookCall(): void
    {
        $response = Request::makeRequest('admin/hook/call', ['event' => 'onBeforeAdminCronRun']);
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
    }
}
