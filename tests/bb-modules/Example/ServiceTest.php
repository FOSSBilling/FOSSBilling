<?php

namespace Box\Tests\Mod\Example;

class ServiceTest extends \BBTestCase {

    /**
     * @var \Box\Mod\Example\Service
     */
    private $service = null;

    public function setup(): void
    {
        $this->service = new \Box\Mod\Example\Service();
    }

    public function testEvents()
    {
        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);
        $result = $this->service->getSearchQuery(array());
        $this->assertIsArray($result);
    }

    public function testtoApiArray()
    {
        $result = $this->service->toApiArray(array());
        $this->assertEquals(array(), $result);
    }

    public function testonAfterClientCalledExampleModule()
    {
        $extensionMetaModel = new \Model_ExtensionMeta();
        $extensionMetaModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->with('extension_meta')
            ->willReturn($extensionMetaModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($extensionMetaModel);

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn(array());

        $this->service->onAfterClientCalledExampleModule($eventMock);
    }
}
 
