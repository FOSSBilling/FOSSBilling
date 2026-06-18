<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Cart;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class DomainPricingTest extends \BBTestCase
{
    public function testCartProductToApiArrayUsesResolvedInitialDomainTermPricing(): void
    {
        $service = new \Box\Mod\Cart\Service();

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());

        $cartProduct = new \Model_CartProduct();
        $cartProduct->loadBean(new \DummyBean());
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

        $db = $this->createMock('Box_Database');
        $db->expects($this->once())
            ->method('load')
            ->with('Cart', $cartProduct->cart_id)
            ->willReturn($cart);
        $db->expects($this->once())
            ->method('find')
            ->with('CartProduct', 'cart_id = :cart_id ORDER BY id ASC', [':cart_id' => $cart->id])
            ->willReturn([]);

        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('getCartProductViewData')
            ->with($cartProduct)
            ->willReturn([
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
        $productService->expects($this->once())
            ->method('getRelatedProductDiscountByProductId')
            ->with(1, [], [
                'action' => 'register',
                'register_sld' => 'example',
                'register_tld' => '.com',
                'register_years' => 2,
                'period' => '2Y',
            ])
            ->willReturn(0.0);

        $di = $this->getDi();
        $di['db'] = $db;
        $di['mod_service'] = $di->protect(function (string $serviceName) use ($productService) {
            if ($serviceName === 'Product') {
                return $productService;
            }

            throw new \RuntimeException('Unexpected service request');
        });
        $service->setDi($di);

        $result = $service->cartProductToApiArray($cartProduct);

        $this->assertSame(1, $result['quantity']);
        $this->assertSame(33.0, $result['price']);
        $this->assertSame(33.0, $result['total']);
        $this->assertSame('Domain example.com registration', $result['title']);
        $this->assertSame('year', $result['unit']);
    }
}
