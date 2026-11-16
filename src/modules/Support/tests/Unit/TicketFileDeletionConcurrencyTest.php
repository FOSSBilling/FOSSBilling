<?php

declare(strict_types=1);

use Box\Mod\Client\ClientRowLock;
use Box\Mod\Support\TicketFiles;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Tests\Helpers\container;

/** @return array{Doctrine\DBAL\Connection, array<string, mixed>, string, ?string} */
function attachmentRaceDatabase(string $driver): array
{
    $dir = sys_get_temp_dir() . '/fb-attachment-race-' . bin2hex(random_bytes(6));
    if ($driver === 'sqlite') {
        $params = ['driver' => 'pdo_sqlite', 'path' => $dir . '/test.sqlite'];
        $admin = null;
    } else {
        $raw = getenv('ATTACHMENT_TEST_' . strtoupper($driver) . '_PARAMS');
        if ($raw === false) {
            PHPUnit\Framework\Assert::markTestSkipped('Set ATTACHMENT_TEST_' . strtoupper($driver) . '_PARAMS to connection JSON for an isolated database server.');
        }
        $params = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $admin = $params;
        $database = 'fb_attach_' . bin2hex(random_bytes(6));
        $control = DriverManager::getConnection($admin);
        $control->executeStatement('CREATE DATABASE ' . $control->quoteSingleIdentifier($database));
        $control->close();
        $params['dbname'] = $database;
    }
    mkdir($dir, 0o700, true);
    $db = DriverManager::getConnection($params);
    if ($driver === 'sqlite') {
        $db->executeStatement('PRAGMA busy_timeout = 5000');
    }
    $id = match ($driver) {
        'mysql' => 'BIGINT AUTO_INCREMENT PRIMARY KEY', 'pgsql' => 'BIGSERIAL PRIMARY KEY', default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
    };
    $db->executeStatement('CREATE TABLE client (id BIGINT PRIMARY KEY)');
    $db->executeStatement('CREATE TABLE support_ticket (id BIGINT PRIMARY KEY, client_id BIGINT NULL)');
    $db->executeStatement('CREATE TABLE support_ticket_message (id BIGINT PRIMARY KEY, support_ticket_id BIGINT NOT NULL)');
    $db->executeStatement("CREATE TABLE support_ticket_file (id $id, token VARCHAR(32) NOT NULL UNIQUE, ticket_id BIGINT NULL, message_id BIGINT NULL, client_id BIGINT NOT NULL, uploader_kind VARCHAR(10) NOT NULL, uploader_id BIGINT NOT NULL, session_hash VARCHAR(64) NOT NULL, name VARCHAR(191) NOT NULL, mime VARCHAR(100) NOT NULL, size INTEGER NOT NULL, created_at TIMESTAMP NOT NULL, attached_at TIMESTAMP NULL, removed_at TIMESTAMP NULL, removed_by BIGINT NULL, removed_reason VARCHAR(20) NULL)");
    $db->insert('client', ['id' => 7]);
    $db->insert('support_ticket', ['id' => 60, 'client_id' => 7]);
    $db->insert('support_ticket_message', ['id' => 70, 'support_ticket_id' => 60]);
    putenv('FOSSBILLING_TICKET_FILES_DIR=' . $dir . '/files');

    return [$db, $params, $dir, $admin === null ? null : json_encode($admin, JSON_THROW_ON_ERROR)];
}

/** @return array{resource, array<int, resource>} */
function attachmentRaceWorker(array $params, string $dir, string $action): array
{
    $env = array_merge(getenv(), ['ATTACHMENT_RACE_DB' => json_encode($params, JSON_THROW_ON_ERROR), 'ATTACHMENT_RACE_DIR' => $dir . '/files', 'ATTACHMENT_RACE_ACTION' => $action]);
    $process = proc_open([PHP_BINARY, __DIR__ . '/../../../../../tests/Support/AttachmentRaceWorker.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start attachment race worker');
    }
    expect(trim((string) fgets($pipes[1])))->toBe('ready');
    usleep(250000);
    if (!proc_get_status($process)['running']) {
        throw new RuntimeException('Worker exited early: ' . stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]));
    }

    return [$process, $pipes];
}

