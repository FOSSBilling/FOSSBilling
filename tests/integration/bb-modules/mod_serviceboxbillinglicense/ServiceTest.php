<?php

/**
 * @group Core
 */
class ServiceBoxBillinglicenseTest extends BBDbApiTestCase
{
    public function testService()
    {
        $service = new \Box\Mod\Serviceboxbillinglicense\Service();
        $service->setDi($this->di);
        $result = $service->install();
        $this->assertNull($result);

        $data = array(
            'param' => 'value'
        );
        $result = $service->setModuleConfig($data);
        $this->assertNull($result);

        $config = $service->getModuleConfig();
        $this->assertArrayHasKey('param', $config);
        $this->assertEquals($data['param'], $config['param']);
    }

    public function testActions()
    {

        $service = new \Box\Mod\Serviceboxbillinglicense\Service();
        $service->setDi($this->di);

        $order = $this->di['db']->load('ClientOrder', 1);

        $model = $service->create($order);
        $this->assertInstanceOf('RedBeanPHP\OODBBean', $model);


        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(7456);
        $this->expectExceptionMessage('Could not activate order. Service was not created');
        $service->activate($order, null);

        $result = $service->activate($order, $model);
        $this->assertTrue($result);


        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(7456);
        $this->expectExceptionMessage('Could not activate order. Service was not created');
        $service->suspend($order, null);

        $result = $service->suspend($order, $model);
        $this->assertTrue($result);


        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(7456);
        $this->expectExceptionMessage('Could not activate order. Service was not created');
        $service->unsuspend($order, null);

        $result = $service->unsuspend($order, $model);
        $this->assertTrue($result);

        $result = $service->reset($order, $model);
        $this->assertTrue($result);

        $result = $service->licenseDetails($model);
        $this->assertIsArray($result);

        $result = $service->licenseReset($model);
        $this->assertIsArray($result);

        $result = $service->toApiArray($model);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('oid', $result);

        $result = $service->delete($order, $model);
        $this->assertTrue($result);
    }
    
    public function testUninstall()
    {
        $service = new \Box\Mod\Serviceboxbillinglicense\Service();
        $service->setDi($this->di);
        $result = $service->uninstall();
        $this->assertNull($result);

        $service->install();
    }

}