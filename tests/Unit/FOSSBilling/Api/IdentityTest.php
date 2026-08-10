<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Api\Identity;

test('resolves the typed guest identity to the guest role', function (): void {
    expect(Identity::typeFromObject(new FOSSBilling\Identity\Guest()))->toBe('guest');
});

test('resolves the admin identity to the admin role', function (): void {
    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());

    expect(Identity::typeFromObject($admin))->toBe('admin');
});

test('resolves the client identity to the client role', function (): void {
    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());

    expect(Identity::typeFromObject($client))->toBe('client');
});
