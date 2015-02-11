<?php


namespace Box\Mod\Paidsupport;


class ServiceTest extends \PHPUnit_Framework_TestCase {
    /**
     * @var \Box\Mod\Paidsupport\Service
     */
    protected $service = null;

    public function setup()
    {
        $this->service= new \Box\Mod\Paidsupport\Service();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testenoughInBalanceToOpenTicket()
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $clientTotalAmount = 25;

        $clientBalanceMock = $this->getMockBuilder('\Box\Mod\Client\ServiceBalance')->getMock();
        $clientBalanceMock->expects($this->atLeastOnce())
            ->method('getClientBalance')
            ->with($clientModel)
            ->willReturn($clientTotalAmount);

        $paidSupportConfig = array(
            'ticket_price' => 2,
            'error_msg' => 'Insufficient funds to open ticket',
        );

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function($serviceName, $subService) use ($clientBalanceMock){
            if ($serviceName == 'Client' && $subService == 'Balance') {
                return $clientBalanceMock;
            }
        });
        $di['mod_config'] = $di->protect(function($serviceName) use ($paidSupportConfig){
            if ($serviceName== 'Paidsupport'){
                return $paidSupportConfig;
            }
        });
        $this->service->setDi($di);

        $result = $this->service->enoughInBalanceToOpenTicket($clientModel);
        $this->assertTrue($result);
    }

    public function test_NotenoughInBalanceToOpenTicket()
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $clientTotalAmount = 0;

        $clientBalanceMock = $this->getMockBuilder('\Box\Mod\Client\ServiceBalance')->getMock();
        $clientBalanceMock->expects($this->atLeastOnce())
            ->method('getClientBalance')
            ->with($clientModel)
            ->willReturn($clientTotalAmount);

        $paidSupportConfig = array(
            'ticket_price' => 2,
            'error_msg' => 'Insufficient funds to open ticket',
        );

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function($serviceName, $subService) use ($clientBalanceMock){
            if ($serviceName == 'Client' && $subService == 'Balance') {
                return $clientBalanceMock;
            }
        });
        $di['mod_config'] = $di->protect(function($serviceName) use ($paidSupportConfig){
            if ($serviceName== 'Paidsupport'){
                return $paidSupportConfig;
            }
        });
        $this->service->setDi($di);

        $this->setExpectedException('\Box_Exception', $paidSupportConfig['error_msg']);
        $this->service->enoughInBalanceToOpenTicket($clientModel);
    }

    public function test_enoughInBalanceToOpenTicket_TicketPriceEqualsTotalAmount()
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $clientTotalAmount = 4;

        $clientBalanceMock = $this->getMockBuilder('\Box\Mod\Client\ServiceBalance')->getMock();
        $clientBalanceMock->expects($this->atLeastOnce())
            ->method('getClientBalance')
            ->with($clientModel)
            ->willReturn($clientTotalAmount);

        $paidSupportConfig = array(
            'ticket_price' => $clientTotalAmount,
            'error_msg' => 'Insufficient funds to open ticket',
        );

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function($serviceName, $subService) use ($clientBalanceMock){
            if ($serviceName == 'Client' && $subService == 'Balance') {
                return $clientBalanceMock;
            }
        });
        $di['mod_config'] = $di->protect(function($serviceName) use ($paidSupportConfig){
            if ($serviceName== 'Paidsupport'){
                return $paidSupportConfig;
            }
        });
        $this->service->setDi($di);

        $result = $this->service->enoughInBalanceToOpenTicket($clientModel);
        $this->assertTrue($result);
    }

    public function test_enoughInBalanceToOpenTicket_TicketPriceIsNotSet()
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $clientTotalAmount = 4;

        $clientBalanceMock = $this->getMockBuilder('\Box\Mod\Client\ServiceBalance')->getMock();
        $clientBalanceMock->expects($this->atLeastOnce())
            ->method('getClientBalance')
            ->with($clientModel)
            ->willReturn($clientTotalAmount);

        $paidSupportConfig = array();

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function($serviceName, $subService) use ($clientBalanceMock){
            if ($serviceName == 'Client' && $subService == 'Balance') {
                return $clientBalanceMock;
            }
        });
        $di['mod_config'] = $di->protect(function($serviceName) use ($paidSupportConfig){
            if ($serviceName== 'Paidsupport'){
                return $paidSupportConfig;
            }
        });
        $this->service->setDi($di);

        $result = $this->service->enoughInBalanceToOpenTicket($clientModel);
        $this->assertTrue($result);
    }

    public function testonBeforeClientOpenTicket()
    {
        $di = new \Box_Di();

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->with('Client')
            ->willReturn($clientModel);
        $di['db'] = $dbMock;

        $paidSupportMock = $this->getMockBuilder('\Box\Mod\Paidsupport\Service')->getMock();
        $paidSupportMock->expects($this->atLeastOnce())
            ->method('enoughInBalanceToOpenTicket')
            ->with($clientModel);
        $di['mod_service'] = $di->protect(function ($serviceName) use($paidSupportMock){
            if ($serviceName == 'Paidsupport'){
                return $paidSupportMock;
            }
        });

        $params = array(
            'client_id' => 1,
        );

        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($params);

        $result = $this->service->onBeforeClientOpenTicket($boxEventMock);
        $this->assertTrue($result);
    }

    public function testgetTicketPrice()
    {
        $di = new \Box_Di();
        $paidSupportConfig = array(
            'ticket_price' => 1,
        );

        $di['mod_config'] = $di->protect(function($serviceName) use ($paidSupportConfig){
            if ($serviceName== 'Paidsupport'){
                return $paidSupportConfig;
            }
        });

        $this->service->setDi($di);
        $result = $this->service->getTicketPrice();
        $this->assertEquals($paidSupportConfig['ticket_price'], $result);
    }

    public function testgetTicketPrice_NotSet()
    {
        $di = new \Box_Di();
        $paidSupportConfig = array();

        $di['mod_config'] = $di->protect(function($serviceName) use ($paidSupportConfig){
            if ($serviceName== 'Paidsupport'){
                return $paidSupportConfig;
            }
        });

        $this->service->setDi($di);
        $result = $this->service->getTicketPrice();
        $this->assertEquals(0, $result);
    }

    public function testgetErrorMessage()
    {
        $di = new \Box_Di();
        $errorMessage = 'Not enough funds';
        $paidSupportConfig = array(
            'error_msg' => $errorMessage,
        );

        $di['mod_config'] = $di->protect(function($serviceName) use ($paidSupportConfig){
            if ($serviceName== 'Paidsupport'){
                return $paidSupportConfig;
            }
        });

        $this->service->setDi($di);
        $result = $this->service->getErrorMessage();
        $this->assertEquals($errorMessage, $result);
    }

    public function testgetErrorMessage_NotSet()
    {
        $di = new \Box_Di();
        $errorMessage = 'Configure paid support module!';
        $paidSupportConfig = array();

        $di['mod_config'] = $di->protect(function($serviceName) use ($paidSupportConfig){
            if ($serviceName== 'Paidsupport'){
                return $paidSupportConfig;
            }
        });

        $this->service->setDi($di);
        $result = $this->service->getErrorMessage();
        $this->assertEquals($errorMessage, $result);
    }

    public function testonAfterClientOpenTicket()
    {
        $di = new \Box_Di();

        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \RedBeanPHP\OODBBean());

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->withConsecutive(array('SupportTicket'), array('Client'))
            ->willReturnOnConsecutiveCalls($supportTicketModel, $clientModel);
        $di['db'] = $dbMock;

        $paidSupportMock = $this->getMockBuilder('\Box\Mod\Paidsupport\Service')->getMock();
        $paidSupportMock->expects($this->atLeastOnce())
            ->method('enoughInBalanceToOpenTicket')
            ->with($clientModel);

        $clientBalanceMock = $this->getMockBuilder('\Box\Mod\Client\ServiceBalance')->getMock();
        $clientBalanceMock->expects($this->atLeastOnce())
            ->method('deductFunds');

        $di['mod_service'] = $di->protect(function ($serviceName, $sub ='') use($paidSupportMock, $clientBalanceMock){
            if ($serviceName == 'Paidsupport'){
                return $paidSupportMock;
            }
            if ($serviceName == 'Client' && $sub == 'Balance'){
                return $clientBalanceMock;
            }
        });

        $params = array(
            'id' => 1,
        );

        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($params);

        $result = $this->service->onAfterClientOpenTicket($boxEventMock);
        $this->assertTrue($result);
    }
}
 