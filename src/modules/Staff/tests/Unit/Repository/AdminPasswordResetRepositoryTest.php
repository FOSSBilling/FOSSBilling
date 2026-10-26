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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Doctrine\EntityManagerFactory;
use FOSSBilling\InformationException;

function staffResetEntityManager(): EntityManagerInterface
{
    $em = EntityManagerFactory::create(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
    (new SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [Admin::class, AdminPasswordReset::class]));

    return $em;
}

function staffResetRecord(EntityManagerInterface $em, Admin $admin, string $hash): AdminPasswordReset
{
    $reset = (new AdminPasswordReset())->setAdmin($admin)->setHash($hash);
    $em->persist($reset);
    $em->flush();

    return $reset;
}

function staffResetContainer(EntityManagerInterface $em): Pimple\Container
{
    $di = Tests\Helpers\container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['password'] = Mockery::mock(FOSSBilling\PasswordManager::class);
    $di['password']->shouldReceive('hashIt')->with('NewPassword1!')->andReturn('new-hash');
    $di['event_dispatcher'] = new Symfony\Component\EventDispatcher\EventDispatcher();
    $di['rate_limiter'] = Mockery::mock(FOSSBilling\Security\RateLimiter::class)->shouldIgnoreMissing();
    $profile = Mockery::mock(Box\Mod\Profile\Service::class);
    $profile->shouldReceive('invalidateSessions')->with('admin', Mockery::type('int'))->andReturn(true);
    $email = Mockery::mock(Box\Mod\Email\Service::class)->shouldIgnoreMissing();
    $di['mod_service'] = $di->protect(static fn (string $name): object => $name === 'profile' ? $profile : $email);

    return $di;
}

function staffResetGuest(Pimple\Container $di): Box\Mod\Staff\Api\Guest
{
    $guest = new Box\Mod\Staff\Api\Guest();
    $guest->setDi($di);
    $mod = Mockery::mock(FOSSBilling\Module::class);
    $mod->shouldReceive('getConfig')->andReturn([]);
    $guest->setMod($mod);
    $guest->setIp('192.0.2.10');

    return $guest;
}

function staffRedeem(Box\Mod\Staff\Api\Guest $guest, string $hash): void
{
    $guest->update_password(['code' => $hash, 'password' => 'NewPassword1!', 'password_confirm' => 'NewPassword1!']);
}

test('every staff password change revokes all recovery capabilities for only that admin', function (string $path): void {
    $em = staffResetEntityManager();
    $admin = (new Admin())->setPass('old-hash');
    $other = (new Admin())->setPass('other-hash');
    $em->persist($admin);
    $em->persist($other);
    $em->flush();
    staffResetRecord($em, $admin, 'old-token');
    staffResetRecord($em, $admin, 'sibling-token');
    staffResetRecord($em, $other, 'other-token');
    $di = staffResetContainer($em);
    $guest = staffResetGuest($di);
    if ($path === 'reset') {
        staffRedeem($guest, 'old-token');
    } elseif ($path === 'profile') {
        $service = new Box\Mod\Profile\Service();
        $service->setDi($di);
        expect($service->changeAdminPassword($admin, 'NewPassword1!'))->toBeTrue();
    } else {
        $service = Mockery::mock(Box\Mod\Staff\Service::class)->makePartial();
        $service->shouldReceive('hasPermission')->andReturn(true);
        $di['loggedin_admin'] = Tests\Helpers\admin(['id' => 999, 'system_name' => Admin::SYSTEM_CRON]);
        $service->setDi($di);
        expect($service->changePassword($admin, 'NewPassword1!'))->toBeTrue();
    }

    $repository = $em->getRepository(AdminPasswordReset::class);
    expect($repository->findOneByHash('old-token'))->toBeNull()
        ->and($repository->findOneByHash('sibling-token'))->toBeNull()
        ->and($repository->findOneByHash('other-token'))->not->toBeNull();
    $em->refresh($admin);
    $em->refresh($other);
    expect($admin->getPass())->toBe('new-hash')->and($other->getPass())->toBe('other-hash');
    foreach (['old-token', 'sibling-token'] as $hash) {
        expect(fn () => staffRedeem($guest, $hash))->toThrow(InformationException::class, 'The link has expired');
    }
    $repository->replaceReset($admin, 'fresh-token', '192.0.2.10');
    staffRedeem($guest, 'fresh-token');
    expect($repository->findOneByHash('fresh-token'))->toBeNull();
})->with(['profile', 'staff', 'reset']);

