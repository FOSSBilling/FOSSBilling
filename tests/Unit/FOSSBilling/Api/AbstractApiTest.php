<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Api\AbstractApi;

use function Tests\Helpers\createEntity;

// Minimal concrete subclass to expose the protected method.
class ConcreteApi extends AbstractApi
{
    public function callCheckPermissions(string $module, ?string $key = null, mixed $constraint = null): void
    {
        $this->checkPermissions($module, $key, $constraint);
    }
}

test('does not resolve the test container implicitly', function (): void {
    expect((new ConcreteApi())->getDi())->toBeNull();
});

test('checkPermissions forwards identity to Staff service', function (): void {
    $identity = createEntity(\Box\Mod\Staff\Entity\Admin::class);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')
        ->once()
        ->with('invoice', 'manage_invoices', null, $identity);

    $di = new Pimple\Container();
    $di['mod_service'] = $di->protect(fn (string $name): object => match (strtolower($name)) {
        'staff' => $staffService,
        default => throw new RuntimeException("Unexpected mod service: $name"),
    });

    $api = new ConcreteApi();
    $api->setDi($di);
    $api->setIdentity($identity);

    $api->callCheckPermissions('invoice', 'manage_invoices');
});

test('checkPermissions forwards constraint to Staff service', function (): void {
    $identity = createEntity(\Box\Mod\Staff\Entity\Admin::class);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')
        ->once()
        ->with('order', 'manage', 42, $identity);

    $di = new Pimple\Container();
    $di['mod_service'] = $di->protect(fn (string $name): object => match (strtolower($name)) {
        'staff' => $staffService,
        default => throw new RuntimeException("Unexpected mod service: $name"),
    });

    $api = new ConcreteApi();
    $api->setDi($di);
    $api->setIdentity($identity);

    $api->callCheckPermissions('order', 'manage', 42);
});
