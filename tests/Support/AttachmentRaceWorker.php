<?php

declare(strict_types=1);

require __DIR__ . '/../../src/load.php';
require __DIR__ . '/../../src/vendor/autoload.php';

use Box\Mod\Client\ClientRowLock;
use Box\Mod\Support\TicketFiles;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

$db = DriverManager::getConnection(json_decode((string) getenv('ATTACHMENT_RACE_DB'), true, 512, JSON_THROW_ON_ERROR));
if ($db->getDatabasePlatform() instanceof Doctrine\DBAL\Platforms\SQLitePlatform) {
    $db->executeStatement('PRAGMA busy_timeout = 5000');
}
if (in_array(getenv('ATTACHMENT_RACE_ACTION'), ['prune', 'legacy_cleanup'], true) && $db->getDatabasePlatform() instanceof Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
    $db->executeStatement('SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL REPEATABLE READ');
}
$di = new Pimple\Container();
$di['em'] = new class($db) {
    public function __construct(private Doctrine\DBAL\Connection $db)
    {
    }

    public function getConnection(): Doctrine\DBAL\Connection
    {
        return $this->db;
    }
};
$di['logger'] = new Psr\Log\NullLogger();
$di['mod_config'] = $di->protect(fn (): array => []);
$di['session'] = new class {
    public function getId(): string
    {
        return 'race-session';
    }
};
putenv('FOSSBILLING_TICKET_FILES_DIR=' . getenv('ATTACHMENT_RACE_DIR'));
fwrite(STDOUT, "ready\n");
fflush(STDOUT);

try {
    $action = getenv('ATTACHMENT_RACE_ACTION');
    if ($action === 'legacy_cleanup') {
        $result = TicketFiles::cleanupLegacyOrphans($di, true);
    } elseif ($action === 'prune') {
        $result = TicketFiles::prune($di);
    } elseif ($action === 'delete') {
        $db->transactional(function () use ($db): void {
            if (ClientRowLock::acquire($db, 7)) {
                TicketFiles::forgetClient($db, 7);
                $db->executeStatement('DELETE FROM support_ticket WHERE client_id = 7');
                $db->delete('client', ['id' => 7]);
            }
        });
        $result = 'deleted';
    } elseif ($action === 'attach') {
        $result = TicketFiles::attach($di, 60, true, [(string) getenv('ATTACHMENT_RACE_TOKEN')], 70);
    } elseif ($action === 'mail') {
        $result = TicketFiles::attachFromMail($di, 7, 60, 70, [['name' => 'race.txt', 'content' => 'race']]);
    } else {
        $file = tempnam(sys_get_temp_dir(), 'fb-race-upload-');
        file_put_contents($file, 'race');
        $upload = new UploadedFile($file, 'race.txt', 'text/plain', null, true);
        $result = $action === 'staff' ? TicketFiles::uploadForStaff($di, 1, 60, $upload) : TicketFiles::uploadForClient($di, 7, $action === 'draft' ? null : 60, $upload);
    }
    fwrite(STDOUT, json_encode(['result' => $result], JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $e) {
    fwrite(STDOUT, json_encode(['error' => $e->getMessage()], JSON_THROW_ON_ERROR) . "\n");
}
