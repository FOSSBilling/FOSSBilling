<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

// Skip E2E tests if environment is not configured
if (!getenv('APP_URL') || !getenv('TEST_API_KEY')) {
    return;
}

use function Tests\Helpers\assertApiSuccess;
use function Tests\Helpers\assertApiResultIsInt;
use function Tests\Helpers\assertApiResultIsArray;

test('cart transfers on login', function () {
    $productId = cartCreateDummyProduct();

    cartEnableProduct($productId);

    cartAddProductToCart($productId);

    $startingCart = cartGetCart();

    $clientId = cartCreateClient();

    cartLoginClient($clientId);

    $loggedInCart = cartGetCart();
    expect($loggedInCart)->toEqual($startingCart);

    cartCleanupClient($clientId);
});

function cartCreateDummyProduct(): int
{
    $result = \Tests\Helpers\ApiClient::request('admin/product/prepare', [
        'title' => 'Dummy Product',
        'type' => 'custom',
        'product_category_id' => 1,
    ]);
    assertApiSuccess($result);
    assertApiResultIsInt($result);

    return (int) $result->getResult();
}

function cartEnableProduct(int $productId): void
{
    $result = \Tests\Helpers\ApiClient::request('admin/product/update', [
        'id' => $productId,
        'status' => 'enabled',
        'pricing' => ['type' => 'free'],
    ]);
    assertApiSuccess($result);
    expect($result->getResult())->toBeTrue();
}

function cartAddProductToCart(int $productId): void
{
    $result = \Tests\Helpers\ApiClient::request('guest/cart/add_item', ['id' => $productId]);
    assertApiSuccess($result);
    expect($result->getResult())->toBeTrue();
}

function cartGetCart(): array
{
    $result = \Tests\Helpers\ApiClient::request('guest/cart/get');
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function cartCreateClient(): int
{
    $password = 'A1a' . bin2hex(random_bytes(6));
    $result = \Tests\Helpers\ApiClient::request('guest/client/create', [
        'email' => 'client_' . uniqid() . '@example.com',
        'first_name' => 'Test',
        'password' => $password,
        'password_confirm' => $password,
    ]);
    assertApiSuccess($result);
    assertApiResultIsInt($result);

    return (int) $result->getResult();
}

function cartLoginClient(int $clientId): void
{
    $result = \Tests\Helpers\ApiClient::request('guest/client/login', [
        'email' => 'client_' . $clientId . '@example.com',
        'password' => 'A1a' . bin2hex(random_bytes(6)),
    ]);
    assertApiSuccess($result);
}

function cartCleanupClient(int $clientId): void
{
    $result = \Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $clientId]);
    assertApiSuccess($result);
    expect($result->getResult())->toBeTrue();
}
