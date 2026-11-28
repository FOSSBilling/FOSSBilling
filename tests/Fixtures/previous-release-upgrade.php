<?php

declare(strict_types=1);

use FOSSBilling\Core\Doctrine\DriverManagerFactory;
use FOSSBilling\Core\Doctrine\EntityManagerFactory;
use FOSSBilling\Core\Security\Crypt;
use FOSSBilling\Core\System\Config;
use FOSSBilling\Core\Update\Finalization;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

require dirname(__DIR__, 2) . '/src/vendor/autoload.php';
putenv('APP_ENV=test');
define('DEBUG', false);
define('PATH_ROOT', $argv[1]);
define('PATH_DATA', PATH_ROOT . '/data');
define('PATH_CACHE', PATH_DATA . '/cache');
define('PATH_CONFIG', PATH_ROOT . '/config.php');
define('PATH_MODS', PATH_ROOT . '/modules');
define('PATH_THEMES', PATH_ROOT . '/themes');
define('PATH_LIBRARY', PATH_ROOT . '/library');
define('PATH_CORE', PATH_ROOT . '/core');
define('PATH_VENDOR', dirname(__DIR__, 2) . '/src/vendor');
define('ADMIN_PREFIX', '/admin');
define('SYSTEM_URL', 'https://example.test/');
function __trans(string $message, ?array $values = null, ?string $from = null): string
{
    return strtr($message, $values ?? []);
}
$filesystem = new Filesystem();
$filesystem->mkdir([PATH_CACHE, PATH_LIBRARY, PATH_THEMES]);
// Patches may rename/remove module files: never point their paths at the checkout.
foreach (Finder::create()->files()->in(dirname(__DIR__, 2) . '/src/modules')->exclude(['tests', 'assets']) as $file) {
    $filesystem->copy($file->getPathname(), PATH_MODS . '/' . $file->getRelativePathname());
}
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Box\\Mod\\')) {
        $path = PATH_MODS . '/' . str_replace('\\', '/', substr($class, 8)) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
}, true, true);
$version = $argv[2];
$fixture = __DIR__ . '/upgrades/' . $version;
$params = json_decode((string) getenv('ATTACHMENT_TEST_MYSQL_PARAMS'), true, flags: JSON_THROW_ON_ERROR);
$database = 'fb_upgrade_' . bin2hex(random_bytes(6));
$admin = DriverManagerFactory::getConnection([], ['name' => $params['dbname']] + $params);
$admin->executeStatement('CREATE DATABASE ' . $database);
$params['dbname'] = $database;
$db = DriverManagerFactory::getConnection([], ['name' => $params['dbname']] + $params);

