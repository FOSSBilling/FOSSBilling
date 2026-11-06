<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Box\Mod\Extension\Entity\ExtensionMeta;
use Box\Mod\Extension\Event\AfterAdminExtensionConfigSaveEvent;
use Box\Mod\Extension\Service as ExtensionService;
use Box\Mod\Staff\Entity\Admin;
use Box\Mod\Staff\Entity\AdminGroup;
use Box\Mod\Staff\Entity\AdminGroupMember;
use Box\Mod\Staff\Entity\AdminPasswordReset;
use Box\Mod\Staff\Service as StaffService;
use DebugBar\DataCollector\PDO\TraceablePDO;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Core\Module;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function Tests\Helpers\container;

function requestCachingContainer(?EntityManager $em = null): Pimple\Container
{
    if ($em === null) {
        $configuration = ORMSetup::createAttributeMetadataConfig([
            PATH_MODS . '/Staff/Entity',
            PATH_MODS . '/Extension/Entity',
        ], true, cache: new ArrayAdapter());
        $configuration->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
        $configuration->setProxyDir(sys_get_temp_dir() . '/fossbilling-request-cache-proxies');
        $configuration->setProxyNamespace('FOSSBilling\\Tests\\RequestScopedCaching\\Proxies');
        if (PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }
        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);
        (new SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [
            Admin::class, AdminGroup::class, AdminGroupMember::class, AdminPasswordReset::class, ExtensionMeta::class,
        ]));
    }
    $di = container();
    $di['em'] = $em;
    $di['cache'] = new ArrayAdapter();
    $di['crypt'] = new FOSSBilling\Core\Security\Crypt();
    $di['event_dispatcher'] = new EventDispatcher();
    $extension = Mockery::mock(ExtensionService::class)->makePartial();
    $extension->shouldReceive('getSpecificModulePermissions')->andReturn([]);
    $di['mod_service'] = $di->protect(static fn (): object => $extension);

    return $di;
}

function requestCachingStaff(Pimple\Container $di): StaffService
{
    $service = new StaffService();
    $service->setDi($di);

    return $service;
}

function requestCachingModule(Pimple\Container $di, string $name = 'client'): Module
{
    $module = new Module($name);
    $module->setDi($di);

    return $module;
}

function requestCachingStaffFixtures(Pimple\Container $di): array
{
    $root = (new AdminGroup())->setName('Super administrators')->setSystemName(AdminGroup::SYSTEM_SUPER_ADMIN)->setProtected(true);
    $group = (new AdminGroup())->setName('Support')->setParent($root)->setPermissions(['support' => ['access' => true, 'manage_tickets' => true]]);
    $actor = (new Admin())->setEmail('actor@example.test');
    $target = (new Admin())->setEmail('target@example.test');
    foreach ([$root, $group, $actor, $target] as $entity) {
        $di['em']->persist($entity);
    }
    $di['em']->persist(new AdminGroupMember($actor, $root));
    $di['em']->flush();
    $di['loggedin_admin'] = $actor;

    return [$actor, $target, $root, $group];
}

test('authorization facts share a request cache across service instances and keep staff identities separate', function (): void {
    $di = requestCachingContainer();
    [$actor, $target] = requestCachingStaffFixtures($di);
    $trace = new TraceablePDO($di['em']->getConnection()->getNativeConnection());
    foreach ([requestCachingStaff($di), requestCachingStaff($di)] as $service) {
        expect($service->isSuperAdministrator($actor->getId()))->toBeTrue()
            ->and($service->isSuperAdministrator($target->getId()))->toBeFalse()
            ->and($service->getPermissions($target->getId()))->toBe([]);
    }
    expect($trace->getExecutedStatements())->toHaveCount(3);

    // A new request gets fresh facts, even when the entity manager is reused.
    $fresh = requestCachingStaff(requestCachingContainer($di['em']));
    expect($fresh->isSuperAdministrator($target->getId()))->toBeFalse()
        ->and($fresh->getPermissions($target->getId()))->toBe([])
        ->and($trace->getExecutedStatements())->toHaveCount(5);
});

