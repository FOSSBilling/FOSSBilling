<?php

namespace Box\Tests\Mod\Currency;

class ServiceTest extends \BBTestCase
{
    public function testDi(): void
    {
        $service = new \Box\Mod\Currency\Service();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $di['db'] = $db;
        $di['em'] = $emMock;
        $service->setDi($di);
        $result = $service->getDi();
        $this->assertEquals($di, $result);
    }

    public function testGetBaseCurrencyRate(): void
    {
        $service = new \Box\Mod\Currency\Service();
        $rate = 0.6;
        $expected = 1 / $rate;

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('getRateByCode')
            ->willReturn($rate);

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $di = new \Pimple\Container();
        $di['em'] = $emMock;
        $service->setDi($di);
        $code = 'EUR';
        $result = $service->getBaseCurrencyRate($code);
        $this->assertEquals($expected, $result);
    }

    public function testGetBaseCurrencyRateException(): void
    {
        $service = new \Box\Mod\Currency\Service();

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('getRateByCode')
            ->willReturn(0.0);

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $di = new \Pimple\Container();
        $di['em'] = $emMock;
        $service->setDi($di);
        $code = 'EUR';
        $this->expectException(\FOSSBilling\Exception::class);
        $service->getBaseCurrencyRate($code); // Expecting exception
    }

    public static function toBaseCurrencyProvider(): array
    {
        return [
            ['EUR', 'USD', 100, 0.73, 73], // 100 USD ~ 72.99 EUR
            ['USD', 'EUR', 100, 1.37, 137], // 100 Eur  ~ 136.99 USD
            ['EUR', 'EUR', 100, 0.5, 100], // should return same amount
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('toBaseCurrencyProvider')]
    public function testToBaseCurrency(string $defaultCode, string $foreignCode, int $amount, float $rate, int $expected): void
    {
        $model = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $model->expects($this->atLeastOnce())
            ->method('getCode')
            ->willReturn($defaultCode);

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findDefault')
            ->willReturn($model);

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['getBaseCurrencyRate'])
            ->getMock();

        $serviceMock->expects($this->any()) // will not be called when currencies are the same, so using any()
            ->method('getBaseCurrencyRate')
            ->willReturn($rate);

        $di = new \Pimple\Container();
        $di['em'] = $emMock;
        $serviceMock->setDi($di);

        $result = $serviceMock->toBaseCurrency($foreignCode, $amount);

        $this->assertEquals($expected, round($result, 2));
    }

