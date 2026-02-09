<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;
use Box\Mod\Servicedomain\Api\Guest;
use Box\Mod\Servicedomain\Service;

beforeEach(function () {
    $this->guestApi = new Guest();
});

test('gets tlds', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldToApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $this->guestApi->setService($serviceMock);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([new \Model_Tld()]);

    $di = container();
    $di['db'] = $dbMock;

    $this->guestApi->setDi($di);

    $result = $this->guestApi->tlds([]);
    expect($result)->toBeArray();
    expect($result[0])->toBeArray();
});

test('gets pricing', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->atLeast()->once()
        ->andReturn(new \Model_Tld());
    $serviceMock->shouldReceive('tldToApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $this->guestApi->setDi($di);
    $this->guestApi->setService($serviceMock);

    $data = [
        'tld' => '.com',
    ];

    $result = $this->guestApi->pricing($data);
    expect($result)->toBeArray();
});

test('throws exception when getting pricing for tld not found', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->atLeast()->once()
        ->andReturn(null);
    $serviceMock->shouldReceive('tldToApiArray')
        ->never();

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);

    $di = container();
    $di['validator'] = $validatorMock;
    $this->guestApi->setDi($di);
    $this->guestApi->setService($serviceMock);

    $data = [
        'tld' => '.com',
    ];

    expect(fn () => $this->guestApi->pricing($data))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('checks domain availability', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->atLeast()->once()
        ->andReturn(new \Model_Tld());
    $serviceMock->shouldReceive('isDomainAvailable')
        ->atLeast()->once()
        ->andReturn(true);

    $this->guestApi->setService($serviceMock);

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isSldValid')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['validator'] = $validatorMock;
    $this->guestApi->setDi($di);

    $data = [
        'tld' => '.com',
        'sld' => 'example',
    ];

    $result = $this->guestApi->check($data);
    expect($result)->toBeTrue();
});

test('throws exception when checking sld not valid', function () {
    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isSldValid')
        ->atLeast()->once()
        ->andReturn(false);

    $di = container();
    $di['validator'] = $validatorMock;
    $this->guestApi->setDi($di);

    $data = [
        'tld' => '.com',
        'sld' => 'example',
    ];

    expect(fn () => $this->guestApi->check($data))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('throws exception when checking tld not found', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->atLeast()->once()
        ->andReturn(null);
    $serviceMock->shouldReceive('isDomainAvailable')
        ->never();

    $this->guestApi->setService($serviceMock);

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isSldValid')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['validator'] = $validatorMock;
    $this->guestApi->setDi($di);

    $data = [
        'tld' => '.com',
        'sld' => 'example',
    ];

    expect(fn () => $this->guestApi->check($data))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('throws exception when checking domain not available', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->atLeast()->once()
        ->andReturn(new \Model_Tld());
    $serviceMock->shouldReceive('isDomainAvailable')
        ->atLeast()->once()
        ->andReturn(false);

    $this->guestApi->setService($serviceMock);

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isSldValid')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['validator'] = $validatorMock;
    $this->guestApi->setDi($di);

    $data = [
        'tld' => '.com',
        'sld' => 'example',
    ];

    expect(fn () => $this->guestApi->check($data))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('checks if domain can be transferred', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->atLeast()->once()
        ->andReturn(new \Model_Tld());
    $serviceMock->shouldReceive('canBeTransferred')
        ->atLeast()->once()
        ->andReturn(true);

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);

    $di = container();
    $di['validator'] = $validatorMock;
    $this->guestApi->setDi($di);

    $this->guestApi->setService($serviceMock);

    $data = [
        'tld' => '.com',
        'sld' => 'example',
    ];

    $result = $this->guestApi->can_be_transferred($data);
    expect($result)->toBeTrue();
});

test('throws exception when checking transfer for tld not found', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->atLeast()->once()
        ->andReturn(null);
    $serviceMock->shouldReceive('canBeTransferred')
        ->never();

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);

    $di = container();
    $di['validator'] = $validatorMock;
    $this->guestApi->setDi($di);
    $this->guestApi->setService($serviceMock);

    $data = [
        'tld' => '.com',
        'sld' => 'example',
    ];

    expect(fn () => $this->guestApi->can_be_transferred($data))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('throws exception when checking domain cannot be transferred', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->atLeast()->once()
        ->andReturn(new \Model_Tld());
    $serviceMock->shouldReceive('canBeTransferred')
        ->atLeast()->once()
        ->andReturn(false);

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);

    $di = container();
    $di['validator'] = $validatorMock;
    $this->guestApi->setDi($di);
    $this->guestApi->setService($serviceMock);

    $data = [
        'tld' => '.com',
        'sld' => 'example',
    ];

    expect(fn () => $this->guestApi->can_be_transferred($data))
        ->toThrow(\FOSSBilling\Exception::class);
});
