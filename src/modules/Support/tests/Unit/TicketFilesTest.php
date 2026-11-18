<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\TicketFiles;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Tests\Helpers\container;

function ticketFilesConnection(): array
{
    $dir = sys_get_temp_dir() . '/fb-ticket-files-' . bin2hex(random_bytes(4));
    putenv('FOSSBILLING_TICKET_FILES_DIR=' . $dir);

    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE client (id INTEGER PRIMARY KEY)');
    $connection->insert('client', ['id' => 7]);
    $connection->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, client_id INTEGER, status VARCHAR(30))');
    $connection->executeStatement('CREATE TABLE support_ticket_message (id INTEGER PRIMARY KEY, support_ticket_id INTEGER, admin_id INTEGER, client_id INTEGER)');
    $connection->executeStatement('CREATE TABLE support_ticket_file (
        id INTEGER PRIMARY KEY AUTOINCREMENT, token CHAR(32) NOT NULL, ticket_id BIGINT DEFAULT NULL,
        message_id BIGINT DEFAULT NULL, client_id BIGINT NOT NULL, uploader_kind VARCHAR(10) NOT NULL,
        uploader_id BIGINT NOT NULL, session_hash CHAR(64) NOT NULL, name VARCHAR(191) NOT NULL,
        mime VARCHAR(100) NOT NULL, size INT NOT NULL, created_at DATETIME NOT NULL,
        attached_at DATETIME DEFAULT NULL, removed_at DATETIME DEFAULT NULL,
        removed_by BIGINT DEFAULT NULL, removed_reason VARCHAR(20) DEFAULT NULL,
        UNIQUE (token))');

    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;
    $di['request'] = Symfony\Component\HttpFoundation\Request::create('http://localhost/');

    return [$di, $connection, $dir];
}

function ticketFilesUpload(Symfony\Component\HttpFoundation\Request $request, string $name, string $content, string $mime): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'ticket-file-test');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, $mime, null, true);
}

test('ticket file rules describe the upload limits', function (): void {
    $rules = TicketFiles::rules();

    expect($rules['max_files'])->toBe(5);
    expect($rules['max_bytes'])->toBe(10 * 1024 * 1024);
    expect($rules['extensions'])->toContain('pdf');
    expect($rules['extensions'])->not->toContain('svg');
    expect($rules['extensions'])->not->toContain('html');
});

test('ticket file type checks match content, not just the name', function (): void {
    expect(TicketFiles::typeFits('png', 'image/png'))->toBeTrue();
    expect(TicketFiles::typeFits('png', 'text/html'))->toBeFalse();
    expect(TicketFiles::typeFits('log', 'text/plain'))->toBeTrue();
    expect(TicketFiles::typeFits('csv', 'application/x-ndjson'))->toBeTrue();
    expect(TicketFiles::typeFits('svg', 'image/svg+xml'))->toBeFalse();
    expect(TicketFiles::typeFits('exe', 'application/octet-stream'))->toBeFalse();
});

test('ticket file names are sanitized for storage', function (): void {
    expect(TicketFiles::cleanName('C:\\Users\\me\\report Q1.pdf'))->toBe('report Q1.pdf');
    expect(TicketFiles::cleanName('../../etc/passwd'))->toBe('passwd');
    expect(TicketFiles::cleanName('a<b>c?.txt'))->toBe('a_b_c_.txt');
    expect(TicketFiles::cleanName('...'))->toBe('file');
    expect(TicketFiles::sizeLabel(500))->toBe('1 KB');
    expect(TicketFiles::sizeLabel(2048))->toBe('2 KB');
    expect(TicketFiles::sizeLabel(3 * 1024 * 1024))->toBe('3 MB');
});

test('ticket file upload validates type and size', function (): void {
    [$di, $connection] = ticketFilesConnection();
    $connection->insert('support_ticket', ['id' => 60, 'client_id' => 7, 'status' => 'open']);

    // A script is refused even with an allowed name.
    $evil = ticketFilesUpload($di['request'], 'photo.png', '<html>hi', 'text/html');
    expect(fn (): array => TicketFiles::uploadForClient($di, 7, 60, $evil))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'type');

    // Another client's ticket is not found.
    $ok = ticketFilesUpload($di['request'], 'note.txt', 'hello', 'text/plain');
    expect(fn (): array => TicketFiles::uploadForClient($di, 8, 60, $ok))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Ticket not found');
});

