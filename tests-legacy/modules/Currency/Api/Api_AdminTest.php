<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Currency\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class Api_AdminTest extends \BBTestCase
{
    public function testGetList(): void
    {
        $adminApi = $this->createAdminApi(\Box\Mod\Currency\Api\Admin::class);

        $willReturn = [
            'list' => ['id' => 1],
        ];

        $qbMock = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('getSearchQueryBuilder')
            ->willReturn($qbMock);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($repositoryMock);

        $pager = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
            ->onlyMethods(['paginateDoctrineQuery'])
            ->disableOriginalConstructor()
            ->getMock();
        $pager->expects($this->atLeastOnce())
            ->method('paginateDoctrineQuery')
            ->willReturn($willReturn);

        $di = $this->getDi();
        $di['pager'] = $pager;

        $adminApi->setDi($di);
        $adminApi->setService($serviceMock);

        $result = $adminApi->get_list([]);

        $this->assertIsArray($result);
        $this->assertIsArray($result['list']);
    }

    public function testGetPairs(): void
    {
        $adminApi = $this->createAdminApi(\Box\Mod\Currency\Api\Admin::class);
        $adminApi->setDi($this->getDi());

        $result = $adminApi->get_pairs();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('USD', $result);
        $this->assertArrayHasKey('EUR', $result);
        $this->assertStringContainsString('USD', $result['USD']);
        $this->assertStringContainsString('EUR', $result['EUR']);
    }

    public function testGet(): void
    {
        $adminApi = $this->createAdminApi(\Box\Mod\Currency\Api\Admin::class);

        $model = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $model->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([
                'code' => 'EUR',
                'name' => 'Euro',
                'conversion_rate' => 1.0,
                'default' => false,
            ]);

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->with('EUR')
            ->willReturn($model);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($repositoryMock);

        $di = $this->getDi();
        $data = [
            'code' => 'EUR',
        ];
        $adminApi->setService($service);
        $adminApi->setDi($di);
        $result = $adminApi->get($data);
        $this->assertIsArray($result);
        $this->assertEquals($result['code'], 'EUR');
    }

    public function testGetDefault(): void
    {
        $adminApi = $this->createAdminApi(\Box\Mod\Currency\Api\Admin::class);

        $returnArr = [
            'code' => 'EUR',
            'name' => 'Euro',
            'conversion_rate' => 3.4528,
            'default' => true,
        ];

        $model = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $model->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($returnArr);

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findDefault')
            ->willReturn($model);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($repositoryMock);

        $adminApi->setService($service);
        $result = $adminApi->get_default([]);

        $this->assertIsArray($result);
        $this->assertEquals($result, $returnArr);
        $this->assertEquals('EUR', $returnArr['code']);
        $this->assertEquals('Euro', $returnArr['name']);
        $this->assertIsFloat($returnArr['conversion_rate']);
        $this->assertEquals(3.4528, $returnArr['conversion_rate']);
        $this->assertTrue($returnArr['default']);
    }

    public static function CreateExceptionProvider(): array
    {
        return [
            [
                [
                    'code' => 'EUR',
                ],
                'atLeastOnce',
                'currency_exists',
            ],
            [
                [
                    'code' => 'NON',
                ],
                'atLeastOnce',
                null,
            ],
        ];
    }

    #[DataProvider('CreateExceptionProvider')]
    public function testCreateException(array $data, string $findOneByCodeCalled, ?string $findOneByCodeReturn): void
    {
        $adminApi = $this->createAdminApi(\Box\Mod\Currency\Api\Admin::class);

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        if ($findOneByCodeReturn === 'currency_exists') {
            $findOneByCodeReturn = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
                ->disableOriginalConstructor()
                ->getMock();
        }

        $repositoryMock->expects($this->$findOneByCodeCalled())
            ->method('findOneByCode')
            ->willReturn($findOneByCodeReturn);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($repositoryMock);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('currency', 'create');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (string $name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower($name)) {
            'staff' => $staffServiceMock,
        });
        $adminApi->setService($service);
        $adminApi->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $adminApi->create($data);
    }

    public function testCreate(): void
    {
        $adminApi = $this->createAdminApi(\Box\Mod\Currency\Api\Admin::class);

        $data = [
            'code' => 'EUR',
        ];

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->willReturn(null);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($repositoryMock);
        $service->expects($this->atLeastOnce())
            ->method('createCurrency')
            ->willReturn($data['code']);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('currency', 'create');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (string $name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower($name)) {
            'staff' => $staffServiceMock,
        });
        $adminApi->setService($service);
        $adminApi->setDi($di);

        $result = $adminApi->create($data);

        $this->assertIsString($result);
        $this->assertEquals(strlen($result), 3);
        $this->assertEquals($result, $data['code']);
    }

    public function testUpdate(): void
    {
        $adminApi = $this->createAdminApi(\Box\Mod\Currency\Api\Admin::class);

        $data = [
            'code' => 'EUR',
            'conversion_rate' => 0.6,
        ];

        $service = $this->createMock(\Box\Mod\Currency\Service::class);
        $service->expects($this->atLeastOnce())
            ->method('updateCurrency')
            ->willReturn(true);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('currency', 'edit');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (string $name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower($name)) {
            'staff' => $staffServiceMock,
        });
        $adminApi->setDi($di);
        $adminApi->setService($service);

        $result = $adminApi->update($data);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    /**
     * @expectedException \FOSSBilling\Exception
     */
    public function testDeleteException(): void
    {
        $adminApi = $this->createAdminApi(\Box\Mod\Currency\Api\Admin::class);

        $service = $this->createMock(\Box\Mod\Currency\Service::class);
        $service->expects($this->never())
            ->method('removeCurrency');

        $apiHandler = new \Api_Handler(new \Model_Admin());
        $reflection = new \ReflectionClass($apiHandler);
        $method = $reflection->getMethod('validateRequiredParams');
        $this->expectException(\FOSSBilling\InformationException::class);
        $method->invokeArgs($apiHandler, [$adminApi, 'delete', []]);

        $di = $this->getDi();
        $di['validator'] = new \FOSSBilling\Validate();
        $adminApi->setDi($di);
        $adminApi->setService($service);
        $adminApi->delete([]);
    }

    public function testDelete(): void
    {
        $adminApi = $this->createAdminApi(\Box\Mod\Currency\Api\Admin::class);

        $data = [
            'code' => 'EUR',
        ];

        $service = $this->getMockBuilder(\Box\Mod\Currency\Service::class)->onlyMethods(['removeCurrency'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('removeCurrency')
            ->willReturn(true);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('currency', 'delete');

        $di = $this->getDi();
        $di['validator'] = new \FOSSBilling\Validate();
        $di['mod_service'] = $di->protect(fn (string $name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower($name)) {
            'staff' => $staffServiceMock,
        });
        $adminApi->setDi($di);
        $adminApi->setService($service);

        $result = $adminApi->delete($data);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public static function SetDefaultExceptionProvider(): array
    {
        return [
            [
                [
                    'code' => 'EUR', // model is not instance of Currency
                ],
                'atLeastOnce',
                null,
            ],
        ];
    }

    #[DataProvider('SetDefaultExceptionProvider')]
    public function testSetDefaultException(array $data, string $getByCodeCalled, $getByCodeReturn): void
    {
        $adminApi = $this->createAdminApi(\Box\Mod\Currency\Api\Admin::class);

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->willReturn(null);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($repositoryMock);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('currency', 'set_default');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (string $name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower($name)) {
            'staff' => $staffServiceMock,
        });
        $adminApi->setDi($di);

        $adminApi->setService($service);
        $this->expectException(\FOSSBilling\Exception::class);
        $adminApi->set_default($data); // Expecting \FOSSBilling\Exception every time
    }

    public function testSetDefault(): void
    {
        $adminApi = $this->createAdminApi(\Box\Mod\Currency\Api\Admin::class);

        $model = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();

        $data = [
            'code' => 'EUR',
        ];

        $repositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->willReturn($model);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($repositoryMock);
        $service->expects($this->atLeastOnce())
            ->method('setAsDefault')
            ->willReturn(true);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('currency', 'set_default');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (string $name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower($name)) {
            'staff' => $staffServiceMock,
        });
        $adminApi->setDi($di);
        $adminApi->setService($service);

        $result = $adminApi->set_default($data);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testUpdateRates(): void
    {
        $adminApi = $this->createAdminApi(\Box\Mod\Currency\Api\Admin::class);

        $service = $this->createMock(\Box\Mod\Currency\Service::class);
        $service->expects($this->once())
            ->method('updateCurrencyRates')
            ->willReturn(true);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('currency', 'update_rates');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (string $name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower($name)) {
            'staff' => $staffServiceMock,
        });

        $adminApi->setDi($di);
        $adminApi->setService($service);

        $result = $adminApi->update_rates([]);

        $this->assertTrue($result);
    }
}
