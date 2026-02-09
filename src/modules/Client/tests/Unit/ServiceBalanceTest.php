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

beforeEach(function () {
    $this->service = new \Box\Mod\Client\ServiceBalance();
});

test('getDi returns dependency injection container', function () {
    $di = container();
    $this->service->setDi($di);
    $getDi = $this->service->getDi();
    expect($getDi)->toEqual($di);
});

test('deductFunds creates balance record', function () {
    $di = container();

    $clientBalance = new \Model_ClientBalance();
    $clientBalance->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->with('ClientBalance')
        ->atLeast()->once()
        ->andReturn($clientBalance);
    $dbMock->shouldReceive('store')
        ->with($clientBalance)
        ->atLeast()->once();
    $di['db'] = $dbMock;

    $this->service->setDi($di);

    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());

    $description = 'Charged for product';
    $amount = 5.55;

    $extra = [
        'rel_id' => 1,
    ];

    $result = $this->service->deductFunds($clientModel, $amount, $description, $extra);

    expect($result)->toBeInstanceOf(\Model_ClientBalance::class);
    expect($result->amount)->toEqual(-$amount);
    expect($result->description)->toEqual($description);
    expect($result->rel_id)->toEqual($extra['rel_id']);
    expect($result->type)->toEqual('default');
});

test('deductFunds throws exception for invalid description', function () {
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());

    $description = '    ';
    $amount = 5.55;

    $extra = [
        'rel_id' => 1,
    ];

    $this->service->deductFunds($clientModel, $amount, $description, $extra);
})->throws(\FOSSBilling\Exception::class, 'Funds description is invalid');

test('deductFunds throws exception for invalid amount', function () {
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());

    $description = 'Charged';
    $amount = '5.5adadzxc';

    $extra = [
        'rel_id' => 1,
    ];

    $this->service->deductFunds($clientModel, $amount, $description, $extra);
})->throws(\FOSSBilling\Exception::class, 'Funds amount is invalid');
