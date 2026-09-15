<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Staff\Service as StaffService;
use Box\Mod\System\Service;

use function Tests\Helpers\container;

function systemServiceWithPermissions(bool $companyDetails, bool $companyLegal): Service
{
    $staffServiceMock = Mockery::mock(StaffService::class);
    $staffServiceMock->shouldReceive('hasPermission')
        ->with(null, 'system', 'manage_company_details')
        ->andReturn($companyDetails);
    $staffServiceMock->shouldReceive('hasPermission')
        ->with(null, 'system', 'manage_company_legal')
        ->andReturn($companyLegal);

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('createQueryBuilder')->never();

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->byDefault();

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(fn (): object => $staffServiceMock);
    $di['dbal'] = $dbalMock;

    $service = new Service();
    $service->setDi($di);

    return $service;
}

test('updateParams denies a mixed-case guarded key without the company permission', function (): void {
    $service = systemServiceWithPermissions(false, true);

    expect(fn (): bool => $service->updateParams(['Company_Account_Number' => 'attacker']))->toThrow(
        FOSSBilling\InformationException::class,
        'You do not have permission to update the parameter'
    );
});

test('updateParams denies a mixed-case legal key without the legal permission', function (): void {
    $service = systemServiceWithPermissions(true, false);

    expect(fn (): bool => $service->updateParams(['Company_Note' => 'attacker']))->toThrow(
        FOSSBilling\InformationException::class,
        'You do not have permission to update the parameter'
    );
});

test('setParamValue skips a mixed-case guarded key without the company permission', function (): void {
    $service = systemServiceWithPermissions(false, true);

    expect($service->setParamValue('Company_Bic', 'attacker'))->toBeTrue();
});

test('updateParams rejects a key with a trailing space', function (): void {
    $service = systemServiceWithPermissions(true, true);

    expect(fn (): bool => $service->updateParams(['company_name ' => 'attacker']))->toThrow(
        FOSSBilling\InformationException::class,
        'Invalid parameter name'
    );
});

test('setParamValue canonicalizes a mixed-case unguarded key', function (): void {
    $capturedParams = [];

    $resultMock = Mockery::mock(Doctrine\DBAL\Result::class);
    $resultMock->shouldReceive('fetchOne')->andReturn('1');

    $qbMock = Mockery::mock(Doctrine\DBAL\Query\QueryBuilder::class);
    $qbMock->shouldReceive('select')->andReturnSelf();
    $qbMock->shouldReceive('from')->andReturnSelf();
    $qbMock->shouldReceive('where')->andReturnSelf();
    $qbMock->shouldReceive('update')->andReturnSelf();
    $qbMock->shouldReceive('set')->andReturnSelf();
    $qbMock->shouldReceive('setParameter')->andReturnUsing(function (string $key, mixed $value) use (&$capturedParams, $qbMock): object {
        $capturedParams[$key] = $value;

        return $qbMock;
    });
    $qbMock->shouldReceive('executeQuery')->andReturn($resultMock);
    $qbMock->shouldReceive('executeStatement')->once()->andReturn(1);

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('createQueryBuilder')->andReturn($qbMock);

    $staffServiceMock = Mockery::mock(StaffService::class);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): object => $staffServiceMock);
    $di['dbal'] = $dbalMock;

    $service = new Service();
    $service->setDi($di);

    expect($service->setParamValue('Last_Cron_Exec', 'new'))->toBeTrue();
    expect($capturedParams['param'])->toBe('last_cron_exec');
    expect($capturedParams['value'])->toBe('new');
});
