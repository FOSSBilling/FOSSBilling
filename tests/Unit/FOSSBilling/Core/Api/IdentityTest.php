<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Client\Entity\Client as ClientEntity;
use Box\Mod\Staff\Entity\Admin as AdminEntity;
use FOSSBilling\Core\Api\Identity;
use FOSSBilling\Core\Api\Role;
use FOSSBilling\Core\Identity\Guest;

use function Tests\Helpers\createEntity;

test('resolves the typed guest identity to the guest role', function (): void {
    expect(Identity::typeFromObject(new Guest()))->toBe(Role::Guest)
        ->and(Identity::typeFromObject(new Guest())->value)->toBe('guest');
});

test('resolves the admin identity to the admin role', function (): void {
    $admin = \Tests\Helpers\admin();

    expect(Identity::typeFromObject($admin))->toBe(Role::Admin)
        ->and(Identity::typeFromObject($admin)->value)->toBe('admin');
});

test('resolves the client identity to the client role', function (): void {
    expect(Identity::typeFromObject(new ClientEntity()))->toBe(Role::Client)
        ->and(Identity::typeFromObject(new ClientEntity())->value)->toBe('client');
});

test('resolves Doctrine identities and their proxies to their roles', function (): void {
    expect(Identity::typeFromObject(new AdminEntity()))->toBe(Role::Admin)
        ->and(Identity::typeFromObject(createEntity(AdminEntity::class)))->toBe(Role::Admin)
        ->and(Identity::typeFromObject(new ClientEntity()))->toBe(Role::Client)
        ->and(Identity::typeFromObject(createEntity(ClientEntity::class)))->toBe(Role::Client);
});

test('rejects unsupported identity objects', function (): void {
    Identity::typeFromObject(new class {
    });
})->throws(InvalidArgumentException::class, 'Unsupported API identity:');
