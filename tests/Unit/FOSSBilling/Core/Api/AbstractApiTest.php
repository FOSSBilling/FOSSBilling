<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Core\Api\AbstractApi;

// Minimal concrete subclass to expose the protected method.
class ConcreteApi extends AbstractApi
{
    public function callCheckPermissions(string $module, ?string $key = null, mixed $constraint = null): void
    {
        $this->checkPermissions($module, $key, $constraint);
    }

    public function callCheckCaptchaIfEnabled(array $data): void
    {
        $this->checkCaptchaIfEnabled($data);
    }
}

test('does not resolve the test container implicitly', function (): void {
    expect((new ConcreteApi())->getDi())->toBeNull();
});

test('stores and returns the module service', function (): void {
    $service = new stdClass();
    $api = new ConcreteApi();

    $api->setService($service);

    expect($api->getService())->toBe($service);
});

test('requires a module service before it is read', function (): void {
    expect(fn (): object => (new ConcreteApi())->getService())
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'Service object is not set for the API');
});

test('checkPermissions forwards identity to Staff service', function (): void {
    $identity = \Tests\Helpers\admin();

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
    $identity = \Tests\Helpers\admin();

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

test('checkCaptchaIfEnabled skips an inactive antispam extension', function (): void {
    $extensionService = Mockery::mock();
    $extensionService->shouldReceive('isExtensionActive')->once()->with('mod', 'antispam')->andReturnFalse();

    $di = new Pimple\Container();
    $di['mod_service'] = $di->protect(fn (string $name): object => match (strtolower($name)) {
        'extension' => $extensionService,
        default => throw new RuntimeException("Unexpected mod service: $name"),
    });

    $api = new ConcreteApi();
    $api->setDi($di);
    $api->callCheckCaptchaIfEnabled(['email' => 'user@example.com']);
});

test('checkCaptchaIfEnabled delegates to the antispam extension', function (): void {
    $data = ['g-recaptcha-response' => 'token'];
    $extensionService = Mockery::mock();
    $extensionService->shouldReceive('isExtensionActive')->once()->with('mod', 'antispam')->andReturnTrue();

    $antispamService = Mockery::mock();
    $antispamService->shouldReceive('checkCaptcha')->once()->with($data);

    $di = new Pimple\Container();
    $di['mod_service'] = $di->protect(fn (string $name): object => match (strtolower($name)) {
        'extension' => $extensionService,
        'antispam' => $antispamService,
        default => throw new RuntimeException("Unexpected mod service: $name"),
    });

    $api = new ConcreteApi();
    $api->setDi($di);
    $api->callCheckCaptchaIfEnabled($data);
});