test('replacement leaves only the latest recovery token and it can be redeemed', function (): void {
    $em = staffResetEntityManager();
    $admin = (new Admin())->setPass('old-hash');
    $em->persist($admin);
    $em->flush();
    $repository = $em->getRepository(AdminPasswordReset::class);
    $repository->replaceReset($admin, 'first-token', null);
    $stale = $repository->findOneByHash('first-token');
    $repository->replaceReset($admin, 'second-token', '192.0.2.10');
    expect($stale)->toBeInstanceOf(AdminPasswordReset::class)
        ->and($repository->findOneByHash('first-token'))->toBeNull()
        ->and($repository->findOneByHash('second-token')->getIp())->toBe('192.0.2.10');
    staffRedeem(staffResetGuest(staffResetContainer($em)), 'second-token');
    expect($admin->getPass())->toBe('new-hash');
    expect(fn () => $repository->changePassword($admin, 'attacker-hash', $stale->getHash()))
        ->toThrow(InformationException::class, 'The link has expired');
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

test('completion side effect failure cannot preserve a reset capability', function (): void {
    $em = staffResetEntityManager();
    $admin = (new Admin())->setPass('old-hash');
    $em->persist($admin);
    $em->flush();
    staffResetRecord($em, $admin, 'token');
    staffResetRecord($em, $admin, 'sibling');
    $di = staffResetContainer($em);
    $di['event_dispatcher']->addListener(Box\Mod\Staff\Event\AfterStaffPasswordResetEvent::class, static function (): never {
        throw new RuntimeException('listener failed');
    });
    $guest = staffResetGuest($di);
    expect(fn () => staffRedeem($guest, 'token'))->toThrow(RuntimeException::class, 'listener failed');
    expect(fn () => staffRedeem($guest, 'sibling'))->toThrow(InformationException::class, 'The link has expired');
    expect($em->getConnection()->fetchOne('SELECT pass FROM admin WHERE id = ?', [$admin->getId()]))->toBe('new-hash')
        ->and($em->getConnection()->fetchOne('SELECT COUNT(*) FROM admin_password_reset'))->toBe(0);
});

test('concurrent recovery cannot cross a password change or token replacement', function (string $operation): void {
    $filesystem = new Symfony\Component\Filesystem\Filesystem();
    $path = $filesystem->tempnam(sys_get_temp_dir(), 'staff-reset-');
    $firstConnection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
    $secondConnection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
    $secondConnection->executeStatement('PRAGMA busy_timeout = 1');
    $first = EntityManagerFactory::create($firstConnection);
    $second = EntityManagerFactory::create($secondConnection);

    try {
        (new SchemaTool($first))->createSchema(array_map($first->getClassMetadata(...), [Admin::class, AdminPasswordReset::class]));
        $admin = (new Admin())->setPass('old-hash');
        $first->persist($admin);
        $first->flush();
        staffResetRecord($first, $admin, 'stale-token');
        $stale = $second->getRepository(AdminPasswordReset::class)->findOneByHash('stale-token');
        $firstConnection->beginTransaction();
        $repository = $first->getRepository(AdminPasswordReset::class);
        if ($operation === 'change') {
            $repository->changePassword($admin, 'new-hash');
        } else {
            $repository->replaceReset($admin, 'fresh-token', null);
        }

        // The competing connection loaded the old token before the first writer acquired its lock.
        expect(fn () => $second->getRepository(AdminPasswordReset::class)->changePassword($stale->getAdmin(), 'attacker-hash', 'stale-token'))
            ->toThrow(Doctrine\DBAL\Exception\LockWaitTimeoutException::class);
        $firstConnection->commit();
        // Failed ORM transactions close their EntityManager; a new request receives a fresh one.
        $second = EntityManagerFactory::create($secondConnection);
        $current = $second->find(Admin::class, $admin->getId());
        expect(fn () => $second->getRepository(AdminPasswordReset::class)->changePassword($current, 'attacker-hash', 'stale-token'))
            ->toThrow(InformationException::class, 'The link has expired');
        expect($firstConnection->fetchOne('SELECT pass FROM admin WHERE id = ?', [$admin->getId()]))
            ->toBe($operation === 'change' ? 'new-hash' : 'old-hash');
    } finally {
        if ($firstConnection->isTransactionActive()) {
            $firstConnection->rollBack();
        }
        $firstConnection->close();
        $secondConnection->close();
        $filesystem->remove($path);
    }
})->with(['change', 'replace']);

test('recovery request preserves its generic response when eligibility changes after lookup', function (string $state): void {
    $em = staffResetEntityManager();
    $admin = (new Admin())->setEmail('staff@example.com')->setPass('old-hash');
    $em->persist($admin);
    $em->flush();
    staffResetRecord($em, $admin, 'old-token');
    if ($state !== 'active') {
        $em->getConnection()->update('admin', $state === 'cron' ? ['system_name' => Admin::SYSTEM_CRON] : ['status' => Admin::STATUS_INACTIVE], ['id' => $admin->getId()]);
    }
    $di = staffResetContainer($em);
    $tools = Mockery::mock(FOSSBilling\Tools::class);
    $tools->shouldReceive('validateAndSanitizeEmail')->andReturn('staff@example.com');
    $di['tools'] = $tools;
    $limiter = Mockery::mock(FOSSBilling\Security\RateLimiter::class);
    $limiter->shouldReceive('consume')->andReturn(new FOSSBilling\Security\RateLimitResult('test', false, 5, 4));
    $di['rate_limiter'] = $limiter;
    $extension = Mockery::mock(Box\Mod\Extension\Service::class);
    $extension->shouldReceive('isExtensionActive')->andReturn(false);
    $email = Mockery::mock(Box\Mod\Email\Service::class);
    if ($state === 'active') {
        $email->shouldReceive('sendTemplate')->once()->andReturnUsing(function (array $data) use ($em, $admin): void {
            expect($data['hash'])->toMatch('/^[a-f0-9]{64}$/');
            expect($em->getRepository(AdminPasswordReset::class)->findOneByHash($data['hash'])->getAdmin())->toBe($admin);
        });
    } else {
        $email->shouldNotReceive('sendTemplate');
    }
    $di['mod_service'] = $di->protect(static fn (string $name): object => $name === 'extension' ? $extension : $email);
    expect(staffResetGuest($di)->passwordreset(['email' => 'staff@example.com']))->toBeTrue();
    expect($em->getConnection()->fetchOne('SELECT COUNT(*) FROM admin_password_reset'))->toBe(1);
    expect($em->getRepository(AdminPasswordReset::class)->findOneByHash('old-token') === null)->toBe($state === 'active');
})->with(['active', 'inactive', 'cron']);
