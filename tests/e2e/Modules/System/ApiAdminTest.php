<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace FOSSBilling\Tests\E2E\Modules\System;

use FOSSBilling\Tests\E2E\TestCase;
use FOSSBilling\Tests\E2E\ApiClient;

final class ApiAdminTest extends TestCase
{
    private function ipLookupWorking(): bool
    {
        $services = ['https://api64.ipify.org', 'https://ifconfig.io/ip', 'https://ip.hestiacp.com/'];
        foreach ($services as $service) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_FAILONERROR => true,
                CURLOPT_URL => $service,
            ]);
            $ip = curl_exec($ch);
            curl_close($ch);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return true;
            }
        }

        return false;
    }

    public function testClearCache(): void
    {
        $result = ApiClient::request('admin/system/clear_cache');
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertIsBool($result->getResult());
    }

    public function testErrorReportingToggle(): void
    {
        $before = ApiClient::request('admin/system/error_reporting_enabled')->getResult();
        $this->assertIsBool($before);

        $result = ApiClient::request('admin/system/toggle_error_reporting');
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertTrue($result->getResult());

        $after = ApiClient::request('admin/system/error_reporting_enabled')->getResult();
        $this->assertIsBool($after);
        $this->assertNotEquals($after, $before);

        if ($after) {
            $result = ApiClient::request('admin/system/toggle_error_reporting');
            $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
            $this->assertTrue($result->getResult());
        }
    }

    public function testGetAndSetNetworkInterfaces(): void
    {
        $result = ApiClient::request('admin/system/get_interface_ips');
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertIsArray($result->getResult());

        foreach ($result->getResult() as $ip) {
            $this->assertTrue((bool) filter_var($ip, FILTER_VALIDATE_IP));
        }

        if ($this->ipLookupWorking()) {
            foreach ($result->getResult() as $ip) {
                $testResult = ApiClient::request('admin/system/set_interface_ip', ['interface' => $ip]);
                $this->assertTrue($testResult->wasSuccessful(), $result->generatePhpUnitMessage());

                sleep(2);

                $result = ApiClient::request('admin/system/env', ['ip' => true]);
                $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
                $this->assertTrue((bool) filter_var($result->getResult(), FILTER_VALIDATE_IP));
            }
        }

        ApiClient::request('admin/system/set_interface_ip', ['interface' => '0']);
    }

    public function testInterfaceIsIgnoredWhenNotValid(): void
    {
        $result = ApiClient::request('admin/system/set_interface_ip', ['interface' => '12345']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertTrue($result->getResult());

        if ($this->ipLookupWorking()) {
            sleep(2);
            $result = ApiClient::request('admin/system/env', ['ip' => true]);
            $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
            $this->assertTrue((bool) filter_var($result->getResult(), FILTER_VALIDATE_IP));
        }
    }

    public function testCustomInterface(): void
    {
        $result = ApiClient::request('admin/system/set_interface_ip', ['custom_interface' => '12345']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertTrue($result->getResult());

        if ($this->ipLookupWorking()) {
            sleep(2);
            $result = ApiClient::request('admin/system/env', ['ip' => true]);
            $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
            $this->assertEmpty($result->getResult());
        }

        $result = ApiClient::request('admin/system/set_interface_ip', ['custom_interface' => '', 'interface' => '0']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertTrue($result->getResult());
    }
}
