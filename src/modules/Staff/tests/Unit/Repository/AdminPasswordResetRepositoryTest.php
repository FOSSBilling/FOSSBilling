<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Box\Mod\Staff\Entity\Admin;
use Box\Mod\Staff\Entity\AdminPasswordReset;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\InformationException;
use Symfony\Component\Filesystem\Path;

function staffResetEntityManager(): EntityManagerInterface
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    (new SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [Admin::class, AdminPasswordReset::class]));

    return $em;
}

function staffResetRecord(EntityManagerInterface $em, Admin $admin, string $hash): AdminPasswordReset
{
    $reset = (new AdminPasswordReset())->setAdminId((int) $admin->getId())->setHash($hash);
    $em->persist($reset);
    $em->flush();

    return $reset;
}

test('every staff password change revokes all recovery capabilities for only that admin', function (): void {
    $em = staffResetEntityManager();
    $admin = (new Admin())->setPass('old-hash');
    $other = (new Admin())->setPass('other-hash');
    $em->persist($admin);
    $em->persist($other);
    $em->flush();
    staffResetRecord($em, $admin, 'old-token');
    staffResetRecord($em, $admin, 'sibling-token');
    staffResetRecord($em, $other, 'other-token');

    $repository = $em->getRepository(AdminPasswordReset::class);
    $repository->changePassword($admin, 'new-hash');

    expect($repository->findOneByHash('old-token'))->toBeNull()
        ->and($repository->findOneByHash('sibling-token'))->toBeNull()
        ->and($repository->findOneByHash('other-token'))->not->toBeNull();
    $em->refresh($admin);
    $em->refresh($other);
    expect($admin->getPass())->toBe('new-hash')->and($other->getPass())->toBe('other-hash');

    foreach (['old-token', 'sibling-token'] as $hash) {
        expect(fn () => $repository->changePassword($admin, 'attacker-hash', $hash))
            ->toThrow(InformationException::class, 'The link has expired');
    }

    // A failed transactional change closes its EntityManager; reopen over the
    // same connection (same in-memory database) for the remainder of the test.
    $adminId = $admin->getId();
    $em = new EntityManager($em->getConnection(), $em->getConfiguration());
    $admin = $em->find(Admin::class, $adminId);
    $repository = $em->getRepository(AdminPasswordReset::class);
    $repository->replaceReset($admin, 'fresh-token', '192.0.2.10');
    $repository->changePassword($admin, 'fresh-hash', 'fresh-token');
    expect($repository->findOneByHash('fresh-token'))->toBeNull()
        ->and($admin->getPass())->toBe('fresh-hash');
});

test('a preloaded reset cannot overwrite a changed password', function (): void {
    $em = staffResetEntityManager();
    $admin = (new Admin())->setPass('old-hash');
    $em->persist($admin);
    $em->flush();
    $stale = staffResetRecord($em, $admin, 'stale-token');
    $repository = $em->getRepository(AdminPasswordReset::class);
    $repository->changePassword($admin, 'new-hash');
    expect(fn () => $repository->changePassword($admin, 'attacker-hash', $stale->getHash()))
        ->toThrow(InformationException::class, 'The link has expired');
    expect($em->getConnection()->fetchOne('SELECT pass FROM admin WHERE id = ?', [$admin->getId()]))->toBe('new-hash');
});

test('replacement leaves only the latest recovery token and it can be redeemed', function (): void {
    $em = staffResetEntityManager();
    $admin = (new Admin())->setPass('old-hash');
    $em->persist($admin);
    $em->flush();
    $repository = $em->getRepository(AdminPasswordReset::class);
    expect($repository->replaceReset($admin, 'first-token', null))->toBeTrue();
    $stale = $repository->findOneByHash('first-token');
    expect($repository->replaceReset($admin, 'second-token', '192.0.2.10'))->toBeTrue();
    expect($stale)->toBeInstanceOf(AdminPasswordReset::class)
        ->and($repository->findOneByHash('first-token'))->toBeNull()
        ->and($repository->findOneByHash('second-token')->getIp())->toBe('192.0.2.10');
    $repository->changePassword($admin, 'new-hash', 'second-token');
    expect($admin->getPass())->toBe('new-hash');
    expect(fn () => $repository->changePassword($admin, 'attacker-hash', $stale->getHash()))
        ->toThrow(InformationException::class, 'The link has expired');
});

