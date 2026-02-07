<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace FOSSBilling\Tests\E2E\Modules\Cart;

use FOSSBilling\Tests\Library\E2E\TestCase;
use FOSSBilling\Tests\Library\E2E\ApiClient;

final class ApiGuestTest extends TestCase
{
    public function testCartTransfersOnLogin(): void
    {
        $productId = $this->createDummyProduct();

        $this->enableProduct($productId);

        $this->addProductToCart($productId);

        $startingCart = $this->getCart();

        $clientId = $this->createClient();

        $this->loginClient($clientId);

        $loggedInCart = $this->getCart();
        $this->assertEquals($startingCart, $loggedInCart);

        $this->cleanupClient($clientId);
    }

    private function createDummyProduct(): int
    {
        $result = ApiClient::request('admin/product/prepare', [
            'title' => 'Dummy Product',
            'type' => 'custom',
            'product_category_id' => 1,
        ]);
        $this->assertApiSuccess($result);
        $this->assertApiResultIsInt($result);

        return (int) $result->getResult();
    }

    private function enableProduct(int $productId): void
    {
        $result = ApiClient::request('admin/product/update', [
            'id' => $productId,
            'status' => 'enabled',
            'pricing' => ['type' => 'free'],
        ]);
        $this->assertApiSuccess($result);
        $this->assertTrue($result->getResult());
    }

    private function addProductToCart(int $productId): void
    {
        $result = ApiClient::request('guest/cart/add_item', ['id' => $productId]);
        $this->assertApiSuccess($result);
        $this->assertTrue($result->getResult());
    }

    private function getCart(): array
    {
        $result = ApiClient::request('guest/cart/get');
        $this->assertApiSuccess($result);
        $this->assertApiResultIsArray($result);

        return $result->getResult();
    }

    private function createClient(): int
    {
        $password = 'A1a' . bin2hex(random_bytes(6));
        $result = ApiClient::request('guest/client/create', [
            'email' => 'client_' . uniqid() . '@example.com',
            'first_name' => 'Test',
            'password' => $password,
            'password_confirm' => $password,
        ]);
        $this->assertApiSuccess($result);
        $this->assertApiResultIsInt($result);

        return (int) $result->getResult();
    }

    private function loginClient(int $clientId): void
    {
        $result = ApiClient::request('guest/client/login', [
            'email' => 'client_' . $clientId . '@example.com',
            'password' => 'A1a' . bin2hex(random_bytes(6)),
        ]);
        $this->assertApiSuccess($result);
    }

    private function cleanupClient(int $clientId): void
    {
        $result = ApiClient::request('admin/client/delete', ['id' => $clientId]);
        $this->assertApiSuccess($result);
        $this->assertTrue($result->getResult());
    }
}
