<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;

test('di returns dependency injection container', function (): void {
    $service = new Box\Mod\Currency\Service();

    $di = container();
    $db = Mockery::mock('Box_Database');

    $repositoryStub = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryStub);

    $di['db'] = $db;
    $di['em'] = $emMock;
    $service->setDi($di);
    $result = $service->getDi();
    expect($result)->toBe($di);
});

test('getBaseCurrencyRate returns rate for currency', function (): void {
    $service = new Box\Mod\Currency\Service();
    $rate = 0.6;
    $expected = 1 / $rate;
    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $repositoryMock->shouldReceive('getRateByCode')
        ->atLeast()->once()
        ->andReturn($rate);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);

    $di = new Pimple\Container();
    $di['em'] = $emMock;
    $service->setDi($di);
    $code = 'EUR';
    $result = $service->getBaseCurrencyRate($code);
    expect($result)->toBe($expected);
});

test('getBaseCurrencyRate throws exception when rate is zero', function (): void {
    $service = new Box\Mod\Currency\Service();

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $repositoryMock->shouldReceive('getRateByCode')
        ->atLeast()->once()
        ->andReturn(0.0);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);

    $di = new Pimple\Container();
    $di['em'] = $emMock;
    $service->setDi($di);
    $code = 'EUR';

    expect(fn (): float => $service->getBaseCurrencyRate($code))
        ->toThrow(FOSSBilling\Exception::class);
});

test('getBaseCurrencyRate throws exception when currency not found', function (): void {
    $service = new Box\Mod\Currency\Service();

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $repositoryMock->shouldReceive('getRateByCode')
        ->atLeast()->once()
        ->andReturn(null);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);

    $di = new Pimple\Container();
    $di['em'] = $emMock;
    $service->setDi($di);
    $code = 'XYZ';

    expect(fn (): float => $service->getBaseCurrencyRate($code))
        ->toThrow(FOSSBilling\Exception::class, 'Currency not found');
});

dataset('toBaseCurrencyProvider', fn (): array => [
    ['EUR', 'USD', 100, 0.73, 73],
    ['USD', 'EUR', 100, 1.37, 137],
    ['EUR', 'EUR', 100, 0.5, 100],
]);

test('toBaseCurrency converts amount to base currency', function (string $defaultCode, string $foreignCode, int $amount, float $rate, int $expected): void {
    $model = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $model->shouldReceive('getCode')
        ->atLeast()->once()
        ->andReturn($defaultCode);

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $repositoryMock->shouldReceive('findDefault')
        ->atLeast()->once()
        ->andReturn($model);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);

    $serviceMock = Mockery::mock(Box\Mod\Currency\Service::class)->makePartial();

    $serviceMock->shouldReceive('getBaseCurrencyRate')
        ->byDefault()
        ->andReturn($rate);

    $di = new Pimple\Container();
    $di['em'] = $emMock;
    $serviceMock->setDi($di);

    $result = $serviceMock->toBaseCurrency($foreignCode, $amount);

    expect(round($result, 2))->toEqual($expected);
})->with('toBaseCurrencyProvider');

dataset('getCurrencyByClientIdProvider', fn (): array => [
    ['USD', 'atLeastOnce', 'never'],
    [null, 'never', 'atLeastOnce'],
]);

test('getCurrencyByClientId returns currency for client', function (?string $currency, string $expectsGetByCode, string $expectsGetDefault): void {
    $model = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);

    $di = new Pimple\Container();

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $repositoryMock->shouldReceive('getClientCurrencyCode')
        ->once()
        ->with(1)
        ->andReturn($currency);
    if ($expectsGetDefault === 'atLeastOnce') {
        $repositoryMock->shouldReceive('findDefault')
            ->atLeast()->once()
            ->andReturn($model);
    } else {
        $repositoryMock->shouldReceive('findDefault')->never();
    }
    if ($expectsGetByCode === 'atLeastOnce') {
        $repositoryMock->shouldReceive('findOneByCode')
            ->atLeast()->once()
            ->andReturn($model);
    } else {
        $repositoryMock->shouldReceive('findOneByCode')->never();
    }

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);

    $di['em'] = $emMock;

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);

    $result = $service->getCurrencyByClientId(1);

    expect($result)->toBeInstanceOf(Box\Mod\Currency\Entity\Currency::class);
})->with('getCurrencyByClientIdProvider');

