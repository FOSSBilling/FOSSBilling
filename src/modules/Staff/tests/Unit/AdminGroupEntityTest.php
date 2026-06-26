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

test('admin group encodes permissions and identifies super administrator group', function (): void {
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

    $repository = Mockery::mock(AdminGroupMemberRepository::class)->makePartial();
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
