<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Staff\Entity\AdminGroup;
use Box\Mod\Staff\Repository\AdminGroupMemberRepository;
use Box\Mod\Staff\Repository\AdminGroupRepository;

test('admin group stores permissions and identifies super administrator group', function (): void {
    $group = (new AdminGroup())
        ->setName('Super Administrator')
        ->setSystemName(AdminGroup::SYSTEM_SUPER_ADMIN)
        ->setProtected(true)
        ->setPermissions([
            'support' => [
                'access' => true,
                'manage_tickets' => true,
            ],
        ]);

    expect($group->isSuperAdministrator())->toBeTrue()
        ->and($group->isProtected())->toBeTrue()
        ->and($group->getPermissions())->toBe([
            'support' => [
                'access' => true,
                'manage_tickets' => true,
            ],
        ]);
});

test('admin group member repository unions group permissions', function (): void {
    $supportGroup = (new AdminGroup())->setPermissions([
        'support' => [
            'access' => true,
            'manage_tickets' => true,
        ],
    ]);
    $billingGroup = (new AdminGroup())->setPermissions([
        'support' => [
            'manage_tickets' => false,
            'manage_kb' => true,
        ],
        'invoice' => [
            'access' => true,
        ],
    ]);

    $repository = Mockery::mock(AdminGroupMemberRepository::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $repository->shouldReceive('findGroupsForAdmin')
        ->once()
        ->with(1)
        ->andReturn([$supportGroup, $billingGroup]);

    expect($repository->getPermissionsForAdmin(1))->toBe([
        'support' => [
            'access' => true,
            'manage_tickets' => true,
            'manage_kb' => true,
        ],
        'invoice' => [
            'access' => true,
        ],
    ]);
});

test('admin group member repository excludes cron from active system group count', function (): void {
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchOne')
        ->once()
        ->with(
            Mockery::on(static fn (string $sql): bool => str_contains($sql, 'a.system_name IS NULL OR a.system_name != :cron_system_name')),
            [
                'status' => Model_Admin::STATUS_ACTIVE,
                'system_name' => AdminGroup::SYSTEM_SUPER_ADMIN,
                'cron_system_name' => Model_Admin::SYSTEM_CRON,
            ],
        )
        ->andReturn(1);

    $entityManager = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $entityManager->shouldReceive('getConnection')->once()->andReturn($connection);

    $repository = Mockery::mock(AdminGroupMemberRepository::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $repository->shouldReceive('getEntityManager')->once()->andReturn($entityManager);

    expect($repository->countActiveMembersInSystemGroup(AdminGroup::SYSTEM_SUPER_ADMIN))->toBe(1);
});

test('admin group repository excludes group descendants from parent pairs', function (): void {
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAllAssociative')
        ->once()
        ->andReturn([
            ['id' => 1, 'name' => 'Root', 'parent_id' => null],
            ['id' => 2, 'name' => 'Parent', 'parent_id' => 1],
            ['id' => 3, 'name' => 'Child', 'parent_id' => 2],
            ['id' => 4, 'name' => 'Sibling', 'parent_id' => 1],
        ]);

    $entityManager = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $entityManager->shouldReceive('getConnection')->once()->andReturn($connection);

    $repository = Mockery::mock(AdminGroupRepository::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $repository->shouldReceive('getEntityManager')->once()->andReturn($entityManager);

    expect($repository->getParentPairs(2))->toBe([
        1 => 'Root',
        4 => 'Root / Sibling',
    ]);
});