test('membership grants and revocations invalidate cached permissions in other service instances', function (): void {
    $di = requestCachingContainer();
    [, $target, $root, $group] = requestCachingStaffFixtures($di);
    $reader = requestCachingStaff($di);
    $writer = requestCachingStaff($di);

    expect($reader->hasPermission($target, 'support', 'manage_tickets'))->toBeFalse();
    $writer->addAdminToGroup($target, $group);
    expect($reader->hasPermission($target, 'support', 'manage_tickets'))->toBeTrue();
    $writer->removeAdminFromGroup($target, $group);
    expect($reader->hasPermission($target, 'support', 'manage_tickets'))->toBeFalse();

    expect($reader->isSuperAdministrator($target->getId()))->toBeFalse();
    $writer->addAdminToGroup($target, $root);
    expect($reader->isSuperAdministrator($target->getId()))->toBeTrue();
    $writer->removeAdminFromGroup($target, $root);
    expect($reader->isSuperAdministrator($target->getId()))->toBeFalse()
        ->and($reader->hasPermission($target, 'support', 'manage_tickets'))->toBeFalse();
});

test('group permission edits invalidate already cached access for their members', function (): void {
    $di = requestCachingContainer();
    [, $target, , $group] = requestCachingStaffFixtures($di);
    $reader = requestCachingStaff($di);
    $writer = requestCachingStaff($di);
    $writer->addAdminToGroup($target, $group);
    expect($reader->hasPermission($target, 'support', 'manage_tickets'))->toBeTrue();

    $writer->updateGroup($group, ['permissions' => []]);
    expect($reader->hasPermission($target, 'support', 'manage_tickets'))->toBeFalse();
    $writer->updateGroup($group, ['permissions' => ['support' => ['access' => true, 'manage_tickets' => true]]]);
    expect($reader->hasPermission($target, 'support', 'manage_tickets'))->toBeTrue();
});

test('deleting staff clears their cached super administrator membership before after-delete listeners', function (): void {
    $di = requestCachingContainer();
    [, $target, $root] = requestCachingStaffFixtures($di);
    $writer = requestCachingStaff($di);
    $writer->addAdminToGroup($target, $root);
    $reader = requestCachingStaff($di);
    $targetId = $target->getId();
    expect($reader->isSuperAdministrator($targetId))->toBeTrue();
    $observed = null;
    $di['event_dispatcher']->addListener(Box\Mod\Staff\Event\AfterAdminStaffDeleteEvent::class, function () use ($reader, $targetId, &$observed): void {
        $observed = $reader->isSuperAdministrator($targetId);
    });
    $writer->delete($target);
    expect($observed)->toBeFalse();
});

test('module configuration caches empty reads across instances but refreshes before after-save listeners', function (): void {
    $di = requestCachingContainer();
    $trace = new TraceablePDO($di['em']->getConnection()->getNativeConnection());
    $first = requestCachingModule($di);
    $second = requestCachingModule($di, 'CLIENT');
    expect($first->getConfig())->toBe([])->and($second->getConfig())->toBe([])
        ->and($trace->getExecutedStatements())->toHaveCount(1);

    $writer = Mockery::mock(ExtensionService::class)->makePartial();
    $writer->shouldAllowMockingProtectedMethods();
    $writer->shouldReceive('hasManagePermission')->with('mod_client');
    $writer->setDi($di);
    expect($writer->getConfig('mod_client'))->toBe(['ext' => 'mod_client']);
    $observed = [];
    $di['event_dispatcher']->addListener(AfterAdminExtensionConfigSaveEvent::class, function () use ($second, $writer, &$observed): void {
        $observed[] = [$second->getConfig(), $writer->getConfig('mod_client')];
    });
    foreach ([true, false] as $enabled) {
        $data = ['ext' => 'mod_client', 'enabled' => $enabled];
        $writer->setConfig($data);
        expect($first->getConfig())->toBe($data)
            ->and($observed[array_key_last($observed)])->toBe([$data, $data]);
    }

    $di['em']->getConnection()->update('extension_meta', ['meta_value' => $di['crypt']->encrypt(json_encode(['enabled' => 'next request']), FOSSBilling\Core\System\Config::getProperty('info.salt'))], ['extension' => 'mod_client']);
    $di['em']->clear();
    expect(requestCachingModule(requestCachingContainer($di['em']))->getConfig())->toBe(['enabled' => 'next request']);
});