function attachmentRaceResult($process, array $pipes): array
{
    $result = json_decode((string) stream_get_contents($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
    $stderr = stream_get_contents($pipes[2]);
    foreach ($pipes as $pipe) {
        fclose($pipe);
    }
    expect(proc_close($process))->toBe(0, $stderr);

    return $result;
}

function attachmentRaceCleanup(Doctrine\DBAL\Connection $db, array $params, string $dir, ?string $admin): void
{
    $db->close();
    if ($admin !== null) {
        $control = DriverManager::getConnection(json_decode($admin, true, 512, JSON_THROW_ON_ERROR));
        $control->executeStatement('DROP DATABASE ' . $control->quoteSingleIdentifier($params['dbname']));
        $control->close();
    }
    (new Symfony\Component\Filesystem\Filesystem())->remove($dir);
}

test('client deletion blocks uploads until the client is gone', function (string $driver, string $kind): void {
    [$db, $params, $dir, $admin] = attachmentRaceDatabase($driver);

    try {
        $db->beginTransaction();
        expect(ClientRowLock::acquire($db, 7))->toBeTrue();
        [$process, $pipes] = attachmentRaceWorker($params, $dir, $kind);
        TicketFiles::forgetClient($db, 7);
        $db->delete('support_ticket', ['id' => 60]);
        $db->delete('client', ['id' => 7]);
        $db->commit();
        $result = attachmentRaceResult($process, $pipes);
        if ($kind === 'mail') {
            expect($result['result'])->toBe(['race.txt']);
        } else {
            expect($result)->toHaveKey('error');
        }
        expect((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_file'))->toBe(0)
            ->and(is_dir($dir . '/files/7'))->toBeFalse();
    } finally {
        if ($db->isTransactionActive()) {
            $db->rollBack();
        }
        attachmentRaceCleanup($db, $params, $dir, $admin);
    }
})->with(['sqlite', 'mysql', 'pgsql'])->with(['draft', 'client', 'staff', 'mail']);

test('client deletion queues an upload that acquired the lock first', function (string $driver): void {
    [$db, $params, $dir, $admin] = attachmentRaceDatabase($driver);

    try {
        $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
        $em->shouldReceive('getConnection')->andReturn($db);
        $di = container();
        $di['em'] = $em;
        $file = tempnam(sys_get_temp_dir(), 'fb-race-upload-');
        file_put_contents($file, 'race');
        $db->beginTransaction();
        $upload = TicketFiles::uploadForClient($di, 7, 60, new UploadedFile($file, 'race.txt', 'text/plain', null, true));
        [$process, $pipes] = attachmentRaceWorker($params, $dir, 'delete');
        $db->commit();
        expect(attachmentRaceResult($process, $pipes)['result'])->toBe('deleted');
        expect($db->fetchOne('SELECT removed_reason FROM support_ticket_file WHERE token = ?', [$upload['id']]))->toBe('client_deleted')
            ->and(is_file($dir . '/files/7/' . $upload['id']))->toBeTrue();
        TicketFiles::prune($di);
        expect(is_file($dir . '/files/7/' . $upload['id']))->toBeFalse();
    } finally {
        if ($db->isTransactionActive()) {
            $db->rollBack();
        }
        attachmentRaceCleanup($db, $params, $dir, $admin);
    }
})->with(['sqlite', 'mysql', 'pgsql']);

test('a rolled back client deletion lets the waiting upload proceed', function (string $driver): void {
    [$db, $params, $dir, $admin] = attachmentRaceDatabase($driver);

    try {
        $db->beginTransaction();
        expect(ClientRowLock::acquire($db, 7))->toBeTrue();
        TicketFiles::forgetClient($db, 7);
        [$process, $pipes] = attachmentRaceWorker($params, $dir, 'client');
        $db->rollBack();
        $result = attachmentRaceResult($process, $pipes);
        expect($result)->toHaveKey('result')
            ->and((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_file'))->toBe(1)
            ->and(is_file($dir . '/files/7/' . $result['result']['id']))->toBeTrue();
    } finally {
        if ($db->isTransactionActive()) {
            $db->rollBack();
        }
        attachmentRaceCleanup($db, $params, $dir, $admin);
    }
})->with(['sqlite', 'mysql', 'pgsql']);

test('attachment recovery waits for the upload transaction outcome', function (string $driver, bool $commit, bool $guest): void {
    [$db, $params, $dir, $admin] = attachmentRaceDatabase($driver);

    try {
        if ($guest) {
            $db->update('support_ticket', ['client_id' => null], ['id' => 60]);
        }
        $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
        $em->shouldReceive('getConnection')->andReturn($db);
        $di = container();
        $di['em'] = $em;
        $file = tempnam(sys_get_temp_dir(), 'fb-race-upload-');
        file_put_contents($file, 'race');
        $db->beginTransaction();
        $uploaded = $guest
            ? TicketFiles::uploadForStaff($di, 1, 60, new UploadedFile($file, 'race.txt', 'text/plain', null, true))
            : TicketFiles::uploadForClient($di, 7, 60, new UploadedFile($file, 'race.txt', 'text/plain', null, true));
        $path = $dir . '/files/' . ($guest ? 0 : 7) . '/' . $uploaded['id'];
        expect(is_file($path . '.pending'))->toBeTrue();
        [$process, $pipes] = attachmentRaceWorker($params, $dir, 'prune');
        expect(is_file($path))->toBeTrue();
        if ($commit) {
            $db->commit();
        } else {
            $db->rollBack();
        }
        expect(attachmentRaceResult($process, $pipes))->toHaveKey('result')
            ->and(is_file($path))->toBe($commit)
            ->and(is_file($path . '.pending'))->toBeFalse();
    } finally {
        if ($db->isTransactionActive()) {
            $db->rollBack();
        }
        attachmentRaceCleanup($db, $params, $dir, $admin);
    }
})->with(['sqlite', 'mysql', 'pgsql'])->with([false, true])->with([false, true]);

test('recovery waits for an uncommitted attachment owner', function (string $driver, bool $commit, bool $guest): void {
    [$db, $params, $dir, $admin] = attachmentRaceDatabase($driver);

    try {
        $db->delete('support_ticket_message', ['id' => 70]);
        $db->delete('support_ticket', ['id' => 60]);
        $db->delete('client', ['id' => 7]);
        $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
        $em->shouldReceive('getConnection')->andReturn($db);
        $di = container();
        $di['em'] = $em;
        $file = tempnam(sys_get_temp_dir(), 'fb-race-upload-');
        file_put_contents($file, 'race');
        $db->beginTransaction();
        if (!$guest) {
            $db->insert('client', ['id' => 7]);
        }
        $db->insert('support_ticket', ['id' => 60, 'client_id' => $guest ? null : 7]);
        $uploaded = $guest
            ? TicketFiles::uploadForStaff($di, 1, 60, new UploadedFile($file, 'race.txt', 'text/plain', null, true))
            : TicketFiles::uploadForClient($di, 7, 60, new UploadedFile($file, 'race.txt', 'text/plain', null, true));
        $path = $dir . '/files/' . ($guest ? 0 : 7) . '/' . $uploaded['id'];
        [$process, $pipes] = attachmentRaceWorker($params, $dir, 'prune');
        expect(is_file($path))->toBeTrue();
        if ($commit) {
            $db->commit();
        } else {
            $db->rollBack();
        }
        expect(attachmentRaceResult($process, $pipes))->toHaveKey('result')
            ->and(is_file($path))->toBe($commit)
            ->and(is_file($path . '.pending'))->toBeFalse();
    } finally {
        if ($db->isTransactionActive()) {
            $db->rollBack();
        }
        attachmentRaceCleanup($db, $params, $dir, $admin);
    }
})->with(['sqlite', 'mysql', 'pgsql'])->with([false, true])->with([false, true]);

test('legacy cleanup rechecks metadata after waiting for a client transaction', function (string $driver, bool $commit): void {
    [$db, $params, $dir, $admin] = attachmentRaceDatabase($driver);

    try {
        (new Symfony\Component\Filesystem\Filesystem())->mkdir($dir . '/files/7');
        $token = str_repeat('a', 32);
        $path = $dir . '/files/7/' . $token;
        file_put_contents($path, 'old attachment');
        touch($path, time() - (TicketFiles::LEGACY_ORPHAN_HOURS + 1) * 3600);
        $db->beginTransaction();
        expect(ClientRowLock::acquire($db, 7))->toBeTrue();
        $db->insert('support_ticket_file', [
            'token' => $token, 'client_id' => 7, 'uploader_kind' => 'client', 'uploader_id' => 7,
            'session_hash' => 'session', 'name' => 'old.txt', 'mime' => 'text/plain', 'size' => 14,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        [$process, $pipes] = attachmentRaceWorker($params, $dir, 'legacy_cleanup');
        expect(is_file($path))->toBeTrue();
        if ($commit) {
            $db->commit();
        } else {
            $db->rollBack();
        }
        expect(attachmentRaceResult($process, $pipes))->toHaveKey('result')
            ->and(is_file($path))->toBe($commit);
    } finally {
        if ($db->isTransactionActive()) {
            $db->rollBack();
        }
        attachmentRaceCleanup($db, $params, $dir, $admin);
    }
})->with(['sqlite', 'mysql', 'pgsql'])->with([false, true]);

test('retention sweep cannot delete an upload attached concurrently', function (string $driver, bool $commit): void {
    [$db, $params, $dir, $admin] = attachmentRaceDatabase($driver);

    try {
        $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
        $em->shouldReceive('getConnection')->andReturn($db);
        $di = container();
        $di['em'] = $em;
        $file = tempnam(sys_get_temp_dir(), 'fb-retention-upload-');
        file_put_contents($file, 'waiting');
        $upload = TicketFiles::uploadForClient($di, 7, 60, new UploadedFile($file, 'waiting.txt', 'text/plain', null, true));
        $path = $dir . '/files/7/' . $upload['id'];
        (new Symfony\Component\Filesystem\Filesystem())->remove($path . '.pending');
        $db->update('support_ticket_file', ['created_at' => date('Y-m-d H:i:s', time() - 2 * 86400)], ['token' => $upload['id']]);
        $db->beginTransaction();
        $db->update('support_ticket_file', ['message_id' => 70], ['token' => $upload['id']]);
        [$process, $pipes] = attachmentRaceWorker($params, $dir, 'prune');
        expect(is_file($path))->toBeTrue();
        if ($commit) {
            $db->commit();
        } else {
            $db->rollBack();
        }
        $result = attachmentRaceResult($process, $pipes);
        if (isset($result['error'])) {
            throw new RuntimeException($result['error']);
        }
        expect($result)->toHaveKey('result')
            ->and(is_file($path))->toBe($commit)
            ->and((bool) $db->fetchOne('SELECT id FROM support_ticket_file WHERE token = ?', [$upload['id']]))->toBe($commit);
    } finally {
        if ($db->isTransactionActive()) {
            $db->rollBack();
        }
        attachmentRaceCleanup($db, $params, $dir, $admin);
    }
})->with(['sqlite', 'mysql', 'pgsql'])->with([false, true]);
