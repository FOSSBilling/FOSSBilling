<?php

declare(strict_types=1);

namespace SystemTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase
{
    private function ipLookupWorking(): bool
    {
        $services = ['https://api64.ipify.org', 'https://ifconfig.io/ip', 'https://ip.hestiacp.com/'];
        foreach ($services as $service) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            curl_setopt($ch, CURLOPT_URL, $service);
            $ip = curl_exec($ch);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return true;
            }
        }

        return false;
    }

    public function testClearCache(): void
    {
        // Read a stable value first so we can verify behavior before/after clearing cache
        $beforeFirst = Request::makeRequest('admin/system/error_reporting_enabled');
        $this->assertTrue($beforeFirst->wasSuccessful(), $beforeFirst->generatePHPUnitMessage());
        $this->assertIsBool($beforeFirst->getResult());

        // Read again to establish deterministic pre-clear behavior
        $beforeSecond = Request::makeRequest('admin/system/error_reporting_enabled');
        $this->assertTrue($beforeSecond->wasSuccessful(), $beforeSecond->generatePHPUnitMessage());
        $this->assertIsBool($beforeSecond->getResult());
        $this->assertSame($beforeFirst->getResult(), $beforeSecond->getResult());

        // Clear the cache
        $result = Request::makeRequest('admin/system/clear_cache');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsBool($result->getResult());

        // Verify post-condition: system value remains valid and unchanged after cache clear
        $after = Request::makeRequest('admin/system/error_reporting_enabled');
        $this->assertTrue($after->wasSuccessful(), $after->generatePHPUnitMessage());
        $this->assertIsBool($after->getResult());
        $this->assertSame($beforeSecond->getResult(), $after->getResult());
    }

    public function testErrorReportingToggle(): void
    {
        // Get the starting value
        $before = Request::makeRequest('admin/system/error_reporting_enabled')->getResult();
        $this->assertIsBool($before);

        // Toggle the option
        $result = Request::makeRequest('admin/system/toggle_error_reporting');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertTrue($result->getResult());

        // Check that it was correctly switched
        $after = Request::makeRequest('admin/system/error_reporting_enabled')->getResult();
        $this->assertIsBool($after);
        $this->assertNotEquals($before, $after);

        // Ensure we don't leave error reporting on (it shouldn't report anyways, but this is best practice)
        if ($after) {
            $result = Request::makeRequest('admin/system/toggle_error_reporting');
            $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
            $this->assertTrue($result->getResult());
        }
    }

    public function testGetAndSetNetworkInterfaces(): void
    {
        // Get the list of network interfaces, validate the response is as expected
        $result = Request::makeRequest('admin/system/get_interface_ips');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());

        // And then validate they are all valid IP addresses
        foreach ($result->getResult() as $ip) {
            $this->assertTrue((bool) filter_var($ip, FILTER_VALIDATE_IP));
        }

        // Only test each found interface if ipify.org is functioning
        if ($this->ipLookupWorking()) {
            foreach ($result->getResult() as $ip) {
                $testResult = Request::makeRequest('admin/system/set_interface_ip', ['interface' => $ip]);
                $this->assertTrue($testResult->wasSuccessful(), $testResult->generatePHPUnitMessage());

                sleep(2);

                $result = Request::makeRequest('admin/system/env', ['ip' => true]);
                $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
                $this->assertTrue((bool) filter_var($result->getResult(), FILTER_VALIDATE_IP));
            }
        }

        // Finally, set it back to the default interface
        Request::makeRequest('admin/system/set_interface_ip', ['interface' => '0']);
    }

    public function testInvalidInterfaceIsRejected(): void
    {
        $result = Request::makeRequest('admin/system/set_interface_ip', ['interface' => '12345']);
        $this->assertFalse($result->wasSuccessful(), 'An invalid interface IP was accepted when it should have been rejected');
    }

    public function testInvalidCustomInterfaceIsRejected(): void
    {
        $result = Request::makeRequest('admin/system/set_interface_ip', ['custom_interface' => '!@#$%']);
        $this->assertFalse($result->wasSuccessful(), 'An invalid custom interface was accepted when it should have been rejected');
    }

    public function testMaliciousInterfaceIsRejected(): void
    {
        $result = Request::makeRequest('admin/system/set_interface_ip', ['interface' => "x\"; echo 'pwned'; //"]);
        $this->assertFalse($result->wasSuccessful(), 'A malicious interface value was accepted when it should have been rejected');
    }

    public function testMaliciousCustomInterfaceIsRejected(): void
    {
        $result = Request::makeRequest('admin/system/set_interface_ip', ['custom_interface' => "x\"; passthru('id'); //"]);
        $this->assertFalse($result->wasSuccessful(), 'A malicious custom interface value was accepted when it should have been rejected');
    }

    public function testCustomInterfaceAcceptsValidHostname(): void
    {
        $result = Request::makeRequest('admin/system/set_interface_ip', ['custom_interface' => 'eth0']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        // Reset to default
        Request::makeRequest('admin/system/set_interface_ip', ['custom_interface' => '', 'interface' => '0']);
    }
}
