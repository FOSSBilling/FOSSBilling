<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Currency\Event\AfterAdminDeleteCurrencyEvent;
use Box\Mod\Currency\Event\BeforeAdminDeleteCurrencyEvent;
use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;

use function Tests\Helpers\container;

test('di returns dependency injection container', function (): void {
    $service = new Box\Mod\Currency\Service();

    $di = container();

    $repositoryStub = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryStub);

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
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class);
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
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'Currency not found');
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
            ->once()
            ->with(true)
            ->andReturnSelf();
        $refetchedModel->shouldReceive('setIsRateManual')
            ->once()
            ->with(false)
            ->andReturnSelf();
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
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class);
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
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class);
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
    $events = new ArrayObject();
    $eventDispatcher = new SymfonyEventDispatcher();
    foreach ([BeforeAdminDeleteCurrencyEvent::class, AfterAdminDeleteCurrencyEvent::class] as $eventClass) {
        $eventDispatcher->addListener($eventClass, static function (object $event) use ($events): void {
            $events->append($event);
        });
    }

    $emMock->shouldReceive('remove')
        ->once()
        ->with($model)
        ->andReturnUsing(function () use ($events): void {
            expect($events->getArrayCopy())->toHaveCount(1);
            expect($events[0])->toBeInstanceOf(BeforeAdminDeleteCurrencyEvent::class);
        });
    $emMock->shouldReceive('flush')
        ->once()
        ->andReturnUsing(function () use ($events): void {
            expect($events->getArrayCopy())->toHaveCount(1);
        });

    $di = new Pimple\Container();
    $di['em'] = $emMock;
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);
    $result = $service->removeCurrency('EUR');

    expect($result)->toBeTrue();
    expect(array_map(static fn (object $event): string => $event::class, $events->getArrayCopy()))
        ->toBe([BeforeAdminDeleteCurrencyEvent::class, AfterAdminDeleteCurrencyEvent::class]);
    expect($events[0]->code)->toBe('EUR');
    expect($events[1]->code)->toBe('EUR');
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
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class);
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

test('currency API data exposes whether its rate is a manual override', function (): void {
    $currency = (new Box\Mod\Currency\Entity\Currency('LAK'))
        ->setConversionRate(21_650.0)
        ->setIsRateManual(true);

    expect($currency->toApiArray())
        ->toMatchArray([
            'code' => 'LAK',
            'conversion_rate' => 21_650.0,
            'is_rate_manual' => true,
        ]);
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

test('createCurrency requires an explicit rate for a manual override', function (): void {
    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')->andReturn($repositoryMock);

    $di = container();
    $di['em'] = $emMock;

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);

    expect(fn (): string => $service->createCurrency('LAK', null, true))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'A conversion rate is required when manual override is enabled.');
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
    $model->shouldReceive('setIsRateManual')
        ->once()
        ->with(true);
    $model->shouldReceive('isDefault')
        ->once()
        ->andReturn(false);

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

    $result = $service->updateCurrency($code, $conversion_rate, [], true);

    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('updateCurrency rejects manual overrides for the default currency', function (): void {
    $model = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $model->shouldReceive('isDefault')->once()->andReturn(true);
    $model->shouldNotReceive('setConversionRate');
    $model->shouldNotReceive('setIsRateManual');

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $repositoryMock->shouldReceive('findOneByCode')->once()->with('USD')->andReturn($model);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')->andReturn($repositoryMock);
    $emMock->shouldNotReceive('persist');
    $emMock->shouldNotReceive('flush');

    $di = container();
    $di['em'] = $emMock;

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);

    expect(fn (): bool => $service->updateCurrency('USD', 2.0, [], true))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'The default currency cannot use a manual rate override.');
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
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class);
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
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class);
});

