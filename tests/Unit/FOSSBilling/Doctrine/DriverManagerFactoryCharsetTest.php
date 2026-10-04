<?php

declare(strict_types=1);

use FOSSBilling\Config;
use FOSSBilling\Doctrine\DriverManagerFactory;
use Symfony\Component\Filesystem\Filesystem;

test('MySQL defaults to full Unicode and preserves explicit supported charsets', function (?string $charset, string $expected): void {
    $filesystem = new Filesystem();
    $original = $filesystem->readFile(PATH_CONFIG);
    $config = Config::getConfig();
    $config['db'] = ['driver' => 'pdo_mysql', 'host' => '127.0.0.1', 'port' => 3306, 'name' => 'db', 'user' => 'u', 'password' => 'p'];
    if ($charset !== null) {
        $config['db']['charset'] = $charset;
    }

    try {
        $filesystem->dumpFile(PATH_CONFIG, '<?php return ' . var_export($config, true) . ';');
        expect(DriverManagerFactory::getConnection()->getParams()['charset'])->toBe($expected);
    } finally {
        $filesystem->dumpFile(PATH_CONFIG, $original);
    }
})->with([[null, 'utf8mb4'], ['invalid', 'utf8mb4'], ['utf8mb4', 'utf8mb4'], ['utf8', 'utf8'], ['latin1', 'latin1']]);
