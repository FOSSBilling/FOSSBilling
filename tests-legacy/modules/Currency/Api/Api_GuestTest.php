<?php

namespace Box\Tests\Mod\Currency\Api;

class Api_GuestTest extends \BBTestCase
{
    public function testGetPairs(): void
    {
        $guestApi = new \Box\Mod\Currency\Api\Guest();

        $willReturn = [
            'EUR' => 'Euro',
            'USD' => 'US Dollar',
        ];

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getPairs')
            ->willReturn($willReturn);

        $guestApi->setService($service);

        $result = $guestApi->get_pairs([]);
        $this->assertEquals($result, $willReturn);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('EUR', $result);
        $this->assertArrayHasKey('USD', $result);
    }

    public static function getProvider(): array
    {
        $self = new Api_GuestTest('Api_GuestTest');

        $model = new \Model_Currency();

        return [
            [
                [
                    'code' => 'EUR',
                ],
                $model,
                $self->atLeastOnce(),
                $self->never(),
            ],
            [
                [],
                $model,
                $self->never(),
                $self->atLeastOnce(),
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getProvider')]
    public function testGet(array $data, \Model_Currency $model, \PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce|\PHPUnit\Framework\MockObject\Rule\InvokedCount $expectsGetByCode, \PHPUnit\Framework\MockObject\Rule\InvokedCount|\PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce $expectsGetDefault): void
    {
        $guestApi = new \Box\Mod\Currency\Api\Guest();

        $willReturn = [
            'code' => 'EUR',
            'title' => 'Euro',
            'conversion_rate' => 1,
            'format' => '{{price}}',
            'price_format' => 1,
            'default' => 1,
        ];

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($expectsGetByCode)
            ->method('getByCode')
            ->willReturn($model);

        $service->expects($expectsGetDefault)
            ->method('getDefault')
            ->willReturn($model);

        $service->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($willReturn);

        $guestApi->setService($service);

        $result = $guestApi->get($data);
        $this->assertIsArray($result);
        $this->assertEquals($result, $willReturn);
    }

    public function testGetException(): void
    {
        $guestApi = new \Box\Mod\Currency\Api\Guest();

        $willReturn = [
            'code' => 'EUR',
            'title' => 'Euro',
            'conversion_rate' => 1,
            'format' => '{{price}}',
            'price_format' => 1,
            'default' => 1,
        ];

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->never())
            ->method('getByCode')
            ->willReturn(null);

        $service->expects($this->atLeastOnce())
            ->method('getDefault')
            ->willReturn(null);

        $guestApi->setService($service);
        $this->expectException(\FOSSBilling\Exception::class);
        $result = $guestApi->get([]); // Expecting \FOSSBilling\Exception
    }

    public static function formatPriceFormatProvider(): array
    {
        return [
            [
                1,
                '€ 60000.00',
            ],
            [
                2,
                '€ 60,000.00',
            ],
            [
                3,
                '€ 60.000,00',
            ],
            [
                4,
                '€ 60,000',
            ],
            [
                5,
                '€ 60000',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('formatPriceFormatProvider')]
    public function testFormatPriceFormat(int $price_format, string $expectedResult): void
    {
        $willReturn = [
            'code' => 'EUR',
            'title' => 'Euro',
            'conversion_rate' => 0.6,
            'format' => '€ {{price}}',
            'price_format' => $price_format,
            'default' => 1,
        ];

        $data = [
            'code' => 'EUR',
            'price' => 100000,
            'without_currency' => false,
        ];
        $guestApi = $this->getMockBuilder('\\' . \Box\Mod\Currency\Api\Guest::class)->onlyMethods(['get'])->getMock();
        $guestApi->expects($this->atLeastOnce())
            ->method('get')
            ->willReturn($willReturn);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();

        $di = new \Pimple\Container();

        $guestApi->setDi($di);
        $guestApi->setService($service);

        $result = $guestApi->format($data);
        $this->assertEquals($result, $expectedResult);
    }

    public static function formatProvider(): array
    {
        return [
            [
                [
                    'code' => 'EUR',
                ],
                '€ 0.00', // price is not set, so result should be 0
            ],
            [
                [
                    'code' => 'EUR',
                    'price' => 100000,
                    'convert' => false, // Should not convert
                ],
                '€ 100000.00',
            ],
            [
                [
                    'code' => 'EUR',
                    'price' => 100000,
                    'without_currency' => true, // Should return number only
                ],
                '60000.00',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('formatProvider')]
    public function testFormat(array $data, string $expectedResult): void
    {
        $willReturn = [
            'code' => 'EUR',
            'title' => 'Euro',
            'conversion_rate' => 0.6,
            'format' => '€ {{price}}',
            'price_format' => 1,
            'default' => 1,
        ];

        $guestApi = $this->getMockBuilder('\\' . \Box\Mod\Currency\Api\Guest::class)->onlyMethods(['get'])->getMock();
        $guestApi->expects($this->atLeastOnce())
            ->method('get')
            ->willReturn($willReturn);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();

        $di = new \Pimple\Container();

        $guestApi->setDi($di);
        $guestApi->setService($service);

        $result = $guestApi->format($data);
        $this->assertEquals($result, $expectedResult);
    }
}
