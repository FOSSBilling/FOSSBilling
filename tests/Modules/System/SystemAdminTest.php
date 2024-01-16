<?php

declare(strict_types=1);

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class SystemAdminTest extends TestCase
{
    private function ipLookupWorking(): bool
    {
        $services = ['https://api64.ipify.org', 'https://ifconfig.io/ip', 'https://ip.hestiacp.com/'];
        foreach ($services as $service) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
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
        $response = Request::makeRequest('admin/system/clear_cache');
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        $this->assertIsBool($response->getResult());
    }

    public function testErrorReportingToggle(): void
    {
        // Get the starting value
        $before = Request::makeRequest('admin/system/error_reporting_enabled')->getResult();
        $this->assertIsBool($before);

        // Toggle the option
        $response = Request::makeRequest('admin/system/toggle_error_reporting');
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        $this->assertTrue($response->getResult());

        // Check that it was correctly switched
        $after = Request::makeRequest('admin/system/error_reporting_enabled')->getResult();
        $this->assertIsBool($after);
        $this->assertNotEquals($after, $before);

        // Ensure we don't leave error reporting on (it shouldn't report anyways, but this is best practice)
        if ($after) {
            $response = Request::makeRequest('admin/system/toggle_error_reporting');
            $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
            $this->assertTrue($response->getResult());
        }
    }

    public function testGetAndSetNetworkInterfaces(): void
    {
        // Get the list of network interfaces, validate the response is as expected
        $response = Request::makeRequest('admin/system/get_interface_ips');
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        $this->assertIsArray($response->getResult());

        // And then validate they are all valid IP addresses
        foreach ($response->getResult() as $ip) {
            $this->assertTrue((bool) filter_var($ip, FILTER_VALIDATE_IP));
        }

        // Only test each found interface if ipify.org is functioning
        if ($this->ipLookupWorking()) {
            foreach ($response->getResult() as $ip) {
                $response = Request::makeRequest('admin/system/set_interface_ip', ['interface_ip' => $ip]);
                $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());

                $response = Request::makeRequest('admin/system/env', ['ip' => true]);
                $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
                $this->assertTrue((bool) filter_var($response->getResult(), FILTER_VALIDATE_IP));
            }
        }

        // Finally, set it back to the default interface
        Request::makeRequest('admin/system/set_interface_ip', ['interface_ip' => '0']);
    }

    public function testInterfaceIsIgnoredWhenNotValid(): void
    {
        // Set the network interface to one that's invalid
        $response = Request::makeRequest('admin/system/set_interface_ip', ['interface_ip' => '12345']);
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        $this->assertTrue($response->getResult());

        // Now we validate that the system is discarding it for not being one of the local interface IPs, ensuring that outbound communication still works
        if ($this->ipLookupWorking()) {
            $response = Request::makeRequest('admin/system/env', ['ip' => true]);
            $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
            $this->assertTrue((bool) filter_var($response->getResult(), FILTER_VALIDATE_IP));
        }
    }

    public function testCustomInterface(): void
    {
        // Validate that we can set the custom network interface without errors
        $response = Request::makeRequest('admin/system/set_interface_ip', ['custom_interface_ip' => '12345']);
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        $this->assertTrue($response->getResult());

        // And since we don't (can't) perform any checks against the custom interface, it should now be in use despite not being valid and as a result the system will be unable to get it's IP address
        if ($this->ipLookupWorking()) {
            $response = Request::makeRequest('admin/system/env', ['ip' => true]);
            $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
            $this->assertNotEmpty($response->getResult());
        }

        // Finally reset everything to ensure networking will continue to function
        $response = Request::makeRequest('admin/system/set_interface_ip', ['custom_interface_ip' => '', 'interface_ip' => '0']);
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        $this->assertTrue($response->getResult());
    }
}
