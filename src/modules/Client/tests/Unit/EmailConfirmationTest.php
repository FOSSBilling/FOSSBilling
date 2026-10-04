<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Service;
use Doctrine\DBAL\DriverManager;
use FOSSBilling\InformationException;
use FOSSBilling\Tools;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

beforeEach(function (): void {
    $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $this->connection->executeStatement('CREATE TABLE client (id INTEGER PRIMARY KEY, email TEXT, email_approved BOOLEAN DEFAULT false)');
    $this->connection->executeStatement('CREATE TABLE extension_meta (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER, extension TEXT, meta_key TEXT, meta_value TEXT, rel_type TEXT, rel_id TEXT, created_at TEXT, updated_at TEXT)');
    $this->client = createEntity(Client::class, ['email' => 'owner@example.com']);
    $this->connection->insert('client', ['id' => 1, 'email' => $this->client->getEmail(), 'email_approved' => 0]);
    $this->di = container();
    $this->di['dbal'] = $this->connection;
    $this->di['em']->getRepository(Client::class)->shouldReceive('find')->with(1)->andReturn($this->client);
    $tools = Mockery::mock(Tools::class);
    $tools->shouldReceive('generatePassword')->andReturnUsing(fn (): string => bin2hex(random_bytes(25)));
    $tools->shouldReceive('url')->andReturnUsing(fn (string $path): string => $path);
    $this->di['tools'] = $tools;
    $this->service = new Service();
    $this->service->setDi($this->di);
    $this->issue = fn (): string => basename($this->service->generateEmailConfirmationLink(1));
});

test('confirmation approves the issued address and cannot be replayed', function (): void {
    $hash = ($this->issue)();
    expect($this->service->approveClientEmailByHash($hash))->toBeTrue();
    expect((bool) $this->connection->fetchOne('SELECT email_approved FROM client WHERE id = 1'))->toBeTrue();
    expect(fn () => $this->service->approveClientEmailByHash($hash))->toThrow(InformationException::class, 'Invalid email confirmation link');
});

test('an old confirmation cannot approve a replacement address', function (): void {
    $hash = ($this->issue)();
    $this->connection->update('client', ['email' => 'victim@example.com'], ['id' => 1]);
    expect(fn () => $this->service->approveClientEmailByHash($hash))->toThrow(InformationException::class);
    expect((bool) $this->connection->fetchOne('SELECT email_approved FROM client WHERE id = 1'))->toBeFalse();
    expect($this->connection->getTransactionNestingLevel())->toBe(0);
});

test('confirmation rejects unknown legacy expired and malformed bindings', function (string $kind): void {
    $hash = ($this->issue)();
    $changes = match ($kind) {
        'unknown' => ['meta_value' => 'another-token'],
        'legacy' => ['rel_type' => null, 'rel_id' => null],
        'expired' => ['created_at' => date('Y-m-d H:i:s', time() - 86401)],
        'malformed' => ['rel_id' => 'invalid-binding'],
    };
    $this->connection->update('extension_meta', $changes, ['client_id' => 1]);
    expect(fn () => $this->service->approveClientEmailByHash($hash))->toThrow(InformationException::class);
    expect((bool) $this->connection->fetchOne('SELECT email_approved FROM client WHERE id = 1'))->toBeFalse();
})->with(['unknown', 'legacy', 'expired', 'malformed']);

test('resending replaces older links and retains a valid current link', function (): void {
    $old = ($this->issue)();
    $new = ($this->issue)();
    expect(fn () => $this->service->approveClientEmailByHash($old))->toThrow(InformationException::class);
    expect($this->service->approveClientEmailByHash($new))->toBeTrue();
});

test('issuance binds to the managed email before a profile flush', function (): void {
    $old = ($this->issue)();
    $this->client->setEmail('new@example.com');
    $new = ($this->issue)();
    expect(fn () => $this->service->approveClientEmailByHash($new))->toThrow(InformationException::class);
    $this->connection->update('client', ['email' => $this->client->getEmail()], ['id' => 1]);
    expect(fn () => $this->service->approveClientEmailByHash($old))->toThrow(InformationException::class);
    expect($this->service->approveClientEmailByHash($new))->toBeTrue();
});

test('revocation prevents restoring an old address from reviving its link', function (): void {
    $hash = ($this->issue)();
    $this->service->revokeEmailConfirmations(1);
    $this->connection->update('client', ['email' => 'new@example.com'], ['id' => 1]);
    $this->connection->update('client', ['email' => 'owner@example.com'], ['id' => 1]);
    expect(fn () => $this->service->approveClientEmailByHash($hash))->toThrow(InformationException::class);
});