test('redemption rechecks expiry and eligibility against the database', function (string $condition): void {
    $em = staffResetEntityManager();
    $admin = (new Admin())->setPass('old-hash');
    $other = new Admin();
    $em->persist($admin);
    $em->persist($other);
    $em->flush();
    staffResetRecord($em, $admin, 'token');
    $connection = $em->getConnection();
    match ($condition) {
        'expired' => $connection->update('admin_password_reset', ['created_at' => date('Y-m-d H:i:s', time() - 901)], ['admin_id' => $admin->getId()]),
        'inactive' => $connection->update('admin', ['status' => Admin::STATUS_INACTIVE], ['id' => $admin->getId()]),
        'cron' => $connection->update('admin', ['system_name' => Admin::SYSTEM_CRON], ['id' => $admin->getId()]),
        'wrong-admin' => $connection->update('admin_password_reset', ['admin_id' => $other->getId()], ['hash' => 'token']),
    };
    expect(fn () => $em->getRepository(AdminPasswordReset::class)->changePassword($admin, 'attacker-hash', 'token'))
        ->toThrow(InformationException::class, 'The link has expired');
    expect($connection->fetchOne('SELECT pass FROM admin WHERE id = ?', [$admin->getId()]))->toBe('old-hash')
        ->and($connection->fetchOne('SELECT COUNT(*) FROM admin_password_reset'))->toBe(1);
})->with(['expired', 'inactive', 'cron', 'wrong-admin']);

test('replacement refuses ineligible admins without touching stored tokens', function (string $state): void {
    $em = staffResetEntityManager();
    $admin = (new Admin())->setPass('old-hash');
    $em->persist($admin);
    $em->flush();
    staffResetRecord($em, $admin, 'old-token');
    if ($state !== 'active') {
        $em->getConnection()->update('admin', $state === 'cron' ? ['system_name' => Admin::SYSTEM_CRON] : ['status' => Admin::STATUS_INACTIVE], ['id' => $admin->getId()]);
    }
    expect($em->getRepository(AdminPasswordReset::class)->replaceReset($admin, 'new-token', null))->toBe($state === 'active');
    expect($em->getRepository(AdminPasswordReset::class)->findOneByHash('old-token') === null)->toBe($state === 'active');
})->with(['active', 'inactive', 'cron']);

test('password and reset revocation roll back together when persistence fails', function (): void {
    $em = staffResetEntityManager();
    $admin = (new Admin())->setPass('old-hash');
    $em->persist($admin);
    $em->flush();
    staffResetRecord($em, $admin, 'token');
    $em->getEventManager()->addEventListener(['preFlush'], new class {
        public function preFlush(): never
        {
            throw new RuntimeException('flush failed');
        }
    });
    expect(fn () => $em->getRepository(AdminPasswordReset::class)->changePassword($admin, 'new-hash'))
        ->toThrow(RuntimeException::class, 'flush failed');
    expect($em->getConnection()->fetchOne('SELECT pass FROM admin WHERE id = ?', [$admin->getId()]))->toBe('old-hash')
        ->and($em->getConnection()->fetchOne('SELECT COUNT(*) FROM admin_password_reset'))->toBe(1);
});

test('credential writes acquire the same mutex before changing recovery state on every driver', function (string $platformClass, bool $issuance): void {
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $platform = new $platformClass();
    $connection->shouldReceive('getDatabasePlatform')->andReturn($platform);
    $sqlite = $platform instanceof Doctrine\DBAL\Platforms\SQLitePlatform;
    if ($sqlite) {
        $connection->shouldReceive('executeStatement')->once()->ordered()
            ->with('UPDATE admin SET id = id WHERE id = ?', [1])->andReturn(1);
    }
    $suffix = $sqlite ? '' : ' FOR UPDATE';
    $connection->shouldReceive('fetchAssociative')->once()->ordered()
        ->with('SELECT status, system_name FROM admin WHERE id = ?' . $suffix, [1])
        ->andReturn(['status' => Admin::STATUS_ACTIVE, 'system_name' => 'default']);
    if (!$issuance) {
        $connection->shouldReceive('fetchOne')->once()->ordered()
            ->with('SELECT created_at FROM admin_password_reset WHERE admin_id = ? AND hash = ?' . $suffix, [1, 'token'])
            ->andReturn(date('Y-m-d H:i:s'));
    }
    $connection->shouldReceive('delete')->once()->ordered()->with('admin_password_reset', ['admin_id' => 1])->andReturn(1);
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $em->shouldReceive('wrapInTransaction')->once()->andReturnUsing(static fn (callable $callback) => $callback());
    $em->shouldReceive('persist')->once();
    $repository = new Box\Mod\Staff\Repository\AdminPasswordResetRepository($em, new Doctrine\ORM\Mapping\ClassMetadata(AdminPasswordReset::class));
    $admin = new Admin();
    (new ReflectionProperty($admin, 'id'))->setValue($admin, 1);
    if ($issuance) {
        expect($repository->replaceReset($admin, 'token', null))->toBeTrue();
    } else {
        $repository->changePassword($admin, 'new-password', 'token');
        expect($admin->getPass())->toBe('new-password');
    }
})->with([
    Doctrine\DBAL\Platforms\SQLitePlatform::class,
    Doctrine\DBAL\Platforms\MySQLPlatform::class,
    Doctrine\DBAL\Platforms\PostgreSQLPlatform::class,
])->with([true, false]);