test('updateCurrencyRates updates rates for all currencies', function (): void {
    $defaultModel = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $defaultModel->shouldReceive('getCode')
        ->byDefault()
        ->andReturn('EUR');
    $defaultModel->shouldReceive('isDefault')
        ->byDefault()
        ->andReturn(true);
    $defaultModel->shouldReceive('isRateManual')
        ->byDefault()
        ->andReturn(false);
    $defaultModel->shouldReceive('setConversionRate')
        ->byDefault();

    $otherModel = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $otherModel->shouldReceive('getCode')
        ->byDefault()
        ->andReturn('USD');
    $otherModel->shouldReceive('isDefault')
        ->byDefault()
        ->andReturn(false);
    $otherModel->shouldReceive('isRateManual')
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
    $model->shouldReceive('isRateManual')
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

test('updateCurrencyRates preserves manual overrides', function (): void {
    $defaultModel = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $defaultModel->shouldReceive('getCode')->andReturn('USD');
    $defaultModel->shouldReceive('isDefault')->andReturn(true);
    $defaultModel->shouldReceive('setConversionRate')->once()->with(1.0);

    $manualModel = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $manualModel->shouldReceive('isDefault')->andReturn(false);
    $manualModel->shouldReceive('isRateManual')->once()->andReturn(true);
    $manualModel->shouldNotReceive('setConversionRate');

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $repositoryMock->shouldReceive('findDefault')->once()->andReturn($defaultModel);
    $repositoryMock->shouldReceive('findAll')->once()->andReturn([$defaultModel, $manualModel]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')->andReturn($repositoryMock);
    $emMock->shouldReceive('flush')->once();

    $service = Mockery::mock(Box\Mod\Currency\Service::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $service->shouldNotReceive('getRate');

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['em'] = $emMock;
    $service->setDi($di);

    expect($service->updateCurrencyRates())->toBeTrue();
});

test('updates currency rates before admin cron through a typed event listener', function (): void {
    $service = Mockery::mock(Box\Mod\Currency\Service::class)->makePartial();
    $service->shouldReceive('isCronEnabled')->once()->andReturn(true);
    $service->shouldReceive('updateCurrencyRates')->once()->andReturn(true);
    $dispatcher = new FOSSBilling\Core\Events\EventDispatcher(
        static fn (): array => ['currency'],
        static fn (string $module): object => $service,
    );

    $dispatcher->dispatch(new Box\Mod\Cron\Event\BeforeAdminCronRunEvent());
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

    $di = new Pimple\Container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['em'] = $emMock;
    $di['event_dispatcher'] = new FOSSBilling\Core\Events\EventDispatcher(static fn (): array => [], static fn (string $module): object => new stdClass());

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
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class);
});

dataset('subPrecisionRates', fn (): array => [
    [0.0000001],
    ['0.0000001'],
    ['1e-7'],
    [0.0000005],
]);

test('createCurrency rejects rates that would round to zero', function (string|float $rate): void {
    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);
    $emMock->shouldNotReceive('persist');
    $emMock->shouldNotReceive('flush');

    $di = new Pimple\Container();
    $di['em'] = $emMock;

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);

    expect(fn (): string => $service->createCurrency('EUR', $rate))
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'round to zero');
})->with('subPrecisionRates');

test('updateCurrency rejects rates that would round to zero without mutating the currency', function (string|float $rate): void {
    $model = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $model->shouldNotReceive('setConversionRate');

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $repositoryMock->shouldReceive('findOneByCode')
        ->atLeast()->once()
        ->with('EUR')
        ->andReturn($model);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);
    $emMock->shouldNotReceive('persist');
    $emMock->shouldNotReceive('flush');

    $di = new Pimple\Container();
    $di['em'] = $emMock;

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);

    expect(fn (): bool => $service->updateCurrency('EUR', $rate))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'round to zero');
})->with('subPrecisionRates');

test('createCurrency accepts the smallest storable rate', function (): void {
    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryMock);
    $emMock->shouldReceive('persist')
        ->once()
        ->with(Mockery::on(static fn (mixed $currency): bool => $currency instanceof Box\Mod\Currency\Entity\Currency && $currency->getConversionRate() === 1.0E-6));
    $emMock->shouldReceive('flush')
        ->once();

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['em'] = $emMock;

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);

    expect($service->createCurrency('EUR', 0.000001))->toBe('EUR');
});

test('numeric string conversion rates are normalized to six decimals', function (): void {
    $currency = (new Box\Mod\Currency\Entity\Currency('EUR'))->setConversionRate('0.92');

    expect($currency->getConversionRateRaw())->toBe('0.920000')
        ->and($currency->getConversionRate())->toBe(0.92);
});