test('ticket file upload, attach, find and serve round-trip', function (): void {
    [$di, $connection, $dir] = ticketFilesConnection();
    $connection->insert('support_ticket', ['id' => 60, 'client_id' => 7, 'status' => 'open']);
    $connection->insert('support_ticket_message', ['id' => 70, 'support_ticket_id' => 60, 'admin_id' => null, 'client_id' => 7]);

    $uploaded = TicketFiles::uploadForClient($di, 7, 60, ticketFilesUpload($di['request'], 'note.txt', 'hello files', 'text/plain'));
    expect($uploaded['name'])->toBe('note.txt');
    expect(is_file($dir . '/7/' . $uploaded['id']))->toBeTrue();

    // Waiting files are not servable yet.
    expect(TicketFiles::find($di, $uploaded['id']))->toBeNull();

    // The reply save binds the waiting upload.
    $di['request']->request->set('ticket_files', [$uploaded['id']]);
    expect(TicketFiles::attachFromRequest($di, 60, false))->toBe(1);
    $found = TicketFiles::find($di, $uploaded['id']);
    expect($found['name'] ?? null)->toBe('note.txt');

    $served = TicketFiles::serve($found);
    expect($served['disposition'])->toBe('inline');
    expect($served['content_type'])->toBe('text/plain; charset=utf-8');

    // A zip downloads instead of showing inline.
    $zipPath = tempnam(sys_get_temp_dir(), 'ticket-file-test') . '.zip';
    $zipArchive = new ZipArchive();
    $zipArchive->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zipArchive->addFromString('a.txt', str_repeat('x', 100));
    $zipArchive->close();
    $zip = new UploadedFile($zipPath, 'a.zip', 'application/zip', null, true);
    $zip = TicketFiles::uploadForClient($di, 7, 60, $zip);
    $di['request']->request->set('ticket_files', [$zip['id']]);
    TicketFiles::attachFromRequest($di, 60, false);
    expect(TicketFiles::serve(TicketFiles::find($di, $zip['id']))['disposition'])->toBe('attachment');

    // Images show inline with their own type.
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    $img = TicketFiles::uploadForClient($di, 7, 60, ticketFilesUpload($di['request'], 'shot.png', $png, 'image/png'));
    $di['request']->request->set('ticket_files', [$img['id']]);
    TicketFiles::attachFromRequest($di, 60, false);
    $servedImg = TicketFiles::serve(TicketFiles::find($di, $img['id']));
    expect($servedImg['disposition'])->toBe('inline');
    expect($servedImg['content_type'])->toBe('image/png');
});

test('ticket file discard removes only waiting uploads', function (): void {
    [$di, $connection, $dir] = ticketFilesConnection();
    $connection->insert('support_ticket', ['id' => 60, 'client_id' => 7, 'status' => 'open']);

    $uploaded = TicketFiles::uploadForClient($di, 7, null, ticketFilesUpload($di['request'], 'draft.txt', 'draft', 'text/plain'));
    TicketFiles::discard($di, $uploaded['id'], 'client', 7);
    expect(is_file($dir . '/7/' . $uploaded['id']))->toBeFalse();

    // Discarding twice, or another client's file, is not found.
    expect(TicketFiles::attachFromRequest($di, -1, false))->toBe(0);
    expect(TicketFiles::attachFromRequest($di, 0, false))->toBe(0);
});

test('ticket file attach never fails the message save', function (): void {
    [$di, $connection] = ticketFilesConnection();
    $connection->insert('support_ticket', ['id' => 60, 'client_id' => 7, 'status' => 'open']);
    $connection->insert('support_ticket_message', ['id' => 70, 'support_ticket_id' => 60, 'admin_id' => null, 'client_id' => 7]);

    // Unknown tokens are skipped silently.
    $di['request']->request->set('ticket_files', ['nope']);
    expect(TicketFiles::attachFromRequest($di, 60, false))->toBe(0);

    // Staff cannot take the client's waiting upload (and vice versa).
    $uploaded = TicketFiles::uploadForClient($di, 7, 60, ticketFilesUpload($di['request'], 'note.txt', 'hello', 'text/plain'));
    $di['request']->request->set('ticket_files', [$uploaded['id']]);
    expect(TicketFiles::attachFromRequest($di, 60, true))->toBe(0);
    expect(TicketFiles::attachFromRequest($di, 60, false))->toBe(1);
});

test('ticket file remove keeps a staff marker', function (): void {
    [$di, $connection, $dir] = ticketFilesConnection();
    $connection->insert('support_ticket', ['id' => 60, 'client_id' => 7, 'status' => 'open']);
    $connection->insert('support_ticket_message', ['id' => 70, 'support_ticket_id' => 60, 'admin_id' => null, 'client_id' => 7]);

    $uploaded = TicketFiles::uploadForClient($di, 7, 60, ticketFilesUpload($di['request'], 'note.txt', 'hello', 'text/plain'));
    $di['request']->request->set('ticket_files', [$uploaded['id']]);
    TicketFiles::attachFromRequest($di, 60, false);

    TicketFiles::remove($di, $uploaded['id'], 1);
    expect(TicketFiles::find($di, $uploaded['id']))->toBeNull();
    expect(is_file($dir . '/7/' . $uploaded['id']))->toBeFalse();

    $row = $connection->fetchAssociative('SELECT removed_reason, removed_by FROM support_ticket_file WHERE token = ?', [$uploaded['id']]);
    expect($row['removed_reason'])->toBe('staff');
    expect((int) $row['removed_by'])->toBe(1);

    expect(TicketFiles::attachFromRequest($di, 60, false))->toBe(0);
    expect(fn () => TicketFiles::remove($di, $uploaded['id'], 1))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'File not found');
});

