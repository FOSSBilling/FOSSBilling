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

        $productTable = $this->getMockBuilder(\Model_ProductTable::class)
            ->onlyMethods(['getOrderLineConfig', 'getUnit'])
            ->getMock();
        $productTable->expects($this->once())
            ->method('getOrderLineConfig')
            ->willReturn([
                'price' => 33.0,
                'quantity' => 1,
                'setup_price' => 0.0,
            ]);
        $productTable->expects($this->once())
            ->method('getUnit')
            ->willReturn('year');

        $product = $this->getMockBuilder(\Model_Product::class)
            ->onlyMethods(['getTable', 'getService'])
            ->getMock();
        $product->loadBean(new \DummyBean());
        $product->id = 1;
        $product->form_id = 2;
        $product->type = 'domain';
        $product->title = 'Domain example.com registration';
        $product->expects($this->exactly(2))
            ->method('getTable')
            ->willReturn($productTable);
        $product->expects($this->once())
            ->method('getService')
            ->willReturn(new \stdClass());

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
        $db->expects($this->atLeast(4))
            ->method('load')
            ->willReturnCallback(static fn (string $model) => $model === 'Cart' ? $cart : $product);

        $di = $this->getDi();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->cartProductToApiArray($cartProduct);

        $this->assertSame(1, $result['quantity']);
        $this->assertSame(33.0, $result['price']);
        $this->assertSame(33.0, $result['total']);
    }
}