test('updateCurrencyRates skips fetched rates that would round to zero', function (): void {
    $defaultModel = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $defaultModel->shouldReceive('getCode')->byDefault()->andReturn('USD');
    $defaultModel->shouldReceive('isDefault')->byDefault()->andReturn(true);
    $defaultModel->shouldReceive('setConversionRate')->once()->with(1.0);

    $tinyModel = Mockery::mock(Box\Mod\Currency\Entity\Currency::class);
    $tinyModel->shouldReceive('getCode')->byDefault()->andReturn('EUR');
    $tinyModel->shouldReceive('isDefault')->byDefault()->andReturn(false);
    $tinyModel->shouldReceive('isRateManual')->byDefault()->andReturn(false);
    $tinyModel->shouldNotReceive('setConversionRate');

    $repositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $repositoryMock->shouldReceive('findDefault')->once()->andReturn($defaultModel);
    $repositoryMock->shouldReceive('findAll')->once()->andReturn([$defaultModel, $tinyModel]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')->andReturn($repositoryMock);
    $emMock->shouldReceive('flush')->once();

    $service = Mockery::mock(Box\Mod\Currency\Service::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('getRate')->once()->with('USD', 'EUR')->andReturn(1e-7);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['em'] = $emMock;
    $service->setDi($di);

    expect($service->updateCurrencyRates())->toBeTrue();
});

test('currencylayer rates are requested from the currencylayer API with an access key', function (): void {
    $capturedRequests = [];
    $httpClient = new Symfony\Component\HttpClient\MockHttpClient(function (string $method, string $url, array $options) use (&$capturedRequests): Symfony\Component\HttpClient\Response\MockResponse {
        $capturedRequests[] = ['url' => $url, 'options' => $options];

        return new Symfony\Component\HttpClient\Response\MockResponse(
            json_encode([
                'success' => true,
                'timestamp' => 1_760_000_000,
                'source' => 'USD',
                'quotes' => ['USDEUR' => 0.9, 'USDGBP' => 0.8],
            ], JSON_THROW_ON_ERROR),
            ['response_headers' => ['content-type: application/json']]
        );
    });

    $repositoryStub = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryStub);

    $di = new Pimple\Container();
    $di['em'] = $emMock;
    $di['cache'] = static fn (): Symfony\Component\Cache\Adapter\ArrayAdapter => new Symfony\Component\Cache\Adapter\ArrayAdapter();
    $di['http_client'] = static fn (): Symfony\Component\HttpClient\MockHttpClient => $httpClient;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);

    $provider = new ReflectionMethod($service, 'getCurrencyLayerRates');
    $provider->setAccessible(true);
    $rates = $provider->invoke($service, 'USD', 3600, 'test-key');

    expect($capturedRequests)->toHaveCount(1)
        ->and($capturedRequests[0]['url'])->toStartWith('https://api.currencylayer.com/live')
        ->and($capturedRequests[0]['url'])->toContain('access_key=test-key')
        ->and($capturedRequests[0]['url'])->toContain('source=USD')
        ->and($rates)->toBe(['EUR' => 0.9, 'GBP' => 0.8]);
});

test('Currency Data API rates keep using the APILayer marketplace endpoint and apikey header', function (): void {
    $capturedRequests = [];
    $httpClient = new Symfony\Component\HttpClient\MockHttpClient(function (string $method, string $url, array $options) use (&$capturedRequests): Symfony\Component\HttpClient\Response\MockResponse {
        $capturedRequests[] = ['url' => $url, 'options' => $options];

        return new Symfony\Component\HttpClient\Response\MockResponse(
            json_encode([
                'success' => true,
                'timestamp' => 1_760_000_000,
                'source' => 'USD',
                'quotes' => ['USDEUR' => 0.9, 'USDGBP' => 0.8],
            ], JSON_THROW_ON_ERROR),
            ['response_headers' => ['content-type: application/json']]
        );
    });

    $repositoryStub = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class)->shouldIgnoreMissing();
    $emMock = Mockery::mock(Doctrine\ORM\EntityManager::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->andReturn($repositoryStub);

    $di = new Pimple\Container();
    $di['em'] = $emMock;
    $di['cache'] = static fn (): Symfony\Component\Cache\Adapter\ArrayAdapter => new Symfony\Component\Cache\Adapter\ArrayAdapter();
    $di['http_client'] = static fn (): Symfony\Component\HttpClient\MockHttpClient => $httpClient;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service = new Box\Mod\Currency\Service();
    $service->setDi($di);

    $provider = new ReflectionMethod($service, 'getCurrencyDataRates');
    $provider->setAccessible(true);
    $rates = $provider->invoke($service, 'USD', 3600, 'test-key');

    $headers = json_encode($capturedRequests[0]['options']['headers'] ?? [], JSON_THROW_ON_ERROR);

    expect($capturedRequests)->toHaveCount(1)
        ->and($capturedRequests[0]['url'])->toStartWith('https://api.apilayer.com/currency_data/live')
        ->and($capturedRequests[0]['url'])->toContain('source=USD')
        ->and($headers)->toContain('apikey')
        ->and($headers)->toContain('test-key')
        ->and($rates)->toBe(['EUR' => 0.9, 'GBP' => 0.8]);
});
