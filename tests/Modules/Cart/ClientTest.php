<?php

declare(strict_types=1);

namespace CartTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function testDoesCartTransferOnLogin(): void
    {
        // First we need a dummy product
        $result = Request::makeRequest('admin/product/prepare', [
            'title' => 'Dummy Product',
            'type' => 'custom',
            'product_category_id' => 1,
        ]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsNumeric($result->getResult());
        $productID = intval($result->getResult());

        // Now configure it
        $result = Request::makeRequest('admin/product/update', [
            'id' => $productID,
            'status' => 'enabled',
            'pricing' => [
                'type' => 'free',
            ],
        ]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertTrue($result->getResult());

        // Next add that product to the cart
        $result = Request::makeRequest('guest/cart/add_item', [
            'id' => $productID,
        ]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertTrue($result->getResult());

        // Get the cart as-is
        $result = Request::makeRequest('guest/cart/get');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());
        $startingCart = $result->getResult();

        // Generate a new test user
        $password = 'A1a' . bin2hex(random_bytes(6));
        $result = Request::makeRequest('guest/client/create', [
            'email' => 'client@example.com',
            'first_name' => 'Test',
            'password' => $password,
            'password_confirm' => $password,
        ]);

        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsNumeric($result->getResult());

        $id = intval($result->getResult());

        // Login as that user
        $result = Request::makeRequest('guest/client/login', [
            'email' => 'client@example.com',
            'password' => $password,
        ]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        // Get the cart again and verify it matches the original one
        $result = Request::makeRequest('guest/cart/get');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());
        $this->assertEquals($startingCart, $result->getResult());

        // Finally perform some cleanup
        $result = Request::makeRequest('admin/client/delete', ['id' => $id]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertTrue($result->getResult());
        Request::resetCookies();
    }
}