dataset('getRateByCodeProvider', fn (): array => [
    ['EUR', 0.6, 0.6],
    ['GBP', null, null],
]);

test('getRateByCode returns rate for currency code', function (string $code, ?float $returns, ?float $expected): void {
    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $repositoryMock->shouldReceive('getRateByCode')
        ->atLeast()->once()
        ->andReturn($returns);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);

    $di = new Pimple\Container();
    $di['em'] = $emMock;

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);

    $result = $service->getCurrencyRepository()->getRateByCode($code);
    expect($result)->toBe($expected);
})->with('getRateByCodeProvider');

dataset('setAsDefaultProvider', fn (): array => [
    ['default_currency', 'atLeastOnce'],
    ['already_default', 'never'],
]);

test('setAsDefault sets currency as default', function (string $modelType, string $expects): void {
    if ($modelType === 'default_currency') {
        $model = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
        $model->shouldReceive('getCode')
            ->byDefault()
            ->andReturn('USD');
        $model->shouldReceive('isDefault')
            ->byDefault()
            ->andReturn(false);
    } else {
        $model = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
        $model->shouldReceive('getCode')
            ->byDefault()
            ->andReturn('USD');
        $model->shouldReceive('isDefault')
            ->byDefault()
            ->andReturn(true);
    }

    $refetchedModel = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $refetchedModel->shouldReceive('getCode')
        ->byDefault()
        ->andReturn('USD');
    $refetchedModel->shouldReceive('isDefault')
        ->byDefault()
        ->andReturn(false);

    // setIsDefault is only called when currency is not already default
    if ($modelType === 'default_currency') {
        $refetchedModel->shouldReceive('setIsDefault')
            ->atLeast()->once()
            ->with(true);
    }

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    if ($expects === 'atLeastOnce') {
        $repositoryMock->shouldReceive('clearDefaultFlags')
            ->atLeast()->once();
        $repositoryMock->shouldReceive('findOneByCode')
            ->with('USD')
            ->atLeast()->once()
            ->andReturn($refetchedModel);
    } else {
        $repositoryMock->shouldReceive('clearDefaultFlags')->never();
        $repositoryMock->shouldReceive('findOneByCode')->never();
    }

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);
    if ($expects === 'atLeastOnce') {
        $emMock->shouldReceive('persist')
            ->atLeast()->once();
        $emMock->shouldReceive('flush')
            ->atLeast()->once();
        $emMock->shouldReceive('clear')
            ->with(Box\Mod\Currency\Entity\Currency::class)
            ->atLeast()->once();
    } else {
        $emMock->shouldReceive('persist')->never();
        $emMock->shouldReceive('flush')->never();
        $emMock->shouldReceive('clear')->never();
    }

    $di = new Pimple\Container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);
    $result = $service->setAsDefault($model);

    expect($result)->toBeTrue();
})->with('setAsDefaultProvider');

test('setAsDefault throws exception when currency code is empty', function (): void {
    $model = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $model->shouldReceive('isDefault')
        ->byDefault()
        ->andReturn(false);
    $model->shouldReceive('getCode')
        ->byDefault()
        ->andReturn('');

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);

    $di = new Pimple\Container();
    $di['em'] = $emMock;

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);

    expect(fn (): bool => $service->setAsDefault($model))
        ->toThrow(FOSSBilling\Exception::class);
});

test('getPairs returns currency pairs', function (): void {
    $pairs = [
        'USD' => 'US Dollar',
        'EUR' => 'Euro',
        'GBP' => 'Pound Sterling',
    ];
    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $repositoryMock->shouldReceive('getPairs')
        ->atLeast()->once()
        ->andReturn($pairs);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);

    $di = new Pimple\Container();
    $di['em'] = $emMock;

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);
    $result = $service->getCurrencyRepository()->getPairs();

    expect($result)->toBe($pairs);
});

