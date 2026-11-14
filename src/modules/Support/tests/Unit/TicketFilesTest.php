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
    $path = tempnam(sys_get_temp_dir(), 'tftest');
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
    $zipPath = tempnam(sys_get_temp_dir(), 'tftest') . '.zip';
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
    $connection->insert('support_ticket', ['id' => 60, 'client_id' => 0, 'status' => 'open']);
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
