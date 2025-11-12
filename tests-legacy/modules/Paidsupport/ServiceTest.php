<?php

namespace Box\Mod\Paidsupport;

class ServiceTest extends \BBTestCase
{
    /**
     * @var Service
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new Service();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testenoughInBalanceToOpenTicket(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $clientTotalAmount = 25.0;

        $clientBalanceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\ServiceBalance::class)->getMock();
        $clientBalanceMock->expects($this->atLeastOnce())
            ->method('getClientBalance')
            ->with($clientModel)
            ->willReturn($clientTotalAmount);

        $paidSupportConfig = [
            'ticket_price' => 2,
            'error_msg' => 'Insufficient funds to open ticket',
        ];

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName, $subService) use ($clientBalanceMock) {
            if ($serviceName == 'Client' && $subService == 'Balance') {
                return $clientBalanceMock;
            }
        });
        $di['mod_config'] = $di->protect(function ($serviceName) use ($paidSupportConfig) {
            if ($serviceName == 'Paidsupport') {
                return $paidSupportConfig;
            }
        });
        $this->service->setDi($di);

        $result = $this->service->enoughInBalanceToOpenTicket($clientModel);
        $this->assertTrue($result);
    }

    public function testNotenoughInBalanceToOpenTicket(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $clientTotalAmount = 0.0;

        $clientBalanceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\ServiceBalance::class)->getMock();
        $clientBalanceMock->expects($this->atLeastOnce())
            ->method('getClientBalance')
            ->with($clientModel)
            ->willReturn($clientTotalAmount);

        $paidSupportConfig = [
            'ticket_price' => 2,
            'error_msg' => 'Insufficient funds to open ticket',
        ];

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName, $subService) use ($clientBalanceMock) {
            if ($serviceName == 'Client' && $subService == 'Balance') {
                return $clientBalanceMock;
            }
        });
        $di['mod_config'] = $di->protect(function ($serviceName) use ($paidSupportConfig) {
            if ($serviceName == 'Paidsupport') {
                return $paidSupportConfig;
            }
        });

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage($paidSupportConfig['error_msg']);
        $this->service->enoughInBalanceToOpenTicket($clientModel);
    }

    public function testEnoughInBalanceToOpenTicketTicketPriceEqualsTotalAmount(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $clientTotalAmount = 4.0;

        $clientBalanceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\ServiceBalance::class)->getMock();
        $clientBalanceMock->expects($this->atLeastOnce())
            ->method('getClientBalance')
            ->with($clientModel)
            ->willReturn($clientTotalAmount);

        $paidSupportConfig = [
            'ticket_price' => $clientTotalAmount,
            'error_msg' => 'Insufficient funds to open ticket',
        ];

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName, $subService) use ($clientBalanceMock) {
            if ($serviceName == 'Client' && $subService == 'Balance') {
                return $clientBalanceMock;
            }
        });
        $di['mod_config'] = $di->protect(function ($serviceName) use ($paidSupportConfig) {
            if ($serviceName == 'Paidsupport') {
                return $paidSupportConfig;
            }
        });
        $this->service->setDi($di);

        $result = $this->service->enoughInBalanceToOpenTicket($clientModel);
        $this->assertTrue($result);
    }

    public function testEnoughInBalanceToOpenTicketTicketPriceIsNotSet(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $clientTotalAmount = 4.0;

        $clientBalanceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\ServiceBalance::class)->getMock();
        $clientBalanceMock->expects($this->atLeastOnce())
            ->method('getClientBalance')
            ->with($clientModel)
            ->willReturn($clientTotalAmount);

        $paidSupportConfig = [];

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName, $subService) use ($clientBalanceMock) {
            if ($serviceName == 'Client' && $subService == 'Balance') {
                return $clientBalanceMock;
            }
        });
        $di['mod_config'] = $di->protect(function ($serviceName) use ($paidSupportConfig) {
            if ($serviceName == 'Paidsupport') {
                return $paidSupportConfig;
            }
        });
        $this->service->setDi($di);

        $result = $this->service->enoughInBalanceToOpenTicket($clientModel);
        $this->assertTrue($result);
    }

    public function testonBeforeClientOpenTicketPaidSupportForHelpdeskEnabled(): void
    {
        $di = new \Pimple\Container();

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->with('Client')
            ->willReturn($clientModel);
        $di['db'] = $dbMock;

        $paidSupportMock = $this->getMockBuilder('\\' . Service::class)->getMock();
        $paidSupportMock->expects($this->atLeastOnce())
            ->method('hasHelpdeskPaidSupport')
            ->willReturn(true);
        $paidSupportMock->expects($this->atLeastOnce())
            ->method('enoughInBalanceToOpenTicket')
            ->with($clientModel);
        $di['mod_service'] = $di->protect(function ($serviceName) use ($paidSupportMock) {
            if ($serviceName == 'Paidsupport') {
                return $paidSupportMock;
            }
        });

        $params = [
            'client_id' => 1,
            'support_helpdesk_id' => 1,
        ];

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

    public function testonBeforeClientOpenTicketPaidSupportForHelpdeskDisabled(): void
    {
        $di = new \Pimple\Container();

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->with('Client')
            ->willReturn($clientModel);
        $di['db'] = $dbMock;

        $paidSupportMock = $this->getMockBuilder('\\' . Service::class)->getMock();
        $paidSupportMock->expects($this->atLeastOnce())
            ->method('hasHelpdeskPaidSupport')
            ->willReturn(false);
        $paidSupportMock->expects($this->never())
            ->method('enoughInBalanceToOpenTicket')
            ->with($clientModel);
        $di['mod_service'] = $di->protect(function ($serviceName) use ($paidSupportMock) {
            if ($serviceName == 'Paidsupport') {
                return $paidSupportMock;
            }
        });

        $params = [
            'client_id' => 1,
            'support_helpdesk_id' => 1,
        ];

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

    public function testgetTicketPrice(): void
    {
        $di = new \Pimple\Container();
        $paidSupportConfig = [
            'ticket_price' => 1,
        ];

        $di['mod_config'] = $di->protect(function ($serviceName) use ($paidSupportConfig) {
            if ($serviceName == 'Paidsupport') {
                return $paidSupportConfig;
            }
        });

        $this->service->setDi($di);
        $result = $this->service->getTicketPrice();
        $this->assertEquals($paidSupportConfig['ticket_price'], $result);
    }

    public function testgetTicketPriceNotSet(): void
    {
        $di = new \Pimple\Container();
        $paidSupportConfig = [];

        $di['mod_config'] = $di->protect(function ($serviceName) use ($paidSupportConfig) {
            if ($serviceName == 'Paidsupport') {
                return $paidSupportConfig;
            }
        });

        $this->service->setDi($di);
        $result = $this->service->getTicketPrice();
        $this->assertEquals(0, $result);
    }

    public function testgetErrorMessage(): void
    {
        $di = new \Pimple\Container();
        $errorMessage = 'Not enough funds';
        $paidSupportConfig = [
            'error_msg' => $errorMessage,
        ];

        $di['mod_config'] = $di->protect(function ($serviceName) use ($paidSupportConfig) {
            if ($serviceName == 'Paidsupport') {
                return $paidSupportConfig;
            }
        });

        $this->service->setDi($di);
        $result = $this->service->getErrorMessage();
        $this->assertEquals($errorMessage, $result);
    }

    public function testgetErrorMessageNotSet(): void
    {
        $di = new \Pimple\Container();
        $errorMessage = 'Configure paid support module!';
        $paidSupportConfig = [];

        $di['mod_config'] = $di->protect(function ($serviceName) use ($paidSupportConfig) {
            if ($serviceName == 'Paidsupport') {
                return $paidSupportConfig;
            }
        });

        $this->service->setDi($di);
        $result = $this->service->getErrorMessage();
        $this->assertEquals($errorMessage, $result);
    }

    public function testonAfterClientOpenTicket(): void
    {
        $di = new \Pimple\Container();

        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \DummyBean());

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturnCallback(fn (...$args): \Model_SupportTicket|\Model_Client => match ($args[0]) {
                'SupportTicket' => $supportTicketModel,
                'Client' => $clientModel,
            });
        $di['db'] = $dbMock;

        $paidSupportMock = $this->getMockBuilder('\\' . Service::class)->getMock();
        $paidSupportMock->expects($this->atLeastOnce())
            ->method('hasHelpdeskPaidSupport')
            ->willReturn(true);
        $paidSupportMock->expects($this->atLeastOnce())
            ->method('enoughInBalanceToOpenTicket')
            ->with($clientModel);

        $clientBalanceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\ServiceBalance::class)->getMock();
        $clientBalanceMock->expects($this->atLeastOnce())
            ->method('deductFunds');

        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($paidSupportMock, $clientBalanceMock) {
            if ($serviceName == 'Paidsupport') {
                return $paidSupportMock;
            }
            if ($serviceName == 'Client' && $sub == 'Balance') {
                return $clientBalanceMock;
            }
        });

        $params = [
            'id' => 1,
            'support_helpdesk_id' => 1,
        ];

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

    public function testonAfterClientOpenTicketPaidSupportDisabledForHelpdesk(): void
    {
        $di = new \Pimple\Container();

        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \DummyBean());

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturnCallback(fn (...$args): \Model_SupportTicket|\Model_Client => match ($args[0]) {
                'SupportTicket' => $supportTicketModel,
                'Client' => $clientModel,
            });
        $di['db'] = $dbMock;

        $paidSupportMock = $this->getMockBuilder('\\' . Service::class)->getMock();
        $paidSupportMock->expects($this->atLeastOnce())
            ->method('hasHelpdeskPaidSupport')
            ->willReturn(false);
        $paidSupportMock->expects($this->never())
            ->method('enoughInBalanceToOpenTicket')
            ->with($clientModel);

        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($paidSupportMock) {
            if ($serviceName == 'Paidsupport') {
                return $paidSupportMock;
            }
        });

        $params = [
            'id' => 1,
            'support_helpdesk_id' => 1,
        ];

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

    public function testgetPaidHelpdeskConfig(): void
    {
        $di = new \Pimple\Container();
        $helpdeskId = 2;
        $helpdeskConfig = [
            $helpdeskId => 0,
        ];
        $paidSupportConfig = [
            'helpdesk' => $helpdeskConfig,
        ];

        $di['mod_config'] = $di->protect(function ($serviceName) use ($paidSupportConfig) {
            if ($serviceName == 'Paidsupport') {
                return $paidSupportConfig;
            }
        });

        $this->service->setDi($di);
        $result = $this->service->getPaidHelpdeskConfig();
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertEquals($helpdeskConfig, $result);
    }

    public function testgetPaidHelpdeskConfigIsNotSet(): void
    {
        $di = new \Pimple\Container();
        $paidSupportConfig = [];

        $di['mod_config'] = $di->protect(function ($serviceName) use ($paidSupportConfig) {
            if ($serviceName == 'Paidsupport') {
                return $paidSupportConfig;
            }
        });

        $this->service->setDi($di);
        $result = $this->service->getPaidHelpdeskConfig();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testhasHelpdeskPaidSupportTurnedOff(): void
    {
        $helpdeskId = 1;
        $helpdeskConfig = [
            $helpdeskId => 0,
        ];
        $paidSupportServiceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getPaidHelpdeskConfig'])
            ->getMock();
        $paidSupportServiceMock->expects($this->atLeastOnce())
            ->method('getPaidHelpdeskConfig')
            ->willReturn($helpdeskConfig);

        $result = $paidSupportServiceMock->hasHelpdeskPaidSupport($helpdeskId);
        $this->assertFalse($result);
    }

    public function testhasHelpdeskPaidSupportTurnedOn(): void
    {
        $helpdeskId = 1;
        $helpdeskConfig = [
            $helpdeskId => 1,
        ];
        $paidSupportServiceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getPaidHelpdeskConfig'])
            ->getMock();
        $paidSupportServiceMock->expects($this->atLeastOnce())
            ->method('getPaidHelpdeskConfig')
            ->willReturn($helpdeskConfig);

        $result = $paidSupportServiceMock->hasHelpdeskPaidSupport($helpdeskId);
        $this->assertTrue($result);
    }

    public function testhasHelpdeskPaidSupportConfigNotConfigured(): void
    {
        $helpdeskId = 1;
        $helpdeskConfig = [];

        $paidSupportServiceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getPaidHelpdeskConfig'])
            ->getMock();
        $paidSupportServiceMock->expects($this->atLeastOnce())
            ->method('getPaidHelpdeskConfig')
            ->willReturn($helpdeskConfig);

        $result = $paidSupportServiceMock->hasHelpdeskPaidSupport($helpdeskId);
        $this->assertFalse($result);
    }

    public function testPaidSupportAppliedForAllHelpdesksAllHelpdesksAreNotChecked(): void
    {
        $di = new \Pimple\Container();
        $helpdeskId = 2;
        $helpdeskId1 = 3;
        $helpdeskConfig = [
            $helpdeskId => 0,
            $helpdeskId1 => 0,
        ];
        $paidSupportConfig = [
            'helpdesk' => $helpdeskConfig,
        ];

        $di['mod_config'] = $di->protect(function ($serviceName) use ($paidSupportConfig) {
            if ($serviceName == 'Paidsupport') {
                return $paidSupportConfig;
            }
        });

        $this->service->setDi($di);
        $result = $this->service->hasHelpdeskPaidSupport($helpdeskId);
        $this->assertFalse($result);
    }

    public function testUninstall(): void
    {
        $di = new \Pimple\Container();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_ExtensionMeta();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('ExtensionMeta')
            ->willReturn($model);
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->with($model);

        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->uninstall();
        $this->assertTrue($result);
    }

    public function testUninstallConfigNotFound(): void
    {
        $di = new \Pimple\Container();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_ExtensionMeta();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('ExtensionMeta');
        $dbMock->expects($this->never())
            ->method('trash');

        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->uninstall();
        $this->assertTrue($result);
    }

    public function testInstall(): void
    {
        $di = new \Pimple\Container();

        $extensionServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\Service::class)->getMock();
        $extensionServiceMock->expects($this->atLeastOnce())
            ->method('setConfig')
            ->willReturn(true);

        $di['mod_service'] = $di->protect(function ($serviceName) use ($extensionServiceMock) {
            if ($serviceName == 'Extension') {
                return $extensionServiceMock;
            }
        });

        $this->service->setDi($di);
        $result = $this->service->install();
        $this->assertTrue($result);
    }
}
