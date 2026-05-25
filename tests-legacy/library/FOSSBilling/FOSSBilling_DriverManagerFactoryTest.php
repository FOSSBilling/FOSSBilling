<?php

declare(strict_types=1);

use FOSSBilling\Doctrine\DriverManagerFactory;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class FOSSBilling_DriverManagerFactoryTest extends PHPUnit\Framework\TestCase
{
    private ?string $originalConfigContents = null;

    protected function setUp(): void
    {
        $configContents = file_get_contents(PATH_CONFIG);
        if ($configContents === false) {
            self::fail('Failed to read the FOSSBilling config file.');
        }

        $this->originalConfigContents = $configContents;
    }

    public function testLegacyMysqlTypeIsNormalizedBeforeConnecting(): void
    {
        $this->writeDbConfig([
            'type' => 'mysql',
            'host' => '127.0.0.1',
            'name' => 'fossbilling',
            'user' => 'fossbilling',
            'password' => 'secret',
        ]);

        $dbConfig = DriverManagerFactory::getDatabaseConfig();
        $connection = DriverManagerFactory::getConnection();

        self::assertSame('pdo_mysql', $dbConfig['driver']);
        self::assertSame('3306', $dbConfig['port']);
        self::assertSame('pdo_mysql', $connection->getParams()['driver']);
        self::assertSame('3306', $connection->getParams()['port']);
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

        @unlink(Symfony\Component\Filesystem\Path::changeExtension(PATH_CONFIG, 'old.php'));
    }

    private function writeDbConfig(array $dbConfig): void
    {
        $config = FOSSBilling\Config::getConfig();
        $config['db'] = $dbConfig;

        $reflection = new ReflectionClass(FOSSBilling\Config::class);
        $method = $reflection->getMethod('prettyPrintArrayToPHP');
        $rendered = $method->invoke(null, $config);

        file_put_contents(PATH_CONFIG, $rendered);
        clearstatcache(true, PATH_CONFIG);

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(PATH_CONFIG, true);
        }
    }
}