test('removeCurrency throws exception when deleting default currency', function (): void {
    $model = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $model->shouldReceive('getCode')
        ->byDefault()
        ->andReturn('EUR');
    $model->shouldReceive('isDefault')
        ->byDefault()
        ->andReturn(true);

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $repositoryMock->shouldReceive('findOneByCode')
        ->once()
        ->with('EUR')
        ->andReturn($model);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);

    $di = new Pimple\Container();
    $di['em'] = $emMock;

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);

    expect(fn (): bool => $service->removeCurrency('EUR'))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('removeCurrency removes currency', function (): void {
    $model = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $model->shouldReceive('getCode')
        ->byDefault()
        ->andReturn('EUR');
    $model->shouldReceive('isDefault')
        ->byDefault()
        ->andReturn(false);

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $repositoryMock->shouldReceive('findOneByCode')
        ->once()
        ->with('EUR')
        ->andReturn($model);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);
    $emMock->shouldReceive('remove')
        ->atLeast()->once()
        ->with($model);
    $emMock->shouldReceive('flush')
        ->atLeast()->once();

    $eventsManager = Mockery::mock(Box_EventManager::class);
    $eventsManager->shouldReceive('fire')
        ->twice();

    $di = new Pimple\Container();
    $di['em'] = $emMock;
    $di['events_manager'] = $eventsManager;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);
    $result = $service->removeCurrency('EUR');

    expect($result)->toBeTrue();
});

test('removeCurrency throws exception when currency is not found', function (): void {
    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $repositoryMock->shouldReceive('findOneByCode')
        ->once()
        ->with('')
        ->andReturn(null);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);

    $di = new Pimple\Container();
    $di['em'] = $emMock;

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);

    expect(fn (): bool => $service->removeCurrency(''))
        ->toThrow(FOSSBilling\Exception::class);
});

test('toApiArray returns API array for currency', function (): void {
    $model = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);

    $expected = [
        'code' => 'EUR',
        'title' => 'Euro',
        'conversion_rate' => 3.4528,
        'format' => '',
        'price_format' => '',
        'default' => true,
    ];

    $model->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($expected);

    $result = $model->toApiArray();
    expect($result)->toBe($expected);
});

test('createCurrency creates new currency', function (): void {
    $code = 'EUR';
    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);
    $emMock->shouldReceive('persist')
        ->atLeast()->once();
    $emMock->shouldReceive('flush')
        ->atLeast()->once();

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['em'] = $emMock;

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);

    $result = $service->createCurrency($code, 0.6);

    expect($result)->toBeString();
    expect(strlen($result))->toBe(3);
    expect($result)->toBe($code);
});

test('updateCurrency updates currency', function (): void {
    $code = 'EUR';
    $conversion_rate = 0.6;

    $model = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $model->shouldReceive('getCode')
        ->atLeast()->once()
        ->andReturn('EUR');
    $model->shouldReceive('setConversionRate')
        ->atLeast()->once()
        ->with(0.6);

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $repositoryMock->shouldReceive('findOneByCode')
        ->atLeast()->once()
        ->andReturn($model);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);
    $emMock->shouldReceive('persist')
        ->atLeast()->once();
    $emMock->shouldReceive('flush')
        ->atLeast()->once();

    $di = new Pimple\Container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['em'] = $emMock;

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);

    $result = $service->updateCurrency($code, $conversion_rate);

    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('updateCurrency throws exception when currency not found', function (): void {
    $code = 'EUR';
    $conversion_rate = 0.6;

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $repositoryMock->shouldReceive('findOneByCode')
        ->atLeast()->once()
        ->andReturn(null);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);

    $di = new Pimple\Container();
    $di['em'] = $emMock;

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);

    expect(fn (): bool => $service->updateCurrency($code, $conversion_rate))
        ->toThrow(FOSSBilling\Exception::class);
});

