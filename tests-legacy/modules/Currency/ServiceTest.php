<?php

namespace Box\Tests\Mod\Currency;

class ServiceTest extends \BBTestCase
{
    public function testDi(): void
    {
        $service = new \Box\Mod\Currency\Service();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $di['db'] = $db;
        $service->setDi($di);
        $result = $service->getDi();
        $this->assertEquals($di, $result);
    }

    public function testGetSearchQuery(): void
    {
        $service = new \Box\Mod\Currency\Service();
        $result = $service->getSearchQuery();
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);
        $this->assertEquals('SELECT * FROM currency WHERE 1', $result[0]);
    }

    public function testGetBaseCurrencyRate(): void
    {
        $service = new \Box\Mod\Currency\Service();
        $rate = 0.6;
        $expected = 1 / $rate;
        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn($rate);

        $di['db'] = $db;
        $service->setDi($di);
        $code = 'EUR';
        $result = $service->getBaseCurrencyRate($code);
        $this->assertEquals($expected, $result);
    }

    public function testGetBaseCurrencyRateException(): void
    {
        $service = new \Box\Mod\Currency\Service();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(0);

        $di['db'] = $db;
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
        $model = new \Model_Currency();
        $bean = new \DummyBean();
        $bean->code = $defaultCode;
        $model->loadBean($bean);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['getDefault', 'getBaseCurrencyRate'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getDefault')
            ->willReturn($model);

        $serviceMock->expects($this->any()) // will not be called when currencies are the same, so using any()
            ->method('getBaseCurrencyRate')
            ->willReturn($rate);

        $result = $serviceMock->toBaseCurrency($foreignCode, $amount);

        $this->assertEquals($expected, round($result, 2));
    }

    public static function getCurrencyByClientIdProvider(): array
    {
        $self = new ServiceTest('ServiceTest');

        $model = new \Model_Currency();
        $bean = new \DummyBean();
        $model->loadBean($bean);

        return [
            [
                $model,
                'USD',
                $self->atLeastOnce(),
                $self->never(),
            ],
            [
                $model,
                null,
                $self->never(),
                $self->atLeastOnce(),
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getCurrencyByClientIdProvider')]
    public function testGetCurrencyByClientId(\Model_Currency $row, ?string $currency, \PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce|\PHPUnit\Framework\MockObject\Rule\InvokedCount $expectsGetByCode, \PHPUnit\Framework\MockObject\Rule\InvokedCount|\PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce $getDefaultCalled): void
    {
        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $db->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn($currency);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->onlyMethods(['getDefault', 'getByCode'])->getMock();

        $serviceMock->expects($getDefaultCalled)
            ->method('getDefault')
            ->willReturn($row);

        $serviceMock->expects($expectsGetByCode)
            ->method('getByCode')
            ->willReturn($row);

        $di['db'] = $db;
        $serviceMock->setDi($di);

        $result = $serviceMock->getCurrencyByClientId(1);

        $this->assertEquals($row, $result);
        $this->assertInstanceOf('Model_Currency', $result);
    }

    public function testGetCurrencyByClientIdNotFounfByCode(): void
    {
        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $db->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(new \Model_Currency());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->onlyMethods(['getDefault', 'getByCode'])->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getDefault')
            ->willReturn(new \Model_Currency());

        $serviceMock->expects($this->atLeastOnce())
            ->method('getByCode')
            ->willReturn(null);

        $di['db'] = $db;
        $serviceMock->setDi($di);

        $result = $serviceMock->getCurrencyByClientId(1);

        $this->assertInstanceOf('Model_Currency', $result);
    }

    public function testgetByCode(): void
    {
        $di = new \Pimple\Container();
        $service = new \Box\Mod\Currency\Service();
        $bean = new \DummyBean();
        $bean->code = 'EUR';
        $model = new \Model_Currency();
        $model->loadBean($bean);

        $currency = 'EUR';

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->getByCode($currency);

        $this->assertEquals($model, $result);
        $this->assertInstanceOf('Model_Currency', $result);
        $this->assertEquals($model->code, $currency);
    }

    public static function getRateByCodeProvider(): array
    {
        return [
            ['EUR', 0.6, 0.6],
            ['GBP', null, 1],
            ['GBP', 'rate', 1],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getRateByCodeProvider')]
    public function testGetRateByCode(string $code, float|string|null $returns, float|int $expected): void
    {
        $service = new \Box\Mod\Currency\Service();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn($returns);

        $di['db'] = $db;
        $service->setDi($di);
        $result = $service->getRateByCode($code);
        $this->assertEquals($expected, $result);
    }

    public function testGetDefault(): void
    {
        $service = new \Box\Mod\Currency\Service();

        $bean = new \DummyBean();
        $model = new \Model_Currency();
        $model->loadBean($bean);

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn([]);
        $db->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($model);
        $di['db'] = $db;
        $service->setDi($di);
        $result = $service->getDefault();

        $this->assertInstanceOf('Model_Currency', $result);
        $this->assertEquals($model, $result);
    }

    public static function setAsDefaultProvider(): array
    {
        $self = new ServiceTest('ServiceTest');

        $firstModel = new \Model_Currency();
        $firstModel->loadBean(new \DummyBean());
        $firstModel->code = 'USD';
        $firstModel->is_default = 0;
        $secondModel = new \Model_Currency();
        $secondModel->loadBean(new \DummyBean());
        $secondModel->code = 'USD';
        $secondModel->is_default = 1;

        return [
            [$firstModel, $self->atLeastOnce()],
            [$secondModel, $self->never()],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('setAsDefaultProvider')]
    public function testSetAsDefault(\Model_Currency $model, \PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce|\PHPUnit\Framework\MockObject\Rule\InvokedCount $expects): void
    {
        $service = new \Box\Mod\Currency\Service();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($expects)
            ->method('exec')
            ->willReturn(true);

        $di['db'] = $db;
        $di['logger'] = new \Box_Log();
        $service->setDi($di);
        $result = $service->setAsDefault($model);

        $this->assertTrue($result);
    }

    public function testSetAsDefaultException(): void
    {
        $service = new \Box\Mod\Currency\Service();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->never())
            ->method('exec')
            ->willReturn(true);

        $model = new \Model_Currency();
        $model->loadBean(new \DummyBean());
        $model->is_default = 0;
        $model->code = null;

        $di['db'] = $db;
        $service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $service->setAsDefault($model); // Currency code is null, should throw an \FOSSBilling\Exception
    }

    public function testgetPairs(): void
    {
        $service = new \Box\Mod\Currency\Service();

        $pairs = [
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
            'GBP' => 'Pound Sterling',
        ];
        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn($pairs);

        $di['db'] = $db;
        $service->setDi($di);
        $result = $service->getPairs();

        $this->assertEquals($result, $pairs);
    }

    public function testRmDefaultCurrencyException(): void
    {
        $service = new \Box\Mod\Currency\Service();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->never())
            ->method('exec')
            ->willReturn(true);

        $model = new \Model_Currency();
        $model->loadBean(new \DummyBean());
        $model->code = 'EUR';
        $model->is_default = 1;

        $di['db'] = $db;
        $service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $service->rm($model); // will throw \FOSSBilling\Exception because default currency cannot be removed
    }

    public function testRm(): void
    {
        $service = new \Box\Mod\Currency\Service();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('exec')
            ->willReturn(true);

        $model = new \Model_Currency();
        $model->loadBean(new \DummyBean());
        $model->code = 'EUR';
        $model->is_default = 0;

        $di['db'] = $db;
        $service->setDi($di);
        $result = $service->rm($model);

        $this->assertEquals($result, null);
    }

    public function testRmMissingCodeException(): void
    {
        $service = new \Box\Mod\Currency\Service();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->never())
            ->method('exec')
            ->willReturn(true);

        $model = new \Model_Currency();
        $model->loadBean(new \DummyBean());
        $model->is_default = 0;
        $model->code = null;

        $di['db'] = $db;
        $service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $service->rm($model); // will throw \FOSSBilling\Exception because currency code is not set
    }

    public function testToApiArray(): void
    {
        $service = new \Box\Mod\Currency\Service();

        $model = new \Model_Currency();
        $model->loadBean(new \DummyBean());

        $model->code = 'EUR';
        $model->title = 'Euro';
        $model->conversion_rate = '3.4528';
        $model->format = '';
        $model->price_format = '';
        $model->is_default = 1;

        $expected = [
            'code' => $model->code,
            'title' => $model->title,
            'conversion_rate' => (float) $model->conversion_rate,
            'format' => $model->format,
            'price_format' => $model->price_format,
            'default' => $model->is_default,
        ];

        $result = $service->toApiArray($model);
        $this->assertEquals($result, $expected);
    }

    public function testCreateCurrency(): void
    {
        $service = new \Box\Mod\Currency\Service();

        $code = 'EUR';
        $format = '€{{price}}';

        $systemService = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->onlyMethods(['checkLimits'])->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('checkLimits')
            ->willReturn(null);

        $currencyModel = new \Model_Tld();
        $currencyModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($currencyModel);

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemService);
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

        $model = new \Model_Currency();
        $model->loadBean(new \DummyBean());
        $model->code = 'EUR';

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->onlyMethods(['getByCode'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getByCode')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $di['logger'] = new \Box_Log();
        $di['db'] = $db;
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

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->onlyMethods(['getByCode'])->getMock();
        $di = new \Pimple\Container();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $di['db'] = $db;

        $service->setDi($di);

        $service->expects($this->atLeastOnce())
            ->method('getByCode')
            ->willReturn(false);
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

        $model = new \Model_Currency();
        $model->loadBean(new \DummyBean());
        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->onlyMethods(['getByCode'])->getMock();
        $di = new \Pimple\Container();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $di['db'] = $db;

        $service->setDi($di);

        $service->expects($this->atLeastOnce())
            ->method('getByCode')
            ->willReturn($model);

        $this->expectException(\FOSSBilling\Exception::class);
        $service->updateCurrency($code, $format, $title, $price_format, $conversion_rate); // Expecting \FOSSBilling\Exception every time
    }

    public function testUpdateCurrencyRates(): void
    {
        $model = new \Model_Currency();
        $model->loadBean(new \DummyBean());
        $model->code = 'EUR';

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->onlyMethods(['getDefault', '_getRate'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getDefault')
            ->willReturn($model);
        $service->expects($this->atLeastOnce())
            ->method('_getRate')
            ->willReturn(floatval(random_int(1, 50) / 10));

        $bean = new \DummyBean();
        $bean->is_default = 1;
        $bean->code = 'EUR';

        $bean2 = new \DummyBean();
        $bean2->is_default = 0;
        $bean2->code = 'USD';

        $beansArray = [
            $bean, $bean2,
        ];

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn($beansArray);

        $di['logger'] = new \Box_Log();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->updateCurrencyRates([]);

        $this->assertIsBool($result);
        $this->assertEquals($result, true);
    }

    public function testUpdateCurrencyRatesRateNotNumeric(): void
    {
        $model = new \Model_Currency();
        $model->loadBean(new \DummyBean());
        $model->code = 'EUR';

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->onlyMethods(['getDefault', '_getRate'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getDefault')
            ->willReturn($model);
        $service->expects($this->atLeastOnce())
            ->method('_getRate')
            ->willReturn(false);

        $bean = new \DummyBean();
        $bean->is_default = 0;
        $bean->code = 'EUR';

        $beansArray = [
            $bean,
        ];

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn($beansArray);

        $di['logger'] = new \Box_Log();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->updateCurrencyRates([]);

        $this->assertIsBool($result);
        $this->assertEquals($result, true);
    }

    public function testDelete(): void
    {
        $model = new \Model_Currency();
        $model->loadBean(new \DummyBean());
        $model->code = 'EUR';

        $code = 'EUR';

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->onlyMethods(['getByCode', 'rm'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getByCode')
            ->willReturn($model);
        $service->expects($this->atLeastOnce())
            ->method('rm');

        $manager = $this->getMockBuilder('Box_EventManager')->getMock();
        $manager->expects($this->atLeastOnce())
            ->method('fire')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $di['logger'] = new \Box_Log();
        $di['db'] = $db;
        $di['events_manager'] = $manager;

        $service->setDi($di);

        $result = $service->deleteCurrencyByCode($code);

        $this->assertIsBool($result);
        $this->assertEquals($result, true);
    }

    public function testDeleteModelNotFoundException(): void
    {
        $code = 'EUR';

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->onlyMethods(['getByCode', 'rm'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getByCode')
            ->willReturn(null);

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