test('ticket file prune expires old attachments and drops orphans', function (): void {
    [$di, $connection, $dir] = ticketFilesConnection();
    $connection->insert('support_ticket', ['id' => 60, 'client_id' => 7, 'status' => 'open']);
    $connection->insert('support_ticket_message', ['id' => 70, 'support_ticket_id' => 60, 'admin_id' => null, 'client_id' => 7]);
    $old = date('Y-m-d H:i:s', time() - 200 * 86400);

    // Attached long ago: soft-expired, file unlinked.
    $uploaded = TicketFiles::uploadForClient($di, 7, 60, ticketFilesUpload($di['request'], 'old.txt', 'old', 'text/plain'));
    $connection->update('support_ticket_file', ['created_at' => $old, 'message_id' => 70, 'attached_at' => $old], ['token' => $uploaded['id']]);

    // Uploaded but never sent: hard-deleted after a day.
    $waiting = TicketFiles::uploadForClient($di, 7, null, ticketFilesUpload($di['request'], 'new.txt', 'new', 'text/plain'));
    $connection->update('support_ticket_file', ['created_at' => $old], ['token' => $waiting['id']]);

    $di['mod_config'] = $di->protect(fn (): array => []);
    expect(TicketFiles::prune($di))->toBe(2);

    $row = $connection->fetchAssociative('SELECT removed_reason FROM support_ticket_file WHERE token = ?', [$uploaded['id']]);
    expect($row['removed_reason'])->toBe('expired');
    expect($connection->fetchOne('SELECT 1 FROM support_ticket_file WHERE token = ?', [$waiting['id']]))->toBeFalse();

    // A zero keep period never expires attachments.
    expect(TicketFiles::prune($di))->toBe(0);
});

test('ticket mail attachments store and skip by type', function (): void {
    [$di, $connection, $dir] = ticketFilesConnection();
    $connection->insert('support_ticket', ['id' => 60, 'client_id' => null, 'status' => 'open']);
    $connection->insert('support_ticket_message', ['id' => 70, 'support_ticket_id' => 60, 'admin_id' => null, 'client_id' => null]);

    $skipped = TicketFiles::attachFromMail($di, 0, 60, 70, [
        ['name' => 'log.txt', 'mime' => 'text/plain', 'content' => 'log lines'],
        ['name' => 'run.exe', 'mime' => 'application/octet-stream', 'content' => 'binary'],
        ['name' => 'empty.txt', 'mime' => 'text/plain', 'content' => ''],
    ]);
    expect($skipped)->toBe(['run.exe', 'empty.txt']);

    $row = $connection->fetchAssociative('SELECT * FROM support_ticket_file WHERE ticket_id = 60');
    expect($row['uploader_kind'])->toBe('mail');
    expect((int) $row['message_id'])->toBe(70);
    expect(is_file($dir . '/0/' . $row['token']))->toBeTrue();
});

