<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Entity\ClientPasswordReset;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\InformationException;
use Symfony\Component\Filesystem\Path;

use function Tests\Helpers\container;

function passwordResetEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    (new SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [Client::class, ClientPasswordReset::class]));

    return $em;
}

function issueTestClientReset(EntityManager $em, Client $client, string $hash): ClientPasswordReset
{
    $reset = new ClientPasswordReset();
    $reset->setClient($client)->setHash($hash);
    $em->persist($reset);
    $em->flush();

    return $reset;
}

test('every password change revokes all old tokens and preserves another client', function (string $path): void {
    $em = passwordResetEntityManager();
    $client = (new Client())->setPass('old-password')->setStatus(Client::ACTIVE);
    $other = (new Client())->setPass('other-password')->setStatus(Client::ACTIVE);
    $em->persist($client);
    $em->persist($other);
    $em->flush();
    issueTestClientReset($em, $client, 'old-token');
    issueTestClientReset($em, $client, 'duplicate-token');
    issueTestClientReset($em, $other, 'other-token');

    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['password'] = Mockery::mock(FOSSBilling\PasswordManager::class);
    $di['validator'] = Mockery::mock(FOSSBilling\Validate::class)->shouldIgnoreMissing();
    $di['event_dispatcher'] = Mockery::mock(Symfony\Component\EventDispatcher\EventDispatcherInterface::class)->shouldIgnoreMissing();
    $profile = Mockery::mock(Box\Mod\Profile\Service::class)->shouldIgnoreMissing();
    $email = Mockery::mock(Box\Mod\Email\Service::class)->shouldIgnoreMissing();
    $staff = $di['mod_service']('staff');
    $di['mod_service'] = $di->protect(static fn (string $name): object => match (strtolower($name)) {
        'profile' => $profile,
        'email' => $email,
        default => $staff,
    });
    $di['password']->shouldReceive('hashIt')->with('NewPassword1!')->andReturn('new-password-hash');
    $data = ['id' => $client->getId(), 'hash' => 'old-token', 'password' => 'NewPassword1!', 'password_confirm' => 'NewPassword1!'];
    if ($path === 'profile') {
        $service = new Box\Mod\Profile\Service();
        $service->setDi($di);
        expect($service->changeClientPassword($client, $data['password']))->toBeTrue();
    } elseif ($path === 'admin') {
        $api = apiEndpoint(new Box\Mod\Client\Api\Admin());
        $api->setDi($di);
        expect($api->change_password($data))->toBeTrue();
    } else {
        $api = apiEndpoint(new Box\Mod\Client\Api\Guest());
        $api->setDi($di);
        expect($api->update_password($data))->toBeTrue();
    }

    $repository = $em->getRepository(ClientPasswordReset::class);
    expect($repository->findOneByHash('old-token'))->toBeNull()
        ->and($repository->findOneByHash('duplicate-token'))->toBeNull()
        ->and($repository->findOneByHash('other-token'))->not->toBeNull();
    $em->refresh($client);
    $em->refresh($other);
    expect($client->getPass())->toBe('new-password-hash')->and($other->getPass())->toBe('other-password');

    $guest = apiEndpoint(new Box\Mod\Client\Api\Guest());
    $guest->setDi($di);
    foreach (['old-token', 'duplicate-token'] as $token) {
        $data['hash'] = $token;
        expect(fn () => $guest->update_password($data))->toThrow(InformationException::class, 'The link has expired');
    }

    // A freshly issued recovery credential still works after a legitimate password change.
    $service = new Box\Mod\Client\Service();
    $service->setDi($di);
    $data['hash'] = $service->createPasswordResetRequestForClient($client);
    expect($data['hash'])->toMatch('/^[a-f0-9]{64}$/');
    expect($guest->update_password($data))->toBeTrue();
})->with(['profile', 'admin', 'reset']);

test('a reset loaded before a password change cannot overwrite the new password', function (): void {
    $em = passwordResetEntityManager();
    $client = (new Client())->setPass('old-password')->setStatus(Client::ACTIVE);
    $em->persist($client);
    $em->flush();
    $staleReset = issueTestClientReset($em, $client, 'stale-token');
    $repository = $em->getRepository(ClientPasswordReset::class);
    $repository->changePassword($client, 'new-password');

    expect(fn () => $repository->changePassword($client, 'attacker-password', $staleReset->getHash()))
        ->toThrow(InformationException::class, 'The link has expired');
    expect($em->getConnection()->fetchOne('SELECT pass FROM client WHERE id = ?', [$client->getId()]))->toBe('new-password');
});