test('approval consumes sibling tokens and leaves other clients and metadata intact', function (): void {
    $hash = ($this->issue)();
    $row = $this->connection->fetchAssociative('SELECT * FROM extension_meta');
    unset($row['id']);
    $row['meta_value'] = 'sibling-token';
    $this->connection->insert('extension_meta', $row);
    $row['client_id'] = 2;
    $this->connection->insert('extension_meta', $row);
    $row['client_id'] = 1;
    $row['meta_key'] = 'unrelated';
    $this->connection->insert('extension_meta', $row);
    expect($this->service->approveClientEmailByHash($hash))->toBeTrue();
    expect(fn () => $this->service->approveClientEmailByHash('sibling-token'))->toThrow(InformationException::class);
    expect((int) $this->connection->fetchOne('SELECT COUNT(*) FROM extension_meta'))->toBe(2);
});

test('confirmation does not accept database collation aliases for the bound email', function (): void {
    $hash = ($this->issue)();
    $this->connection->update('client', ['email' => 'OWNER@example.com'], ['id' => 1]);
    expect(fn () => $this->service->approveClientEmailByHash($hash))->toThrow(InformationException::class);
});

test('profile email changes reset a concurrently approved database row despite stale managed state', function (bool $confirmationRequired): void {
    $connection = $this->connection;
    $connection->executeStatement('DROP TABLE client');
    $config = Doctrine\ORM\ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 2) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $em = new Doctrine\ORM\EntityManager($connection, $config);
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
    $client = new Client();
    $client->setEmail('owner@example.com');
    $client->setEmailApproved(false);
    $em->persist($client);
    $em->flush();
    $di = container();
    $di['em'] = $em;
    $di['dbal'] = $connection;
    $tools = Mockery::mock(Tools::class);
    $tools->shouldReceive('generatePassword')->andReturnUsing(fn (): string => bin2hex(random_bytes(25)));
    $tools->shouldReceive('url')->andReturnUsing(fn (string $path): string => $path);
    $tools->shouldReceive('validateAndSanitizeEmail')->andReturn('victim@example.com');
    $di['tools'] = $tools;
    $module = Mockery::mock(FOSSBilling\Module::class);
    $module->shouldReceive('getConfig')->andReturn(['disable_change_email' => false]);
    $di['mod'] = $di->protect(fn () => $module);
    $di['mod_config'] = $di->protect(fn () => ['require_email_confirmation' => $confirmationRequired]);
    $service = new Service();
    $service->setDi($di);
    $emailService = Mockery::mock(Box\Mod\Email\Service::class);
    $newHash = null;
    if ($confirmationRequired) {
        $emailService->shouldReceive('sendTemplate')->once()->andReturnUsing(function (array $data) use (&$newHash, $connection): void {
            expect($connection->isTransactionActive())->toBeTrue();
            $newHash = basename($data['email_confirmation_link']);
        });
    }
    $di['mod_service'] = $di->protect(fn (string $name) => $name === 'client' ? $service : $emailService);
    $oldHash = basename($service->generateEmailConfirmationLink($client->getId()));
    // A confirmation in another request changed only the database flag.
    $connection->executeStatement('UPDATE client SET email_approved = true WHERE id = :id', ['id' => $client->getId()]);
    expect($client->getEmailApproved())->toBeFalse();
    $profile = new Box\Mod\Profile\Service();
    $profile->setDi($di);
    expect($profile->updateClient($client, ['email' => 'victim@example.com']))->toBeTrue();
    expect($connection->fetchOne('SELECT email FROM client WHERE id = :id', ['id' => $client->getId()]))->toBe('victim@example.com');
    expect((bool) $connection->fetchOne('SELECT email_approved FROM client WHERE id = :id', ['id' => $client->getId()]))->toBeFalse();
    expect(fn () => $service->approveClientEmailByHash($oldHash))->toThrow(InformationException::class);
    if ($confirmationRequired) {
        expect($service->approveClientEmailByHash($newHash))->toBeTrue();
    }
})->with([false, true]);

test('failed approval rolls back token consumption', function (): void {
    $hash = ($this->issue)();
    $this->connection->executeStatement("CREATE TRIGGER reject_approval BEFORE UPDATE OF email_approved ON client WHEN NEW.email_approved = true BEGIN SELECT RAISE(ABORT, 'approval failed'); END");
    expect(fn () => $this->service->approveClientEmailByHash($hash))->toThrow(Doctrine\DBAL\Exception\DriverException::class);
    expect((int) $this->connection->fetchOne('SELECT COUNT(*) FROM extension_meta'))->toBe(1);
    expect((bool) $this->connection->fetchOne('SELECT email_approved FROM client WHERE id = 1'))->toBeFalse();
    $this->connection->executeStatement('DROP TRIGGER reject_approval');
    expect($this->service->approveClientEmailByHash($hash))->toBeTrue();
});
