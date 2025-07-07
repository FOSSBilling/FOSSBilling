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
        // Clear the cache
        $result = Request::makeRequest('admin/system/clear_cache');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsBool($result->getResult());
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
        $this->assertNotEquals($after, $before);

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
                $this->assertTrue($testResult->wasSuccessful(), $result->generatePHPUnitMessage());

                sleep(2);

                $result = Request::makeRequest('admin/system/env', ['ip' => true]);
                $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
                $this->assertTrue((bool) filter_var($result->getResult(), FILTER_VALIDATE_IP));
            }
        }

        // Finally, set it back to the default interface
        Request::makeRequest('admin/system/set_interface_ip', ['interface' => '0']);
    }

    public function testInterfaceIsIgnoredWhenNotValid(): void
    {
        // Set the network interface to one that's invalid
        $result = Request::makeRequest('admin/system/set_interface_ip', ['interface' => '12345']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertTrue($result->getResult());

        // Now we validate that the system is discarding it for not being one of the local interface IPs, ensuring that outbound communication still works
        if ($this->ipLookupWorking()) {
            sleep(2);
            $result = Request::makeRequest('admin/system/env', ['ip' => true]);
            $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
            $this->assertTrue((bool) filter_var($result->getResult(), FILTER_VALIDATE_IP));
        }
    }

    public function testCustomInterface(): void
    {
        // Validate that we can set the custom network interface without errors
        $result = Request::makeRequest('admin/system/set_interface_ip', ['custom_interface' => '12345']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertTrue($result->getResult());

        // And since we don't (can't) perform any checks against the custom interface, it should now be in use despite not being valid and as a result the system will be unable to get its IP address
        if ($this->ipLookupWorking()) {
            sleep(2);
            $result = Request::makeRequest('admin/system/env', ['ip' => true]);
            $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
            $this->assertEmpty($result->getResult());
        }

        // Finally reset everything to ensure networking will continue to function
        $result = Request::makeRequest('admin/system/set_interface_ip', ['custom_interface' => '', 'interface' => '0']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertTrue($result->getResult());
    }
}