test('password and recovery revocation roll back together if flushing fails', function (): void {
    $em = passwordResetEntityManager();
    $client = (new Client())->setPass('old-password');
    $em->persist($client);
    $em->flush();
    issueTestClientReset($em, $client, 'old-token');
    $em->getEventManager()->addEventListener(['preFlush'], new class {
        public function preFlush(): never
        {
            throw new RuntimeException('forced flush failure');
        }
    });

    expect(fn () => $em->getRepository(ClientPasswordReset::class)->changePassword($client, 'new-password'))
        ->toThrow(RuntimeException::class, 'forced flush failure');
    $connection = $em->getConnection();
    expect($connection->fetchOne('SELECT pass FROM client WHERE id = ?', [$client->getId()]))->toBe('old-password')
        ->and($connection->fetchOne('SELECT hash FROM client_password_reset WHERE client_id = ?', [$client->getId()]))->toBe('old-token');
});

test('locked reset revalidation rejects unsafe recovery state', function (string $state): void {
    $em = passwordResetEntityManager();
    $client = (new Client())->setPass('old-password')->setStatus(Client::ACTIVE);
    $other = (new Client())->setStatus(Client::ACTIVE);
    $em->persist($client);
    $em->persist($other);
    $em->flush();
    issueTestClientReset($em, $state === 'wrong client' ? $other : $client, 'token');
    if ($state === 'expired') {
        $em->getConnection()->executeStatement('UPDATE client_password_reset SET created_at = ?', [(new DateTimeImmutable('-16 minutes'))->format('Y-m-d H:i:s')]);
    } elseif ($state === 'inactive') {
        // Change the database only, leaving a previously loaded active entity stale.
        $em->getConnection()->executeStatement('UPDATE client SET status = ? WHERE id = ?', ['suspended', $client->getId()]);
    }

    expect(fn () => $em->getRepository(ClientPasswordReset::class)->changePassword($client, 'attacker-password', 'token'))
        ->toThrow(InformationException::class, 'The link has expired');
    expect($em->getConnection()->fetchOne('SELECT pass FROM client WHERE id = ?', [$client->getId()]))->toBe('old-password');
})->with(['expired', 'inactive', 'wrong client']);

test('reset issuance replaces every old credential for only its client', function (): void {
    $em = passwordResetEntityManager();
    $client = new Client();
    $other = new Client();
    $em->persist($client);
    $em->persist($other);
    $em->flush();
    issueTestClientReset($em, $client, 'old-token');
    issueTestClientReset($em, $client, 'duplicate-token');
    issueTestClientReset($em, $other, 'other-token');

    $repository = $em->getRepository(ClientPasswordReset::class);
    $repository->createRequest($client, 'new-token', '192.0.2.1');
    expect($repository->findOneByHash('old-token'))->toBeNull()
        ->and($repository->findOneByHash('duplicate-token'))->toBeNull()
        ->and($repository->findOneByHash('new-token')?->getIp())->toBe('192.0.2.1')
        ->and($repository->findOneByHash('other-token'))->not->toBeNull();
});

test('credential writes acquire the same mutex before changing recovery state on every driver', function (string $platformClass, bool $issuance): void {
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $platform = new $platformClass();
    $connection->shouldReceive('getDatabasePlatform')->andReturn($platform);
    $sqlite = $platform instanceof Doctrine\DBAL\Platforms\SQLitePlatform;
    if ($sqlite) {
        $connection->shouldReceive('executeStatement')->once()->ordered()
            ->with('UPDATE client SET updated_at = updated_at WHERE id = :id', ['id' => 1])->andReturn(1);
    }
    $suffix = $sqlite ? '' : ' FOR UPDATE';
    $connection->shouldReceive('fetchOne')->once()->ordered()
        ->with('SELECT status FROM client WHERE id = :id' . $suffix, ['id' => 1])->andReturn(Client::ACTIVE);
    if (!$issuance) {
        $connection->shouldReceive('fetchAssociative')->once()->ordered()
            ->with('SELECT created_at FROM client_password_reset WHERE client_id = :id AND hash = :hash' . $suffix, ['id' => 1, 'hash' => 'token'])
            ->andReturn(['created_at' => date('Y-m-d H:i:s')]);
    }
    $connection->shouldReceive('delete')->once()->ordered()->with('client_password_reset', ['client_id' => 1])->andReturn(1);
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $em->shouldReceive('wrapInTransaction')->once()->andReturnUsing(static fn (callable $callback) => $callback());
    $em->shouldReceive('persist')->once()->with(Mockery::type($issuance ? ClientPasswordReset::class : Client::class));
    $repository = new Box\Mod\Client\Repository\ClientPasswordResetRepository($em, new Doctrine\ORM\Mapping\ClassMetadata(ClientPasswordReset::class));
    $client = new Client();
    (new ReflectionProperty($client, 'id'))->setValue($client, 1);
    if ($issuance) {
        $repository->createRequest($client, 'token', null);
    } else {
        $repository->changePassword($client, 'new-password', 'token');
        expect($client->getPass())->toBe('new-password');
    }
})->with([
    Doctrine\DBAL\Platforms\SQLitePlatform::class,
    Doctrine\DBAL\Platforms\MySQLPlatform::class,
    Doctrine\DBAL\Platforms\PostgreSQLPlatform::class,
])->with([true, false]);