try {
    $pdo = $db->getNativeConnection();
    $pdo->exec($filesystem->readFile($fixture . '/structure.sql'));
    $pdo->exec($filesystem->readFile($fixture . '/content.sql'));
    $oldPatch = (int) $db->fetchOne("SELECT value FROM setting WHERE param = 'last_patch'");
    $config = require $fixture . '/config.php.txt';
    $config['db'] = ['driver' => 'pdo_mysql', 'host' => $params['host'], 'port' => $params['port'], 'name' => $database, 'user' => $params['user'], 'password' => $params['password']];
    $config['path_data'] = PATH_DATA;
    $config['url'] = 'https://example.test';
    $config['salt'] = 'fixture-encryption-key';
    $config['info']['salt'] = 'fixture-encryption-key';
    $config['maintenance_mode'] = ['enabled' => false, 'allowed_urls' => ['/keep-this'], 'allowed_ips' => []];
    $filesystem->dumpFile(PATH_CONFIG, '<?php return ' . var_export($config, true) . ';');
    $db->insert('client', ['id' => 9001, 'email' => 'existing@example.test', 'first_name' => 'Existing customer']);
    $db->insert('invoice', ['id' => 9001, 'client_id' => 9001, 'nr' => 42, 'serie' => 'INV-', 'status' => 'paid', 'approved' => 1, 'currency' => 'USD']);
    $db->insert('client_balance', ['id' => 9001, 'client_id' => 9001, 'amount' => '25.00', 'type' => 'transaction', 'rel_id' => 9001]);
    $db->insert('support_ticket', ['id' => 9001, 'client_id' => 9001, 'subject' => 'Existing ticket', 'status' => 'open']);
    $db->insert('setting', ['param' => 'upgrade_fixture', 'value' => 'keep-me']);
    $di = new Pimple\Container([
        'em' => EntityManagerFactory::create($db), 'pdo' => $pdo, 'crypt' => new Crypt(),
        'cache' => new Symfony\Component\Cache\Adapter\ArrayAdapter(),
    ]);
    $di['logger'] = new class extends Psr\Log\NullLogger {
        public function log($level, string|Stringable $message, array $context = []): void
        {
            if ($level === 'error') {
                fwrite(STDERR, $message . "\n");
            }
        }

        public function withChannel(string $channel): static
        {
            return $this;
        }
    };
    $di['mod_service'] = $di->protect(static function (string $module, ?string $sub = null) use ($di): object {
        $class = 'Box\\Mod\\' . ucfirst($module) . '\\Service' . ($sub ?? '');
        $service = new $class();
        $service->setDi($di);

        return $service;
    });
    $patcher = new class extends FOSSBilling\Core\Update\Patcher {
        public bool $interrupt = false;

        public function applyCorePatches(bool $force = false): void
        {
            parent::applyCorePatches($force);
            if ($this->interrupt) {
                $this->interrupt = false;

                throw new RuntimeException('Interrupted before finalization state commit');
            }
        }
    };
    $patcher->interrupt = ($argv[3] ?? '') === 'interrupt';
    $patcher->setDi($di);
    $service = new class($patcher) extends Finalization {
        public function __construct(private readonly FOSSBilling\Core\Update\Patcher $patcher)
        {
            parent::__construct();
        }

        protected function createPatcher(): FOSSBilling\Core\Update\Patcher
        {
            return $this->patcher;
        }
    };
    $service->setDi($di);
    // Old releases have no finalization file. Bootstrap discovers this itself.
    $interrupted = null;

    try {
        $service->finalizePendingUpdate();
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'Interrupted before finalization state commit') {
            throw $e;
        }
        $interrupted = $service->getStatus(false)['status'];
        if (!Config::getProperty('maintenance_mode.enabled')) {
            throw new RuntimeException('Interrupted upgrade unexpectedly reopened the application');
        }
        $service->finalizePendingUpdate();
    }
    $pending = $service->getStatus(false);
    $service->completeFinalization();
    $service->finalizePendingUpdate();
    echo json_encode([
        'old_patch' => $oldPatch,
        'new_patch' => (int) $db->fetchOne("SELECT value FROM setting WHERE param = 'last_patch'"),
        'status_before_completion' => $pending['status'],
        'required' => $service->isRequired(false),
        'salt' => Config::getProperty('info.salt'),
        'maintenance' => Config::getProperty('maintenance_mode'),
        'client' => $db->fetchOne('SELECT email FROM client WHERE id = 9001'),
        'ticket' => $db->fetchOne('SELECT subject FROM support_ticket WHERE id = 9001'),
        'setting' => $db->fetchOne("SELECT value FROM setting WHERE param = 'upgrade_fixture'"),
        'invoice' => $db->fetchAssociative('SELECT nr, status, issued FROM invoice WHERE id = 9001'),
        'wallet' => (float) $db->fetchOne('SELECT SUM(amount) FROM client_balance WHERE client_id = 9001'),
        'journal_entries' => (int) $db->fetchOne('SELECT COUNT(*) FROM invoice_event WHERE invoice_id = 9001'),
        'interrupted' => $interrupted,
        'schema_failure' => $patcher->lastSchemaSyncFailure(),
        'tables' => $db->createSchemaManager()->listTableNames(),
        'transaction_columns' => array_keys($db->createSchemaManager()->listTableColumns('transaction')),
    ], JSON_THROW_ON_ERROR);
} finally {
    $db->close();
    $admin->executeStatement('DROP DATABASE ' . $database);
    $admin->close();
}
