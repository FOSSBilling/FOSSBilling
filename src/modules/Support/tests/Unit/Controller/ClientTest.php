<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\Controller\Client;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

test('client downloads their own ticket file', function (): void {
    $dir = sys_get_temp_dir() . '/fb-ticket-files-' . bin2hex(random_bytes(4));
    putenv('FOSSBILLING_TICKET_FILES_DIR=' . $dir);

    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE support_ticket_file (
        id INTEGER PRIMARY KEY AUTOINCREMENT, token CHAR(32) NOT NULL, ticket_id BIGINT DEFAULT NULL,
        message_id BIGINT DEFAULT NULL, client_id BIGINT NOT NULL, uploader_kind VARCHAR(10) NOT NULL,
        uploader_id BIGINT NOT NULL, session_hash CHAR(64) NOT NULL, name VARCHAR(191) NOT NULL,
        mime VARCHAR(100) NOT NULL, size INT NOT NULL, created_at DATETIME NOT NULL,
        attached_at DATETIME DEFAULT NULL, removed_at DATETIME DEFAULT NULL,
        removed_by BIGINT DEFAULT NULL, removed_reason VARCHAR(20) DEFAULT NULL,
        UNIQUE (token))');
    @mkdir($dir . '/7', 0o700, true);
    file_put_contents($dir . '/7/' . str_repeat('a', 32), 'hello');
    $connection->insert('support_ticket_file', [
        'token' => str_repeat('a', 32), 'ticket_id' => 60, 'message_id' => 70, 'client_id' => 7,
        'uploader_kind' => 'client', 'uploader_id' => 7, 'session_hash' => 'x',
        'name' => 'note.txt', 'mime' => 'text/plain', 'size' => 5,
        'created_at' => date('Y-m-d H:i:s'), 'attached_at' => date('Y-m-d H:i:s'),
    ]);

    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);

    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;
    $di['is_client_logged'] = true;
    $di['loggedin_client'] = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 7]);
    $di['response_factory'] = new FOSSBilling\Core\Http\ResponseFactory();

    $controller = new Client();
    $controller->setDi($di);

    $response = $controller->get_ticket_file($app, str_repeat('a', 32));
    expect($response)->toBeInstanceOf(Symfony\Component\HttpFoundation\BinaryFileResponse::class);
    expect($response->headers->get('Content-Type'))->toBe('text/plain; charset=utf-8');
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');

    // Another client's file is not found.
    expect(fn (): Symfony\Component\HttpFoundation\Response => $controller->get_ticket_file($app, str_repeat('b', 32)))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'File not found.');
});