test('updateCurrency throws exception when conversion rate is zero', function (): void {
    $code = 'EUR';
    $conversion_rate = 0;

    $model = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $model->shouldReceive('setConversionRate')
        ->byDefault();

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $repositoryMock->shouldReceive('findOneByCode')
        ->atLeast()->once()
        ->andReturn($model);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);

    $di = new Pimple\Container();
    $di['em'] = $emMock;

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);

    expect(fn (): bool => $service->updateCurrency($code, $conversion_rate))
        ->toThrow(FOSSBilling\Exception::class);
});

test('updateCurrencyRates updates rates for all currencies', function (): void {
    $defaultModel = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $defaultModel->shouldReceive('getCode')
        ->byDefault()
        ->andReturn('EUR');
    $defaultModel->shouldReceive('isDefault')
        ->byDefault()
        ->andReturn(true);
    $defaultModel->shouldReceive('setConversionRate')
        ->byDefault();

    $otherModel = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $otherModel->shouldReceive('getCode')
        ->byDefault()
        ->andReturn('USD');
    $otherModel->shouldReceive('isDefault')
        ->byDefault()
        ->andReturn(false);
    $otherModel->shouldReceive('setConversionRate')
        ->byDefault();

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $repositoryMock->shouldReceive('findDefault')
        ->atLeast()->once()
        ->andReturn($defaultModel);
    $repositoryMock->shouldReceive('findAll')
        ->atLeast()->once()
        ->andReturn([$defaultModel, $otherModel]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);
    $emMock->shouldReceive('flush')
        ->atLeast()->once();

    $serviceMock = Mockery::mock(Box\Mod\Currency\Service::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getRate')
        ->atLeast()->once()
        ->andReturn(floatval(random_int(1, 50) / 10));

    $di = new Pimple\Container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['em'] = $emMock;
    $serviceMock->setDi($di);

    $result = $serviceMock->updateCurrencyRates();

    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('updateCurrencyRates handles non-numeric rates', function (): void {
    $model = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $model->shouldReceive('getCode')
        ->byDefault()
        ->andReturn('EUR');
    $model->shouldReceive('isDefault')
        ->byDefault()
        ->andReturn(false);
    $model->shouldReceive('setConversionRate')
        ->byDefault();

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $repositoryMock->shouldReceive('findDefault')
        ->atLeast()->once()
        ->andReturn($model);
    $repositoryMock->shouldReceive('findAll')
        ->atLeast()->once()
        ->andReturn([$model]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);
    $emMock->shouldReceive('flush')
        ->atLeast()->once();

    $serviceMock = Mockery::mock(Box\Mod\Currency\Service::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getRate')
        ->atLeast()->once()
        ->andReturn(0.0);

    $di = new Pimple\Container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['em'] = $emMock;
    $serviceMock->setDi($di);

    $result = $serviceMock->updateCurrencyRates();

    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('removeCurrency deletes currency by code', function (): void {
    $model = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $model->shouldReceive('getCode')
        ->byDefault()
        ->andReturn('EUR');
    $model->shouldReceive('isDefault')
        ->byDefault()
        ->andReturn(false);

    $code = 'EUR';

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $repositoryMock->shouldReceive('findOneByCode')
        ->atLeast()->once()
        ->andReturn($model);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);
    $emMock->shouldReceive('remove')
        ->atLeast()->once();
    $emMock->shouldReceive('flush')
        ->atLeast()->once();

    $manager = Mockery::mock('Box_EventManager');
    $manager->shouldReceive('fire')
        ->atLeast()->once()
        ->andReturn(true);

    $di = new Pimple\Container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['em'] = $emMock;
    $di['events_manager'] = $manager;

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);

    $result = $service->removeCurrency($code);

    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('removeCurrency throws exception when currency not found by code', function (): void {
    $code = 'EUR';

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $repositoryMock->shouldReceive('findOneByCode')
        ->atLeast()->once()
        ->andReturn(null);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);

    $di = new Pimple\Container();
    $di['em'] = $emMock;

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);

    expect(fn (): bool => $service->removeCurrency($code))
        ->toThrow(FOSSBilling\Exception::class);
});
