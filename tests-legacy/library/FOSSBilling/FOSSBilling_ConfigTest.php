<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class FOSSBilling_ConfigTest extends PHPUnit\Framework\TestCase
{
    private ?string $originalConfigContents = null;

    protected function setUp(): void
    {
        parent::setUp();

        $configContents = file_get_contents(PATH_CONFIG);
        if ($configContents === false) {
            self::fail('Failed to read the FOSSBilling config file.');
        }

        $this->originalConfigContents = $configContents;
    }

    protected function tearDown(): void
    {
        if ($this->originalConfigContents !== null) {
            file_put_contents(PATH_CONFIG, $this->originalConfigContents);
            clearstatcache(true, PATH_CONFIG);

            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate(PATH_CONFIG, true);
            }
        }

        parent::tearDown();
    }

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

    public function testGetConfigThrowsForNonArrayConfigFile(): void
    {
        $this->writeRawConfig("<?php return 'not-an-array';");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The FOSSBilling configuration file is empty or invalid.');

        FOSSBilling\Config::getConfig();
    }

    public function testIsConfigValidReturnsFalseForNonArrayConfigFile(): void
    {
        $this->writeRawConfig("<?php return 'not-an-array';");

        $this->assertFalse(FOSSBilling\Config::isConfigValid());
    }

    public function testInstallerTreatsExistingInvalidConfigAsAlreadyInstalled(): void
    {
        $this->writeRawConfig("<?php return 'not-an-array';");

        $this->assertSame('installed', $this->runInstallerInstalledCheck());
    }

    public function testInstallerTreatsExistingConfigAsNotInstalledInDebugMode(): void
    {
        $this->writeRawConfig("<?php return ['debug_and_monitoring' => ['debug' => true]];");

        $this->assertSame('not-installed', $this->runInstallerInstalledCheck());
    }

    public function testInstallerAllowsInstallWhenConfigFileIsMissing(): void
    {
        $this->removeConfig();

        $this->assertSame('not-installed', $this->runInstallerInstalledCheck());
    }

    private function writeRawConfig(string $contents): void
    {
        $writeResult = file_put_contents(PATH_CONFIG, $contents);
        if ($writeResult === false) {
            self::fail('Failed to write the FOSSBilling config file.');
        }

        clearstatcache(true, PATH_CONFIG);

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(PATH_CONFIG, true);
        }
    }

    private function removeConfig(): void
    {
        if (!unlink(PATH_CONFIG)) {
            self::fail('Failed to remove the FOSSBilling config file.');
        }

        clearstatcache(true, PATH_CONFIG);

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(PATH_CONFIG, true);
        }
    }

    private function runInstallerInstalledCheck(): string
    {
        $code = <<<'PHP'
            require 'src/install/install.php';
            $request = Symfony\Component\HttpFoundation\Request::create('http://localhost/install/install.php', 'GET');
            $installer = new FOSSBilling_Installer($request);
            echo $installer->isAlreadyInstalled() ? 'installed' : 'not-installed';
            PHP;

        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-d', 'display_errors=1', '-r', $code],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            dirname(__DIR__, 3)
        );

        if (!is_resource($process)) {
            self::fail('Failed to start installer check subprocess.');
        }

        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            self::fail(sprintf('Installer check subprocess failed with exit code %d: %s', $exitCode, $errorOutput));
        }

        return trim((string) $output);
    }
}