    public static function getCurrencyByClientIdProvider(): array
    {
        $self = new ServiceTest('ServiceTest');

        return [
            [
                'USD',
                'findOneByCode',
                $self->atLeastOnce(),
                $self->never(),
            ],
            [
                null,
                'findDefault',
                $self->never(),
                $self->atLeastOnce(),
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getCurrencyByClientIdProvider')]
    public function testGetCurrencyByClientId(?string $currency, string $expectedMethod, \PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce|\PHPUnit\Framework\MockObject\Rule\InvokedCount $expectsFindOneByCode, \PHPUnit\Framework\MockObject\Rule\InvokedCount|\PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce $expectsFindDefault): void
    {
        $model = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn($currency);

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($expectsFindDefault)
            ->method('findDefault')
            ->willReturn($model);
        $repositoryMock->expects($expectsFindOneByCode)
            ->method('findOneByCode')
            ->willReturn($model);

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $di['db'] = $db;
        $di['em'] = $emMock;

        $service = new \Box\Mod\Currency\Service();
        $service->setDi($di);

        $result = $service->getCurrencyByClientId(1);

        $this->assertInstanceOf('\\' . \Box\Mod\Currency\Entity\Currency::class, $result);
    }

    public static function getRateByCodeProvider(): array
    {
        return [
            ['EUR', 0.6, 0.6],
            ['GBP', null, null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getRateByCodeProvider')]
    public function testGetRateByCode(string $code, ?float $returns, ?float $expected): void
    {
        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('getRateByCode')
            ->willReturn($returns);

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $di = new \Pimple\Container();
        $di['em'] = $emMock;

        $service = new \Box\Mod\Currency\Service();
        $service->setDi($di);

        // Access repository directly since getBaseCurrencyRate uses it internally
        $result = $service->getCurrencyRepository()->getRateByCode($code);
        $this->assertEquals($expected, $result);
    }

    public static function setAsDefaultProvider(): array
    {
        $self = new ServiceTest('ServiceTest');

        $firstModel = $self->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $firstModel->expects($self->any())
            ->method('getCode')
            ->willReturn('USD');
        $firstModel->expects($self->any())
            ->method('isDefault')
            ->willReturn(false);

        $secondModel = $self->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $secondModel->expects($self->any())
            ->method('getCode')
            ->willReturn('USD');
        $secondModel->expects($self->any())
            ->method('isDefault')
            ->willReturn(true);

        return [
            [$firstModel, $self->atLeastOnce()],
            [$secondModel, $self->never()],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('setAsDefaultProvider')]
    public function testSetAsDefault($model, \PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce|\PHPUnit\Framework\MockObject\Rule\InvokedCount $expects): void
    {
        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($expects)
            ->method('clearDefaultFlags');
        $repositoryMock->expects($expects)
            ->method('invalidateDefaultCache');

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);
        $emMock->expects($expects)
            ->method('persist');
        $emMock->expects($expects)
            ->method('flush');

        $di = new \Pimple\Container();
        $di['em'] = $emMock;
        $di['logger'] = new \Box_Log();

        $service = new \Box\Mod\Currency\Service();
        $service->setDi($di);
        $result = $service->setAsDefault($model);

        $this->assertTrue($result);
    }

    public function testSetAsDefaultException(): void
    {
        $model = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $model->expects($this->any())
            ->method('isDefault')
            ->willReturn(false);
        $model->expects($this->any())
            ->method('getCode')
            ->willReturn(null);

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $di = new \Pimple\Container();
        $di['em'] = $emMock;

        $service = new \Box\Mod\Currency\Service();
        $service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $service->setAsDefault($model); // Currency code is null, should throw an \FOSSBilling\Exception
    }

    public function testgetPairs(): void
    {
        $pairs = [
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
            'GBP' => 'Pound Sterling',
        ];

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('getPairs')
            ->willReturn($pairs);

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $di = new \Pimple\Container();
        $di['em'] = $emMock;

        $service = new \Box\Mod\Currency\Service();
        $service->setDi($di);
        $result = $service->getCurrencyRepository()->getPairs();

        $this->assertEquals($result, $pairs);
    }

    public function testRmDefaultCurrencyException(): void
    {
        $model = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $model->expects($this->any())
            ->method('getCode')
            ->willReturn('EUR');
        $model->expects($this->any())
            ->method('isDefault')
            ->willReturn(true);

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $di = new \Pimple\Container();
        $di['em'] = $emMock;

        $service = new \Box\Mod\Currency\Service();
        $service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $service->rm($model); // will throw \FOSSBilling\Exception because default currency cannot be removed
    }

    public function testRm(): void
    {
        $model = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $model->expects($this->any())
            ->method('getCode')
            ->willReturn('EUR');
        $model->expects($this->any())
            ->method('isDefault')
            ->willReturn(false);

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);
        $emMock->expects($this->atLeastOnce())
            ->method('remove')
            ->with($model);
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = new \Pimple\Container();
        $di['em'] = $emMock;

        $service = new \Box\Mod\Currency\Service();
        $service->setDi($di);
        $result = $service->rm($model);

        $this->assertEquals($result, null);
    }

    public function testRmMissingCodeException(): void
    {
        $model = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $model->expects($this->any())
            ->method('isDefault')
            ->willReturn(false);
        $model->expects($this->any())
            ->method('getCode')
            ->willReturn(null);

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $di = new \Pimple\Container();
        $di['em'] = $emMock;

        $service = new \Box\Mod\Currency\Service();
        $service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $service->rm($model); // will throw \FOSSBilling\Exception because currency code is not set
    }

    public function testToApiArray(): void
    {
        $model = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();

        $expected = [
            'code' => 'EUR',
            'title' => 'Euro',
            'conversion_rate' => 3.4528,
            'format' => '',
            'price_format' => '',
            'default' => true,
        ];

        $model->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($expected);

        $result = $model->toApiArray();
        $this->assertEquals($result, $expected);
    }

    public function testCreateCurrency(): void
    {
        $code = 'EUR';
        $format = '€{{price}}';

        $systemService = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->onlyMethods(['checkLimits'])->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('checkLimits')
            ->willReturn(null);

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);
        $emMock->expects($this->atLeastOnce())
            ->method('persist');
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['em'] = $emMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemService);

        $service = new \Box\Mod\Currency\Service();
        $service->setDi($di);

        $result = $service->createCurrency($code, $format, 'Euros', 0.6);

        $this->assertIsString($result);
        $this->assertEquals(strlen($result), 3);
        $this->assertEquals($result, $code);
    }

    public function testUpdateCurrency(): void
    {
        $code = 'EUR';
        $format = '€{{price}}';
        $title = 'Euros';
        $price_format = '€{{Price}}';
        $conversion_rate = 0.6;

        $model = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->willReturn($model);

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);
        $emMock->expects($this->atLeastOnce())
            ->method('persist');
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['em'] = $emMock;

        $service = new \Box\Mod\Currency\Service();
        $service->setDi($di);

        $result = $service->updateCurrency($code, $format, $title, $price_format, $conversion_rate);

        $this->assertIsBool($result);
        $this->assertEquals($result, true);
    }

    public function testUpdateCurrencyNotFoundException(): void
    {
        $code = 'EUR';
        $format = '€{{price}}';
        $title = 'Euros';
        $price_format = '€{{Price}}';
        $conversion_rate = 0.6;

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->willReturn(null);

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $di = new \Pimple\Container();
        $di['em'] = $emMock;

        $service = new \Box\Mod\Currency\Service();
        $service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $service->updateCurrency($code, $format, $title, $price_format, $conversion_rate); // Expecting \FOSSBilling\Exception every time
    }

    public function testUpdateConversionRateException(): void
    {
        $code = 'EUR';
        $format = '€{{price}}';
        $title = 'Euros';
        $price_format = '€{{Price}}';
        $conversion_rate = 0;

        $model = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->willReturn($model);

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $di = new \Pimple\Container();
        $di['em'] = $emMock;

        $service = new \Box\Mod\Currency\Service();
        $service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $service->updateCurrency($code, $format, $title, $price_format, $conversion_rate); // Expecting \FOSSBilling\Exception every time
    }

    public function testUpdateCurrencyRates(): void
    {
        $defaultModel = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $defaultModel->expects($this->any())
            ->method('getCode')
            ->willReturn('EUR');
        $defaultModel->expects($this->any())
            ->method('isDefault')
            ->willReturn(true);

        $otherModel = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $otherModel->expects($this->any())
            ->method('getCode')
            ->willReturn('USD');
        $otherModel->expects($this->any())
            ->method('isDefault')
            ->willReturn(false);

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findDefault')
            ->willReturn($defaultModel);
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findAll')
            ->willReturn([$defaultModel, $otherModel]);

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['_getRate'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getRate')
            ->willReturn(floatval(random_int(1, 50) / 10));

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['em'] = $emMock;
        $serviceMock->setDi($di);

        $result = $serviceMock->updateCurrencyRates();

        $this->assertIsBool($result);
        $this->assertEquals($result, true);
    }

    public function testUpdateCurrencyRatesRateNotNumeric(): void
    {
        $model = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $model->expects($this->any())
            ->method('getCode')
            ->willReturn('EUR');
        $model->expects($this->any())
            ->method('isDefault')
            ->willReturn(false);

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findDefault')
            ->willReturn($model);
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findAll')
            ->willReturn([$model]);

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['_getRate'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getRate')
            ->willReturn(0.0);

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['em'] = $emMock;
        $serviceMock->setDi($di);

        $result = $serviceMock->updateCurrencyRates();

        $this->assertIsBool($result);
        $this->assertEquals($result, true);
    }

    public function testDelete(): void
    {
        $model = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $model->expects($this->any())
            ->method('getCode')
            ->willReturn('EUR');
        $model->expects($this->any())
            ->method('isDefault')
            ->willReturn(false);

        $code = 'EUR';

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->willReturn($model);

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);
        $emMock->expects($this->atLeastOnce())
            ->method('remove');
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $manager = $this->getMockBuilder('Box_EventManager')->getMock();
        $manager->expects($this->atLeastOnce())
            ->method('fire')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['em'] = $emMock;
        $di['events_manager'] = $manager;

        $service = new \Box\Mod\Currency\Service();
        $service->setDi($di);

        $result = $service->deleteCurrencyByCode($code);

        $this->assertIsBool($result);
        $this->assertEquals($result, true);
    }

    public function testDeleteModelNotFoundException(): void
    {
        $code = 'EUR';

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->willReturn(null);

        $emMock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $di = new \Pimple\Container();
        $di['em'] = $emMock;

        $service = new \Box\Mod\Currency\Service();
        $service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $service->deleteCurrencyByCode($code);

        $this->assertIsBool($result);
        $this->assertEquals($result, true);
    }

    public function testValidateCurrencyFormatPriceTagMissing(): void
    {
        $service = new \Box\Mod\Currency\Service();

        $this->expectException(\Exception::class);
        $service->validateCurrencyFormat('$$$');
    }
}
