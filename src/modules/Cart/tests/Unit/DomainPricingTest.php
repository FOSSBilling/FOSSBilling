<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Cart\Service;
use Box\Mod\Product\Service as ProductService;

use function Tests\Helpers\container;

test('cartProductToApiArray uses resolved initial domain term pricing', function (): void {
    $service = new Service();

    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());

    $cartProduct = new Model_CartProduct();
    $cartProduct->loadBean(new Tests\Helpers\DummyBean());
    $cartProduct->id = 10;
    $cartProduct->cart_id = 20;
    $cartProduct->product_id = 1;
    $cartProduct->config = json_encode([
        'action' => 'register',
        'register_sld' => 'example',
        'register_tld' => '.com',
        'register_years' => 2,
        'period' => '2Y',
    ]);

    $db = Mockery::mock(Box_Database::class);
    $db->shouldReceive('load')->once()->with('Cart', $cartProduct->cart_id)->andReturn($cart);
    $db->shouldReceive('find')->once()->with('CartProduct', 'cart_id = :cart_id ORDER BY id ASC', [':cart_id' => $cart->id])->andReturn([]);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('getCartProductViewData')->once()->with($cartProduct)->andReturn([
        'product_id' => 1,
        'form_id' => 2,
        'type' => 'domain',
        'quantity' => 1,
        'unit' => 'year',
        'price' => 33.0,
        'setup_price' => 0.0,
        'title' => 'Domain example.com registration',
        'config' => [
            'action' => 'register',
            'register_sld' => 'example',
            'register_tld' => '.com',
            'register_years' => 2,
            'period' => '2Y',
        ],
    ]);
    $productService->shouldReceive('getRelatedProductDiscountByProductId')
        ->once()
        ->with(1, [], [
            'action' => 'register',
            'register_sld' => 'example',
            'register_tld' => '.com',
            'register_years' => 2,
            'period' => '2Y',
        ])
        ->andReturn(0.0);

    $di = container();
    $di['db'] = $db;
    $di['mod_service'] = $di->protect(function (string $serviceName) use ($productService) {
        if ($serviceName === 'Product') {
            return $productService;
        }

        throw new RuntimeException('Unexpected service request');
    });
    $service->setDi($di);

    $result = $service->cartProductToApiArray($cartProduct);

    expect($result['quantity'])->toBe(1);
    expect($result['price'])->toBe(33.0);
    expect($result['total'])->toBe(33.0);
    expect($result['title'])->toBe('Domain example.com registration');
    expect($result['unit'])->toBe('year');
});