test('ticket files annotate messages and vanish without the table', function (): void {
    [$di, $connection] = ticketFilesConnection();
    $connection->insert('support_ticket', ['id' => 60, 'client_id' => 7, 'status' => 'open']);
    $connection->insert('support_ticket_message', ['id' => 70, 'support_ticket_id' => 60, 'admin_id' => null, 'client_id' => 7]);

    $uploaded = TicketFiles::uploadForClient($di, 7, 60, ticketFilesUpload($di['request'], 'note.txt', 'hello', 'text/plain'));
    $di['request']->request->set('ticket_files', [$uploaded['id']]);
    TicketFiles::attachFromRequest($di, 60, false);

    $ticket = TicketFiles::withFiles($di, ['id' => 60, 'messages' => [['id' => 70], ['id' => 71]]]);
    expect($ticket['messages'][0]['ticket_files'][0]['id'])->toBe($uploaded['id']);
    expect($ticket['messages'][0]['ticket_files'][0]['image'])->toBeFalse();
    expect($ticket['messages'][1]['ticket_files'])->toBe([]);

    $bare = container();
    $bareEm = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $bareEm->shouldReceive('getConnection')->andReturn(Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
    $bare['em'] = $bareEm;
    expect(TicketFiles::withFiles($bare, ['id' => 60, 'messages' => [['id' => 70]]]))->toBe(['id' => 60, 'messages' => [['id' => 70]]]);
});

test('reply attachments follow the requested message even when a newer reply exists', function (): void {
    [$di, $connection] = ticketFilesConnection();
    $connection->insert('support_ticket', ['id' => 60, 'client_id' => 7, 'status' => 'open']);
    $connection->insert('support_ticket_message', ['id' => 70, 'support_ticket_id' => 60, 'admin_id' => null, 'client_id' => 7]);
    $connection->insert('support_ticket_message', ['id' => 71, 'support_ticket_id' => 60, 'admin_id' => null, 'client_id' => 7]);
    $uploaded = TicketFiles::uploadForClient($di, 7, 60, ticketFilesUpload($di['request'], 'note.txt', 'hello', 'text/plain'));
    $di['request']->request->set('ticket_files', [$uploaded['id']]);

    expect(TicketFiles::attachFromRequest($di, 60, false, 70))->toBe(1)
        ->and((int) $connection->fetchOne('SELECT message_id FROM support_ticket_file WHERE token = ?', [$uploaded['id']]))->toBe(70);
});

test('a reply attachment cannot target a message belonging to another ticket', function (): void {
    [$di, $connection] = ticketFilesConnection();
    $connection->insert('support_ticket', ['id' => 60, 'client_id' => 7, 'status' => 'open']);
    $connection->insert('support_ticket', ['id' => 61, 'client_id' => 7, 'status' => 'open']);
    $connection->insert('support_ticket_message', ['id' => 71, 'support_ticket_id' => 61, 'admin_id' => null, 'client_id' => 7]);
    $uploaded = TicketFiles::uploadForClient($di, 7, 60, ticketFilesUpload($di['request'], 'note.txt', 'hello', 'text/plain'));
    $di['request']->request->set('ticket_files', [$uploaded['id']]);

    expect(TicketFiles::attachFromRequest($di, 60, false, 71))->toBe(0)
        ->and($connection->fetchOne('SELECT message_id FROM support_ticket_file WHERE token = ?', [$uploaded['id']]))->toBeNull();
});

test('client file cleanup waits for commit and survives a rollback', function (): void {
    [$di, $db, $dir] = ticketFilesConnection();
    $uploaded = TicketFiles::uploadForClient($di, 7, null, ticketFilesUpload($di['request'], 'private.txt', 'private', 'text/plain'));
    $path = $dir . '/7/' . $uploaded['id'];
    $db->beginTransaction();
    TicketFiles::forgetClient($db, 7);
    expect(is_file($path))->toBeTrue();
    $db->rollBack();
    expect($db->fetchOne('SELECT name FROM support_ticket_file WHERE token = ?', [$uploaded['id']]))->toBe('private.txt')
        ->and(is_file($path))->toBeTrue();
    $db->beginTransaction();
    TicketFiles::forgetClient($db, 7);
    $db->commit();
    expect($db->fetchOne('SELECT name FROM support_ticket_file WHERE token = ?', [$uploaded['id']]))->toBe('')
        ->and(is_file($path))->toBeTrue();
    TicketFiles::prune($di);
    expect(is_file($path))->toBeFalse()
        ->and($db->fetchOne('SELECT token FROM support_ticket_file WHERE token = ?', [$uploaded['id']]))->toBeFalse();
});

test('invalid cleanup paths stay queued instead of being removed', function (): void {
    [$di, $db] = ticketFilesConnection();
    $db->insert('support_ticket_file', [
        'token' => '../outside', 'client_id' => 7, 'uploader_kind' => '', 'uploader_id' => 0,
        'session_hash' => '', 'name' => '', 'mime' => '', 'size' => 0,
        'created_at' => date('Y-m-d H:i:s'), 'removed_at' => date('Y-m-d H:i:s'), 'removed_reason' => 'client_deleted',
    ]);
    TicketFiles::prune($di);
    expect($db->fetchOne('SELECT token FROM support_ticket_file WHERE token = ?', ['../outside']))->toBe('../outside');
});

test('staff can attach files to a guest ticket', function (): void {
    [$di, $db, $dir] = ticketFilesConnection();
    $db->insert('support_ticket', ['id' => 60, 'client_id' => null, 'status' => 'open']);
    $file = TicketFiles::uploadForStaff($di, 1, 60, ticketFilesUpload($di['request'], 'guest.txt', 'guest', 'text/plain'));
    expect(is_file($dir . '/0/' . $file['id']))->toBeTrue();
});

test('attachment recovery follows the enclosing transaction outcome', function (string $kind, bool $commit): void {
    [$di, $db, $dir] = ticketFilesConnection();
    $db->insert('support_ticket', ['id' => 60, 'client_id' => 7, 'status' => 'open']);
    $db->insert('support_ticket_message', ['id' => 70, 'support_ticket_id' => 60, 'admin_id' => null, 'client_id' => 7]);
    $db->beginTransaction();
    if ($kind === 'mail') {
        expect(TicketFiles::attachFromMail($di, 7, 60, 70, [['name' => 'file.txt', 'content' => 'hello']]))->toBe([]);
        $token = $db->fetchOne('SELECT token FROM support_ticket_file');
    } else {
        $file = ticketFilesUpload($di['request'], 'file.txt', 'hello', 'text/plain');
        $result = $kind === 'staff' ? TicketFiles::uploadForStaff($di, 1, 60, $file) : TicketFiles::uploadForClient($di, 7, 60, $file);
        $token = $result['id'];
    }
    $path = $dir . '/7/' . $token;
    expect(is_file($path))->toBeTrue()->and(is_file($path . '.pending'))->toBeTrue();
    if ($commit) {
        $db->commit();
    } else {
        $db->rollBack();
    }
    TicketFiles::prune($di);
    expect(is_file($path))->toBe($commit)
        ->and(is_file($path . '.pending'))->toBeFalse()
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_file'))->toBe($commit ? 1 : 0);
})->with(['client', 'staff', 'mail'])->with([false, true]);

test('legacy cleanup removes only old unmarked files without metadata', function (): void {
    [$di, $db, $dir] = ticketFilesConnection();
    $filesystem = new Symfony\Component\Filesystem\Filesystem();
    $filesystem->mkdir([$dir . '/7', $dir . '/0', $dir . '/bad']);
    $old = time() - (TicketFiles::LEGACY_ORPHAN_HOURS + 2) * 3600;
    $orphan = str_repeat('a', 32);
    $guest = str_repeat('b', 32);
    $kept = str_repeat('c', 32);
    $recent = str_repeat('d', 32);
    $staged = str_repeat('e', 32);
    foreach (["7/$orphan", "0/$guest", "7/$kept", "7/$recent", "7/$staged", 'bad/' . str_repeat('f', 32), '7/not-a-token'] as $path) {
        file_put_contents($dir . '/' . $path, 'private');
        if ($path !== "7/$recent") {
            touch($dir . '/' . $path, $old);
        }
    }
    file_put_contents($dir . '/7/' . $staged . '.pending', 'invalid');
    $db->insert('support_ticket_file', [
        'token' => $kept, 'client_id' => 7, 'uploader_kind' => 'client', 'uploader_id' => 7,
        'session_hash' => 'session', 'name' => 'saved.txt', 'mime' => 'text/plain', 'size' => 7,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    TicketFiles::cleanupLegacyInventory($di, TicketFiles::legacyOrphanInventory($di), true);
    expect(is_file($dir . '/7/' . $orphan))->toBeFalse()
        ->and(is_file($dir . '/0/' . $guest))->toBeFalse()
        ->and(is_file($dir . '/7/' . $kept))->toBeTrue()
        ->and(is_file($dir . '/7/' . $recent))->toBeTrue()
        ->and(is_file($dir . '/7/' . $staged))->toBeTrue()
        ->and(is_file($dir . '/bad/' . str_repeat('f', 32)))->toBeTrue()
        ->and(is_file($dir . '/7/not-a-token'))->toBeTrue();
});

test('legacy cleanup skips files while an outer transaction is active', function (): void {
    [$di, $db, $dir] = ticketFilesConnection();
    (new Symfony\Component\Filesystem\Filesystem())->mkdir($dir . '/7');
    $path = $dir . '/7/' . str_repeat('a', 32);
    file_put_contents($path, 'private');
    touch($path, time() - (TicketFiles::LEGACY_ORPHAN_HOURS + 1) * 3600);
    $db->beginTransaction();
    expect(fn () => TicketFiles::cleanupLegacyInventory($di, TicketFiles::legacyOrphanInventory($di), true))->toThrow(LogicException::class);
    expect(is_file($path))->toBeTrue();
    $db->rollBack();
    TicketFiles::cleanupLegacyInventory($di, TicketFiles::legacyOrphanInventory($di), true);
    expect(is_file($path))->toBeFalse();
});

test('legacy cleanup processes orphaned files in bounded batches', function (): void {
    [$di, $db, $dir] = ticketFilesConnection();
    (new Symfony\Component\Filesystem\Filesystem())->mkdir($dir . '/7');
    for ($i = 0; $i < 501; ++$i) {
        $path = $dir . '/7/' . str_pad(dechex($i), 32, '0', STR_PAD_LEFT);
        file_put_contents($path, 'x');
        touch($path, time() - (TicketFiles::LEGACY_ORPHAN_HOURS + 1) * 3600);
    }
    TicketFiles::cleanupLegacyInventory($di, TicketFiles::legacyOrphanInventory($di), true);
    $files = (new Symfony\Component\Finder\Finder())->files()->in($dir . '/7');
    expect(iterator_count($files))->toBe(1);
    TicketFiles::cleanupLegacyInventory($di, TicketFiles::legacyOrphanInventory($di), true);
    $files = (new Symfony\Component\Finder\Finder())->files()->in($dir . '/7');
    expect(iterator_count($files))->toBe(0);
});

test('legacy cleanup does not follow attachment symlinks', function (): void {
    [$di, $db, $dir] = ticketFilesConnection();
    (new Symfony\Component\Filesystem\Filesystem())->mkdir($dir . '/7');
    $target = $dir . '/outside.txt';
    $link = $dir . '/7/' . str_repeat('a', 32);
    file_put_contents($target, 'keep');
    touch($target, time() - (TicketFiles::LEGACY_ORPHAN_HOURS + 1) * 3600);
    symlink($target, $link);
    TicketFiles::cleanupLegacyInventory($di, TicketFiles::legacyOrphanInventory($di), true);
    expect(is_link($link))->toBeTrue()->and(file_get_contents($target))->toBe('keep');
});

test('automatic cleanup never deletes old unmarked in-flight uploads', function (int $clientId): void {
    [$di, $db, $dir] = ticketFilesConnection();
    (new Symfony\Component\Filesystem\Filesystem())->mkdir($dir . '/' . $clientId);
    $token = str_repeat('a', 32);
    $path = $dir . '/' . $clientId . '/' . $token;
    file_put_contents($path, 'legacy upload still running');
    touch($path, time() - (TicketFiles::LEGACY_ORPHAN_HOURS + 48) * 3600);
    TicketFiles::prune($di);
    expect(is_file($path))->toBeTrue();
    expect(fn () => TicketFiles::cleanupLegacyInventory($di, TicketFiles::legacyOrphanInventory($di), false))->toThrow(InvalidArgumentException::class);
    expect(is_file($path))->toBeTrue();
    $db->insert('support_ticket_file', [
        'token' => $token, 'client_id' => $clientId, 'uploader_kind' => 'client', 'uploader_id' => $clientId,
        'session_hash' => 'session', 'name' => 'legacy.txt', 'mime' => 'text/plain', 'size' => 7,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    expect(TicketFiles::cleanupLegacyInventory($di, TicketFiles::legacyOrphanInventory($di), true))->toBe(0)->and(is_file($path))->toBeTrue();
})->with([0, 7]);

test('legacy cleanup command requires an explicit drained-writers confirmation', function (): void {
    [$di, $db, $dir] = ticketFilesConnection();
    (new Symfony\Component\Filesystem\Filesystem())->mkdir($dir . '/7');
    $path = $dir . '/7/' . str_repeat('a', 32);
    file_put_contents($path, 'orphan');
    touch($path, time() - (TicketFiles::LEGACY_ORPHAN_HOURS + 1) * 3600);
    $command = new Box\Mod\Support\Commands\CleanupLegacyFiles();
    $command->setDi($di);
    $tester = new Symfony\Component\Console\Tester\CommandTester($command);
    expect($tester->execute([]))->toBe(Symfony\Component\Console\Command\Command::INVALID)
        ->and($tester->getDisplay())->toContain('--legacy-uploads-drained')
        ->and(is_file($path))->toBeTrue();
    expect($tester->execute(['--inventory' => true]))->toBe(0);
    $manifest = $dir . '/reviewed.json';
    file_put_contents($manifest, $tester->getDisplay());
    expect($tester->execute(['--legacy-uploads-drained' => true, '--manifest' => $manifest]))->toBe(Symfony\Component\Console\Command\Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('Removed 1 legacy orphan')
        ->and(is_file($path))->toBeFalse();
});

test('legacy cleanup reports database failure without deleting the candidate', function (): void {
    [$di, $db, $dir] = ticketFilesConnection();
    (new Symfony\Component\Filesystem\Filesystem())->mkdir($dir . '/7');
    $path = $dir . '/7/' . str_repeat('a', 32);
    file_put_contents($path, 'orphan');
    touch($path, time() - (TicketFiles::LEGACY_ORPHAN_HOURS + 1) * 3600);
    $manifest = $dir . '/reviewed.json';
    file_put_contents($manifest, json_encode(TicketFiles::legacyOrphanInventory($di)));
    $db->executeStatement('DROP TABLE support_ticket_file');
    $command = new Box\Mod\Support\Commands\CleanupLegacyFiles();
    $command->setDi($di);
    $tester = new Symfony\Component\Console\Tester\CommandTester($command);
    expect(fn () => $tester->execute(['--legacy-uploads-drained' => true, '--manifest' => $manifest]))
        ->toThrow(Doctrine\DBAL\Exception\DriverException::class);
    expect(is_file($path))->toBeTrue();
});

test('retention cleanup waits for its outer transaction and retries after commit', function (bool $commit): void {
    [$di, $db, $dir] = ticketFilesConnection();
    $upload = TicketFiles::uploadForClient($di, 7, null, ticketFilesUpload($di['request'], 'waiting.txt', 'waiting', 'text/plain'));
    $path = $dir . '/7/' . $upload['id'];
    $db->update('support_ticket_file', ['created_at' => date('Y-m-d H:i:s', time() - 2 * 86400)], ['token' => $upload['id']]);
    $db->beginTransaction();
    expect(TicketFiles::prune($di))->toBe(1)->and(is_file($path))->toBeTrue();
    if ($commit) {
        $db->commit();
        expect($db->fetchOne('SELECT removed_reason FROM support_ticket_file WHERE token = ?', [$upload['id']]))->toBe('orphan_cleanup');
        TicketFiles::prune($di);
        expect(is_file($path))->toBeFalse()->and($db->fetchOne('SELECT id FROM support_ticket_file WHERE token = ?', [$upload['id']]))->toBeFalse();
    } else {
        $db->rollBack();
        expect(is_file($path))->toBeTrue()
            ->and($db->fetchOne('SELECT removed_reason FROM support_ticket_file WHERE token = ?', [$upload['id']]))->toBeNull();
    }
})->with([false, true]);

test('invalid retention cleanup paths remain queued for retry', function (): void {
    [$di, $db] = ticketFilesConnection();
    $db->insert('support_ticket_file', [
        'token' => '../invalid', 'client_id' => 7, 'uploader_kind' => 'client', 'uploader_id' => 7,
        'session_hash' => '', 'name' => '', 'mime' => '', 'size' => 0,
        'created_at' => date('Y-m-d H:i:s'), 'removed_at' => date('Y-m-d H:i:s'), 'removed_reason' => 'orphan_cleanup',
    ]);
    TicketFiles::prune($di);
    expect($db->fetchOne('SELECT removed_reason FROM support_ticket_file WHERE token = ?', ['../invalid']))->toBe('orphan_cleanup');
});

test('discard and staff removal wait for the enclosing transaction', function (string $action, bool $commit): void {
    [$di, $db, $dir] = ticketFilesConnection();
    $db->insert('support_ticket', ['id' => 60, 'client_id' => 7, 'status' => 'open']);
    $upload = TicketFiles::uploadForClient($di, 7, 60, ticketFilesUpload($di['request'], 'file.txt', 'data', 'text/plain'));
    if ($action === 'staff') {
        $db->update('support_ticket_file', ['message_id' => 70], ['token' => $upload['id']]);
        $db->insert('support_ticket_message', ['id' => 70, 'support_ticket_id' => 60, 'client_id' => 7]);
    }
    $path = $dir . '/7/' . $upload['id'];
    $db->beginTransaction();
    if ($action === 'staff') {
        TicketFiles::remove($di, $upload['id'], 1);
    } else {
        TicketFiles::discard($di, $upload['id'], 'client', 7);
    }
    expect(is_file($path))->toBeTrue();
    if ($commit) {
        $db->commit();
        TicketFiles::prune($di);
        expect(is_file($path))->toBeFalse();
    } else {
        $db->rollBack();
        expect($db->fetchOne('SELECT removed_at FROM support_ticket_file WHERE token = ?', [$upload['id']]))->toBeNull()
            ->and(is_file($path))->toBeTrue();
    }
})->with(['discard', 'staff'])->with([false, true]);

test('reviewed legacy inventory skips changed files and rechecks metadata and markers', function (string $change): void {
    [$di, $db, $dir] = ticketFilesConnection();
    (new Symfony\Component\Filesystem\Filesystem())->mkdir($dir . '/7');
    $token = str_repeat('b', 32);
    $path = $dir . '/7/' . $token;
    file_put_contents($path, 'before');
    $mtime = time() - 2 * 86400;
    touch($path, $mtime);
    $inventory = TicketFiles::legacyOrphanInventory($di);
    expect(count($inventory['files']))->toBe(1)->and(is_file($path))->toBeTrue();
    if ($change === 'content') {
        file_put_contents($path, 'after!'); // same size and timestamp: requires content fingerprint
        touch($path, $mtime);
    } elseif ($change === 'marker') {
        file_put_contents($path . '.pending', '0');
    } elseif ($change === 'metadata') {
        $db->insert('support_ticket_file', [
            'token' => $token, 'client_id' => 7, 'uploader_kind' => 'client', 'uploader_id' => 7,
            'session_hash' => '', 'name' => 'file.txt', 'mime' => 'text/plain', 'size' => 6, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    } else {
        unlink($path);
        symlink(__FILE__, $path);
    }
    expect(TicketFiles::cleanupLegacyInventory($di, $inventory, true))->toBe(0)
        ->and(is_file($path))->toBeTrue();
})->with(['content', 'marker', 'metadata', 'symlink']);

test('reviewed legacy cleanup requires drain and rejects invalid paths before deletion', function (): void {
    [$di, $db, $dir] = ticketFilesConnection();
    expect(fn () => TicketFiles::cleanupLegacyInventory($di, ['version' => 1, 'files' => []], false))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TicketFiles::cleanupLegacyInventory($di, ['version' => 1, 'files' => [['client_id' => 7, 'token' => '../secret']]], true))->toThrow(InvalidArgumentException::class);
});

function guestAttachmentContext(): array
{
    [$di, $db, $dir] = ticketFilesConnection();
    $db->executeStatement('ALTER TABLE support_ticket ADD access_hash VARCHAR(255)');
    $hash = str_repeat('c', 40);
    $db->insert('support_ticket', ['id' => 60, 'client_id' => null, 'status' => 'open', 'access_hash' => $hash]);
    $db->insert('support_ticket', ['id' => 61, 'client_id' => null, 'status' => 'open', 'access_hash' => str_repeat('d', 40)]);
    $db->insert('support_ticket', ['id' => 62, 'client_id' => 7, 'status' => 'open', 'access_hash' => str_repeat('e', 40)]);
    $db->insert('support_ticket_message', ['id' => 70, 'support_ticket_id' => 60, 'admin_id' => 3]);
    $db->insert('support_ticket_message', ['id' => 71, 'support_ticket_id' => 61, 'admin_id' => 3]);
    $uploaded = TicketFiles::uploadForStaff($di, 3, 60, ticketFilesUpload($di['request'], 'reply.txt', 'staff reply contents', 'text/plain'));
    expect(TicketFiles::attach($di, 60, true, [$uploaded['id']], 70))->toBe(1);
    $di['is_client_logged'] = static fn () => throw new RuntimeException('login required');
    $di['response_factory'] = new FOSSBilling\Core\Http\ResponseFactory();

    return [$di, $db, $dir, $hash, $uploaded];
}

test('staff reply attachments are listed and downloadable after guest hash authorization', function (): void {
    [$di, $db, $dir, $hash, $uploaded] = guestAttachmentContext();
    $ticket = Tests\Helpers\createEntity(Box\Mod\Support\Entity\SupportTicket::class, ['id' => 60, 'client_id' => null, 'access_hash' => $hash]);
    $service = Mockery::mock(Box\Mod\Support\Service::class);
    $service->shouldReceive('findOneByHash')->once()->with($hash)->andReturn($ticket);
    $service->shouldReceive('toApiArray')->once()->with($ticket)->andReturn(['id' => 60, 'hash' => $hash, 'author' => ['role' => 'guest'], 'messages' => [['id' => 70]]]);
    $api = new Box\Mod\Support\Api\Guest();
    $api->setService($service);
    $api->setDi($di);
    $controller = new Box\Mod\Support\Controller\Client();
    $controller->setDi($di);

    try {
        $data = $api->ticket_get(['hash' => $hash]);
        expect($data['messages'][0])->toHaveKey('ticket_files');
        expect($data['messages'][0]['ticket_files'][0]['id'])->toBe($uploaded['id'])
            ->and($data['messages'][0]['ticket_files'][0]['name'])->toBe('reply.txt');
        $response = $controller->get_guest_ticket_file(Mockery::mock(FOSSBilling\Core\Http\App::class), $hash, $uploaded['id']);
        expect($response)->toBeInstanceOf(Symfony\Component\HttpFoundation\BinaryFileResponse::class)
            ->and((new Symfony\Component\Filesystem\Filesystem())->readFile($response->getFile()->getPathname()))->toBe('staff reply contents')
            ->and($response->headers->get('Content-Type'))->toBe('text/plain; charset=utf-8')
            ->and($response->headers->get('Cache-Control'))->toContain('no-store')
            ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
            ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox')
            ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer');
    } finally {
        $db->close();
        (new Symfony\Component\Filesystem\Filesystem())->remove($dir);
        putenv('FOSSBILLING_TICKET_FILES_DIR');
    }
});

test('guest attachment downloads reject unrelated removed and unbound files', function (string $scenario): void {
    [$di, $db, $dir, $hash, $uploaded] = guestAttachmentContext();
    $token = $uploaded['id'];
    switch ($scenario) {
        case 'wrong hash': $hash = str_repeat('f', 40);

            break;
        case 'another guest hash': $hash = str_repeat('d', 40);

            break;
        case 'empty hash': $hash = '';

            break;
        case 'unknown token': $token = str_repeat('a', 32);

            break;
        case 'invalid token': $token = '../file';

            break;
        case 'removed': $db->update('support_ticket_file', ['removed_at' => date('Y-m-d H:i:s'), 'removed_reason' => 'staff'], ['token' => $token]);

            break;
        case 'expired': $db->update('support_ticket_file', ['removed_at' => date('Y-m-d H:i:s'), 'removed_reason' => 'expired'], ['token' => $token]);

            break;
        case 'unattached': $db->update('support_ticket_file', ['message_id' => null], ['token' => $token]);

            break;
        case 'wrong message': $db->update('support_ticket_file', ['message_id' => 71], ['token' => $token]);

            break;
        case 'client ticket':
            $hash = str_repeat('e', 40);
            $db->update('support_ticket_file', ['ticket_id' => 62], ['token' => $token]);
            $db->update('support_ticket_message', ['support_ticket_id' => 62], ['id' => 70]);

            break;
        case 'client-owned file':
            (new Symfony\Component\Filesystem\Filesystem())->mkdir($dir . '/7');
            (new Symfony\Component\Filesystem\Filesystem())->rename($dir . '/0/' . $token, $dir . '/7/' . $token);
            $db->update('support_ticket_file', ['client_id' => 7], ['token' => $token]);

            break;
        case 'missing file': (new Symfony\Component\Filesystem\Filesystem())->remove($dir . '/0/' . $token);

            break;
        case 'deleted ticket': $db->delete('support_ticket', ['id' => 60]);

            break;
    }
    $controller = new Box\Mod\Support\Controller\Client();
    $controller->setDi($di);

    try {
        expect(fn () => $controller->get_guest_ticket_file(Mockery::mock(FOSSBilling\Core\Http\App::class), $hash, $token))
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'File not found.');
    } finally {
        $db->close();
        (new Symfony\Component\Filesystem\Filesystem())->remove($dir);
        putenv('FOSSBILLING_TICKET_FILES_DIR');
    }
})->with(['wrong hash', 'another guest hash', 'empty hash', 'unknown token', 'invalid token', 'removed', 'expired', 'unattached', 'wrong message', 'client ticket', 'client-owned file', 'missing file', 'deleted ticket']);

test('guest attachment metadata requires successful ticket hash lookup', function (): void {
    $service = Mockery::mock(Box\Mod\Support\Service::class);
    $service->shouldReceive('findOneByHash')->once()->with('wrong')->andThrow(new FOSSBilling\Core\Exception\BaseException('Guest ticket not found'));
    $service->shouldNotReceive('toApiArray');
    $api = new Box\Mod\Support\Api\Guest();
    $api->setService($service);
    $api->setDi(container());

    expect(fn () => $api->ticket_get(['hash' => 'wrong']))->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'Guest ticket not found');
});

test('ticket attachment links select guest hash authorization or client authentication', function (string $role, string $expected): void {
    $twig = new Twig\Environment(new Twig\Loader\FilesystemLoader(PATH_MODS . '/Support/templates/client'), ['strict_variables' => true, 'autoescape' => 'html']);
    $twig->addFilter(new Twig\TwigFilter('url', static fn (string $path): string => '/' . $path));
    $twig->addFilter(new Twig\TwigFilter('trans', static fn (string $text, array $parameters = []): string => strtr($text, $parameters)));
    $file = ['id' => str_repeat('a', 32), 'name' => '<reply>.txt', 'removed' => false, 'size_label' => '1 KB'];
    $html = $twig->render('mod_support_ticket_files.html.twig', ['ticket' => ['author' => ['role' => $role], 'hash' => str_repeat('c', 40)], 'message' => ['ticket_files' => [$file]]]);

    $document = new DOMDocument();
    // cspell:ignore NOERROR NOWARNING
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $href = (new DOMXPath($document))->evaluate('string(//a/@href)');

    expect($href)->toBe($expected)
        ->and($html)->toContain('&lt;reply&gt;.txt');
})->with([
    'guest' => ['guest', '/support/ticket/' . str_repeat('c', 40) . '/file/' . str_repeat('a', 32)],
    'client' => ['client', '/support/ticket-file/' . str_repeat('a', 32)],
]);
