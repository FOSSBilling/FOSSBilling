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

test('checkout processes cart and returns result array', function (): void {
    $clientApi = new \Box\Mod\Cart\Api\Client();
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

    $clientApi->setService($serviceMock);

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $clientApi->setIdentity($client);

    $data = [
        'id' => 1,
    ];
    $di = container();

    $clientApi->setDi($di);
    $result = $clientApi->checkout($data);

    expect($result)->toBeArray();
});
