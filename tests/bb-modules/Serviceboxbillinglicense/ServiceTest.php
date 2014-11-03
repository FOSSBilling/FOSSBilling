<?php

namespace Box\Tests\Mod\Serviceboxbillinglicense;

use Box\Mod\Serviceboxbillinglicense\Service;

class ServiceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Box\Mod\Serviceboxbillinglicense\Service
     */
    protected $service = null;

    public function setup()
    {
        $this->service= new \Box\Mod\Serviceboxbillinglicense\Service();
    }

    public function testDi()
    {
        $di = new \Box_Di();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testinstall()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('exec');

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $this->service->install();
    }

    public function testuninstall()
    {
        $uninstallSql = "DROP TABLE IF EXISTS `service_boxbillinglicense`";
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('exec')
            ->with($uninstallSql);

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $this->service->uninstall();
    }

    public function testsetModuleConfig()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('exec');

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Serviceboxbillinglicense\Service')
            ->setMethods(array('getModuleConfig'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getModuleConfig');

        $serviceMock->setDi($di);
        $serviceMock->setModuleConfig(array());
    }

    public function testgetModuleConfig()
    {
        $extensionMetaModel = new \Model_ExtensionMeta();
        $extensionMetaModel->loadBean(new \RedBeanPHP\OODBBean());
        $extensionMetaModel->meta_value = '{}';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('extension_meta')
            ->willReturn($extensionMetaModel);

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getModuleConfig();
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('api_key', $result);
    }

    public function testgetModuleConfig_CreateDatabaseRecord()
    {
        $extensionMetaModel = new \Model_ExtensionMeta();
        $extensionMetaModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('extension_meta');
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->with('extension_meta')
            ->willReturn($extensionMetaModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($extensionMetaModel);

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getModuleConfig();
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('api_key', $result);
    }
}
