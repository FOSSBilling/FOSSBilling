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
use function Tests\Helpers\createEntity;

test('getDi returns dependency injection container', function (): void {
    $service = new Box\Mod\Client\ServiceBalance();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toEqual($di);
});

test('builds a Doctrine query for balance searches', function (): void {
    $queryBuilder = Mockery::mock(Doctrine\ORM\QueryBuilder::class);
    $queryBuilder->shouldReceive('andWhere')->once()->with('m.id = :id')->andReturnSelf();
    $queryBuilder->shouldReceive('setParameter')->once()->with('id', 7)->andReturnSelf();
    $queryBuilder->shouldReceive('andWhere')->once()->with('m.clientId = :client_id')->andReturnSelf();
    $queryBuilder->shouldReceive('setParameter')->once()->with('client_id', 3)->andReturnSelf();
    $queryBuilder->shouldReceive('andWhere')->once()->with('m.createdAt >= :date_from')->andReturnSelf();
    $queryBuilder->shouldReceive('setParameter')->once()->with('date_from', Mockery::on(
        static fn (mixed $date): bool => $date instanceof DateTimeImmutable && $date->format('Y-m-d H:i:s') === '2012-12-10 00:00:00',
    ))->andReturnSelf();
    $queryBuilder->shouldReceive('andWhere')->once()->with('m.createdAt <= :date_to')->andReturnSelf();
    $queryBuilder->shouldReceive('setParameter')->once()->with('date_to', Mockery::on(
        static fn (mixed $date): bool => $date instanceof DateTimeImmutable && $date->format('Y-m-d H:i:s') === '2012-12-11 00:00:00',
    ))->andReturnSelf();
    $queryBuilder->shouldReceive('orderBy')->once()->with('m.id', 'DESC')->andReturnSelf();

    $balanceRepository = Mockery::mock(Box\Mod\Client\Repository\ClientBalanceRepository::class);
    $balanceRepository->shouldReceive('createQueryBuilder')->once()->with('m')->andReturn($queryBuilder);

    $service = new Box\Mod\Client\ServiceBalance();
    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Client\Entity\ClientBalance::class)
        ->andReturn($balanceRepository);
    $service->setDi($di);

    expect($service->getSearchQueryBuilder([
        'id' => 7,
        'client_id' => 3,
        'date_from' => '2012-12-10',
        'date_to' => '2012-12-11',
    ]))->toBe($queryBuilder);
});

test('toApiArray uses a supplied client without reloading it', function (): void {
    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 3, 'currency' => 'USD']);
    $balance = createEntity(Box\Mod\Client\Entity\ClientBalance::class, [
        'id' => 7,
        'client_id' => 3,
        'amount' => '12.50',
    ]);

    $clientRepository = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepository->shouldReceive('find')->never();
    $balanceRepository = Mockery::mock(Box\Mod\Client\Repository\ClientBalanceRepository::class);

    $service = new Box\Mod\Client\ServiceBalance();
    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Client\Entity\Client::class)
        ->andReturn($clientRepository);
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Client\Entity\ClientBalance::class)
        ->andReturn($balanceRepository);
    $service->setDi($di);

    expect($service->toApiArray($balance, $client))->toMatchArray([
        'id' => 7,
        'amount' => '12.50',
        'currency' => 'USD',
    ]);
});

test('deductFunds creates balance record', function (): void {
    $service = new Box\Mod\Client\ServiceBalance();
    $di = container();

    $service->setDi($di);

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);

    $description = 'Charged for product';
    $amount = 5.55;

    $extra = [
        'rel_id' => 1,
    ];

    $result = $service->deductFunds($clientModel, $amount, $description, $extra);

    expect($result)->toBeInstanceOf(Box\Mod\Client\Entity\ClientBalance::class);
    expect($result->getAmount())->toEqual((string) (-$amount));
    expect($result->getDescription())->toEqual($description);
    expect($result->getRelId())->toEqual($extra['rel_id']);
    expect($result->getType())->toEqual('default');
});

test('deductFunds throws exception for invalid description', function (): void {
    $service = new Box\Mod\Client\ServiceBalance();
    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);

    $description = '    ';
    $amount = 5.55;

    $extra = [
        'rel_id' => 1,
    ];

    $service->deductFunds($clientModel, $amount, $description, $extra);
})->throws(FOSSBilling\Exception::class, 'Funds description is invalid');

test('deductFunds throws exception for invalid amount', function (): void {
    $service = new Box\Mod\Client\ServiceBalance();
    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);

    $description = 'Charged';
    $amount = '5.5adadzxc';

    $extra = [
        'rel_id' => 1,
    ];

    $service->deductFunds($clientModel, $amount, $description, $extra);
})->throws(FOSSBilling\Exception::class, 'Funds amount is invalid');
