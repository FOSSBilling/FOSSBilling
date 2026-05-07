<?php

declare(strict_types=1);

namespace SystemTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase
{
    private const int MAX_RETRY_ATTEMPTS = 10;
    private const int RETRY_DELAY_MICROSECONDS = 200000;
    private const string DEFAULT_INTERFACE = '0';

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
        $beforeResult = Request::makeRequest('admin/system/error_reporting_enabled');
        $this->assertTrue($beforeResult->wasSuccessful(), $beforeResult->generatePHPUnitMessage());
        $before = $beforeResult->getResult();
        $this->assertIsBool($before);

        try {
            // Toggle the option
            $result = Request::makeRequest('admin/system/toggle_error_reporting');
            $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
            $this->assertTrue($result->getResult());

            // Check that it was correctly switched
            $afterResponse = Request::makeRequest('admin/system/error_reporting_enabled');
            $this->assertTrue($afterResponse->wasSuccessful(), $afterResponse->generatePHPUnitMessage());
            $after = $afterResponse->getResult();
            $this->assertIsBool($after);
            $this->assertNotSame($before, $after);
        } finally {
            // Always restore original state, even if assertions above fail
            $currentResponse = Request::makeRequest('admin/system/error_reporting_enabled');
            $this->assertTrue($currentResponse->wasSuccessful(), $currentResponse->generatePHPUnitMessage());
            $current = $currentResponse->getResult();
            $this->assertIsBool($current);

            if ($current !== $before) {
                $restoreResult = Request::makeRequest('admin/system/toggle_error_reporting');
                $this->assertTrue($restoreResult->wasSuccessful(), $restoreResult->generatePHPUnitMessage());
                $this->assertTrue($restoreResult->getResult());
            }
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
        if ($this->isIpLookupAvailable()) {
            foreach ($result->getResult() as $ip) {
                $testResult = Request::makeRequest('admin/system/set_interface_ip', ['interface' => $ip]);
                $this->assertTrue($testResult->wasSuccessful(), $testResult->generatePHPUnitMessage());

                $isReady = false;
                for ($attempt = 0; $attempt < self::MAX_RETRY_ATTEMPTS; ++$attempt) {
                    $envResult = Request::makeRequest('admin/system/env', ['ip' => true]);
                    if ($envResult->wasSuccessful() && (bool) filter_var($envResult->getResult(), FILTER_VALIDATE_IP)) {
                        $isReady = true;

                        break;
                    }

                    usleep(self::RETRY_DELAY_MICROSECONDS);
                }

                $this->assertTrue($isReady, 'Timed out waiting for interface IP to become active');
            }
        }

        // Finally, set it back to the default interface
        $cleanupResult = Request::makeRequest('admin/system/set_interface_ip', ['interface' => self::DEFAULT_INTERFACE]);
        $this->assertTrue($cleanupResult->wasSuccessful(), $cleanupResult->generatePHPUnitMessage());
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

    #[\PHPUnit\Framework\Attributes\DataProvider('maliciousInterfacePayloadProvider')]
    public function testMaliciousInterfaceIsRejected(string $payload): void
    {
        $result = Request::makeRequest('admin/system/set_interface_ip', ['interface' => $payload]);
        $this->assertFalse(
            $result->wasSuccessful(),
            "A malicious interface value was accepted when it should have been rejected: {$payload}"
        );
    }

    public static function maliciousInterfacePayloadProvider(): array
    {
        return [
            'quote-escape' => ["x\"; echo 'pwned'; //"],
            'pipe-command' => ['eth0|id'],
            'backticks' => ['eth0`id`'],
            'sub-shell' => ['eth0$(id)'],
            'and-command' => ['eth0 && whoami'],
            'redirect' => ['eth0 > /tmp/pwned'],
            'path-traversal-unix' => ['../etc/passwd'],
            'path-traversal-windows' => ['..\\..\\windows\\system32\\drivers\\etc\\hosts'],
            'null-byte' => ["eth0\0evil"],
            'long-string' => [str_repeat('a', 1024)],
            'newline-injection' => ["eth0\nwhoami"],
            'leading-trailing-space' => [' eth0 '],
        ];
    }

    public function testMaliciousCustomInterfaceIsRejected(): void
    {
        $result = Request::makeRequest('admin/system/set_interface_ip', ['custom_interface' => "x\"; passthru('id'); //"]);
        $this->assertFalse($result->wasSuccessful(), 'A malicious custom interface value was accepted when it should have been rejected');
    }

    public function testCustomInterfaceAcceptsValidHostname(): void
    {
        try {
            $result = Request::makeRequest('admin/system/set_interface_ip', ['custom_interface' => 'eth0']);
            $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        } finally {
            $this->resetInterfaceConfiguration();
        }
    }

    private function resetInterfaceConfiguration(): void
    {
        $resetResult = Request::makeRequest(
            'admin/system/set_interface_ip',
            ['custom_interface' => '', 'interface' => self::DEFAULT_INTERFACE]
        );
        $this->assertTrue($resetResult->wasSuccessful(), $resetResult->generatePHPUnitMessage());
    }

    private function isIpLookupAvailable(): bool
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2,
            ],
        ]);

        set_error_handler(static fn (): bool => true);

        try {
            $response = file_get_contents('https://api.ipify.org', false, $context);
        } finally {
            restore_error_handler();
        }

        return $response !== false;
    }
}
