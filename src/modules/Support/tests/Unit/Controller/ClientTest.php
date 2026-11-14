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

test('feedback page renders the form for an open link', function (): void {
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE admin (id INTEGER PRIMARY KEY, name VARCHAR(255), status VARCHAR(30), system_name VARCHAR(100) DEFAULT NULL)');
    $connection->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, subject VARCHAR(255), status VARCHAR(30), client_id INTEGER, created_at DATETIME)');
    $connection->executeStatement('CREATE TABLE support_ticket_message (id INTEGER PRIMARY KEY, support_ticket_id INTEGER, admin_id INTEGER, created_at DATETIME)');
    $connection->executeStatement('CREATE TABLE support_ticket_feedback_request (
        ticket_id BIGINT NOT NULL, token CHAR(64) NOT NULL, sent_at DATETIME NOT NULL,
        answered_at DATETIME DEFAULT NULL, PRIMARY KEY (ticket_id))');
    $connection->executeStatement('CREATE TABLE support_ticket_feedback (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ticket_id BIGINT NOT NULL, admin_id BIGINT DEFAULT NULL,
        rating SMALLINT DEFAULT NULL, comments TEXT NOT NULL, ip VARCHAR(45) NOT NULL DEFAULT \'\',
        created_at DATETIME NOT NULL)');
    $connection->insert('admin', ['id' => 3, 'name' => 'Agent', 'status' => 'active', 'system_name' => null]);
    $connection->insert('support_ticket', ['id' => 60, 'subject' => 'H', 'status' => 'closed', 'client_id' => 1, 'created_at' => '2026-10-01 10:00:00']);
    $connection->insert('support_ticket_message', ['id' => 70, 'support_ticket_id' => 60, 'admin_id' => 3, 'created_at' => '2026-10-03 13:00:00']);
    $token = str_repeat('a', 64);
    $connection->insert('support_ticket_feedback_request', ['ticket_id' => 60, 'token' => $token, 'sent_at' => date('Y-m-d H:i:s'), 'answered_at' => null]);

    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('render')->once()->with('mod_support_feedback', Mockery::on(function (array $params) use ($token): bool {
        return $params['feedback_token'] === $token && $params['feedback']['state'] === 'form' && $params['feedback']['staff'] === [3 => 'Agent'];
    }))->andReturn('feedback-html');

    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;

    $controller = new Client();
    $controller->setDi($di);

    expect($controller->get_feedback($app, $token))->toBe('feedback-html');
});

test('feedback page degrades on a bad link', function (): void {
    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('render')->once()->with('mod_support_feedback', ['feedback' => ['ticket' => [], 'staff' => [], 'state' => 'invalid'], 'feedback_token' => 'nope'])->andReturn('invalid-html');

    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andThrow(new RuntimeException('no such table'));
    $di['em'] = $em;

    $controller = new Client();
    $controller->setDi($di);

    expect($controller->get_feedback($app, 'nope'))->toBe('invalid-html');
});
