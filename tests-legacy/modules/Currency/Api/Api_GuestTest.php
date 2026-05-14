<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Currency\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class Api_GuestTest extends \BBTestCase
{
    public function testGetPairs(): void
    {
        $guestApi = new \Box\Mod\Currency\Api\Guest();

        $willReturn = [
            'EUR' => 'Euro',
            'USD' => 'US Dollar',
        ];

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('getPairs')
            ->willReturn($willReturn);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($repositoryMock);

        $guestApi->setService($service);

        $result = $guestApi->get_pairs([]);
        $this->assertEquals($result, $willReturn);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('EUR', $result);
        $this->assertArrayHasKey('USD', $result);
    }

    public static function getProvider(): array
    {
        $willReturn = [
            'code' => 'EUR',
            'title' => 'Euro',
            'conversion_rate' => 1.0,
            'default' => true,
        ];

        // Use a placeholder since we can't create mocks in static context
        return [
            [
                ['code' => 'EUR'],
                'has_model', // flag to indicate model should be created
                'atLeastOnce',
                'never',
            ],
            [
                [],
                'has_model', // flag to indicate model should be created
                'never',
                'atLeastOnce',
            ],
        ];
    }

    #[DataProvider('getProvider')]
    public function testGet(array $data, string $modelFlag, string $expectsGetByCode, string $expectsGetDefault): void
    {
        $guestApi = new \Box\Mod\Currency\Api\Guest();

        $willReturn = [
            'code' => 'EUR',
            'title' => 'Euro',
            'conversion_rate' => 1,
            'default' => 1,
        ];

        // Create model mock based on flag
        $model = ($modelFlag === 'has_model')
            ? $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
                ->disableOriginalConstructor()
                ->getMock()
            : null;

        // Configure the entity mock to return expected values
        if ($model !== null) {
            $model->expects($this->atLeastOnce())
                ->method('toApiArray')
                ->willReturn($willReturn);
        }

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->$expectsGetByCode())
            ->method('findOneByCode')
            ->willReturn($model);
        $repositoryMock->expects($this->$expectsGetDefault())
            ->method('findDefault')
            ->willReturn($model);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($repositoryMock);

        $guestApi->setService($service);
        $di = $this->getDi();
        $guestApi->setDi($di);

        $result = $guestApi->get($data);
        $this->assertIsArray($result);
        $this->assertEquals($result, $willReturn);
    }

    public function testGetException(): void
    {
        $guestApi = new \Box\Mod\Currency\Api\Guest();

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->never())
            ->method('findOneByCode');
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findDefault')
            ->willReturn(null);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($repositoryMock);

        $guestApi->setService($service);
        $this->expectException(\FOSSBilling\Exception::class);
        $result = $guestApi->get([]); // Expecting \FOSSBilling\Exception
    }

    public static function formatProvider(): array
    {
        return [
            [
                [
                    'code' => 'EUR',
                ],
                '€0.00',
            ],
            [
                [
                    'code' => 'EUR',
                    'price' => 100000,
                    'convert' => false,
                ],
                '€100,000.00',
            ],
            [
                [
                    'code' => 'EUR',
                    'price' => 100000,
                    'without_currency' => true,
                ],
                '60,000.00',
            ],
            [
                [
                    'code' => 'EUR',
                    'price' => -100000,
                    'convert' => false,
                ],
                '-€100,000.00',
            ],
            [
                [
                    'code' => 'JPY',
                    'price' => 100000,
                    'without_currency' => true,
                    'convert' => false,
                ],
                '100,000',
            ],
        ];
    }

    #[DataProvider('formatProvider')]
    public function testFormat(array $data, string $expectedResult): void
    {
        $willReturn = [
            'code' => $data['code'],
            'title' => 'Currency',
            'conversion_rate' => 0.6,
            'default' => 1,
        ];

        $guestApi = $this->getMockBuilder(\Box\Mod\Currency\Api\Guest::class)->onlyMethods(['get'])->getMock();
        $guestApi->expects($this->atLeastOnce())
            ->method('get')
            ->willReturn($willReturn);

        $service = $this->createMock(\Box\Mod\Currency\Service::class);

        $di = $this->getDi();

        $guestApi->setDi($di);
        $guestApi->setService($service);

        $result = $guestApi->format($data);
        $this->assertEquals($result, $expectedResult);
    }
}
