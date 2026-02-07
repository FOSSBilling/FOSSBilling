<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Cart\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ClientTest extends \BBTestCase
{
    protected ?\Box\Mod\Cart\Api\Client $clientApi;

    public function setUp(): void
    {
        $this->clientApi = new \Box\Mod\Cart\Api\Client();
    }

    public function testCheckout(): void
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
             ->onlyMethods(['getSessionCart', 'checkoutCart'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
             ->willReturn($cart);

        $checkOutCartResult = [
            'gateway_id' => 1,
            'invoice_hash' => null,
            'order_id' => 1,
            'orders' => 1,
        ];
        $serviceMock->expects($this->atLeastOnce())
            ->method('checkoutCart')
            ->willReturn($checkOutCartResult);

        $this->clientApi->setService($serviceMock);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $this->clientApi->setIdentity($client);

        $data = [
            'id' => 1,
        ];
        $di = $this->getDi();

        $this->clientApi->setDi($di);
        $result = $this->clientApi->checkout($data);

        $this->assertIsArray($result);
    }
}
