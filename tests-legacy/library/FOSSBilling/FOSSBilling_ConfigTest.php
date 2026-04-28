<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class FOSSBilling_ConfigTest extends PHPUnit\Framework\TestCase
{
    public function testPrettyPrintArrayToPhpEscapesInjectedStringValues(): void
    {
        $payload = "x']; \$GLOBALS['config_injection_test'] = true; //";
        $config = [
            'interface_ip' => $payload,
        ];

        $reflection = new ReflectionClass(FOSSBilling\Config::class);
        $method = $reflection->getMethod('prettyPrintArrayToPHP');

        $rendered = $method->invoke(null, $config);
        $filePath = tempnam(sys_get_temp_dir(), 'fossbilling_config_test_');
        if ($filePath === false) {
            self::fail('Failed to create temp file for config serialization test.');
        }

        try {
            file_put_contents($filePath, $rendered);

            unset($GLOBALS['config_injection_test']);
            $result = include $filePath;

            $this->assertIsArray($result);
            $this->assertArrayHasKey('interface_ip', $result);
            $this->assertSame($payload, $result['interface_ip']);
            $this->assertArrayNotHasKey('config_injection_test', $GLOBALS);
        } finally {
            @unlink($filePath);
            unset($GLOBALS['config_injection_test']);
        }
    }
}
