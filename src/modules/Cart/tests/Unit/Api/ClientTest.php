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

beforeEach(function (): void {
    $this->clientApi = new \Box\Mod\Cart\Api\Client();
});

test('checkout processes cart and returns result array', function (): void {
    $api = new \Box\Mod\Cart\Api\Client();
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()
         ->andReturn($cart);

    $checkOutCartResult = [
        'gateway_id' => 1,
        'invoice_hash' => null,
        'order_id' => 1,
        'orders' => 1,
    ];
    $serviceMock
    ->shouldReceive('checkoutCart')
    ->atLeast()->once()
    ->andReturn($checkOutCartResult);

    $this->clientApi->setService($serviceMock);

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $this->clientApi->setIdentity($client);

    $data = [
        'id' => 1,
    ];
    $di = container();

    $this->clientApi->setDi($di);
    $result = $this->clientApi->checkout($data);

    expect($result)->toBeArray();
});
