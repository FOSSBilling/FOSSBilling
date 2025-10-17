<?php

namespace Box\Mod\Staff;

class PdoMock extends \PDO
{
    public function __construct()
    {
    }
}

class PdoStatementMock extends \PDOStatement
{
    public function __construct()
    {
    }
}

class ServiceTest extends \BBTestCase
{
    public function testLogin(): void
    {
        $email = 'email@domain.com';
        $password = 'pass';
        $ip = '127.0.0.1';

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());
        $admin->id = 1;
        $admin->email = $email;
        $admin->name = 'Admin';
        $admin->role = 'admin';

        $emMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('fire')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($admin);

        $sessionMock = $this->getMockBuilder('\\' . \FOSSBilling\Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $sessionMock->expects($this->atLeastOnce())
            ->method('set');

        $authMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authMock->expects($this->atLeastOnce())
            ->method('authorizeUser')
            ->with($admin, $password)
            ->willReturn($admin);

        $di = new \Pimple\Container();
        $di['events_manager'] = $emMock;
        $di['db'] = $dbMock;
        $di['session'] = $sessionMock;
        $di['logger'] = new \Box_Log();
        $di['auth'] = $authMock;

        $service = new Service();
        $service->setDi($di);

        $result = $service->login($email, $password, $ip);

        $expected = [
            'id' => 1,
            'email' => $email,
            'name' => 'Admin',
            'role' => 'admin',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testLoginException(): void
    {
        $email = 'email@domain.com';
        $password = 'pass';
        $ip = '127.0.0.1';

        $emMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('fire')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $authMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authMock->expects($this->atLeastOnce())
            ->method('authorizeUser')
            ->with(null, $password)
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['events_manager'] = $emMock;
        $di['db'] = $dbMock;
        $di['auth'] = $authMock;

        $service = new Service();
        $service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('Check your login details');
        $service->login($email, $password, $ip);
    }

    public function testgetAdminCount(): void
    {
        $countResult = 3;

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn($countResult);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $service = new Service();
        $service->setDi($di);

        $result = $service->getAdminsCount();
        $this->assertIsInt($result);
        $this->assertEquals($countResult, $result);
    }

    public function testhasPermissionRoleAdmin(): void
    {
        $member = new \Model_Admin();
        $member->loadBean(new \DummyBean());
        $member->role = 'admin';

        $service = new Service();

        $result = $service->hasPermission($member, 'example');
        $this->assertTrue($result);
    }

    public function testhasPermissionRoleStaffWithEmptyPerms(): void
    {
        $member = new \Model_Admin();
        $member->loadBean(new \DummyBean());
        $member->role = 'staff';

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getPermissions'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getPermissions');

        $extensionServiceMock = $this->getMockBuilder(\Box\Mod\Extension\Service::class)->onlyMethods(['getSpecificModulePermissions'])->getMock();
        $extensionServiceMock->expects($this->atLeastOnce())
            ->method('getSpecificModulePermissions')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $extensionServiceMock);

        $serviceMock->setDi($di);

        $result = $serviceMock->hasPermission($member, 'example');
        $this->assertFalse($result);
    }

    public function testhasPermissionRoleStaffWithNoPerm(): void
    {
        $member = new \Model_Admin();
        $member->loadBean(new \DummyBean());
        $member->role = 'staff';

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getPermissions'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getPermissions')
            ->willReturn(['cart' => [], 'client' => []]);

        $extensionServiceMock = $this->getMockBuilder(\Box\Mod\Extension\Service::class)->onlyMethods(['getSpecificModulePermissions'])->getMock();
        $extensionServiceMock->expects($this->atLeastOnce())
            ->method('getSpecificModulePermissions')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $extensionServiceMock);

        $serviceMock->setDi($di);

        $result = $serviceMock->hasPermission($member, 'example');
        $this->assertFalse($result);
    }

    public function testhasPermissionRoleStaffWithNoMethodPerm(): void
    {
        $member = new \Model_Admin();
        $member->loadBean(new \DummyBean());
        $member->role = 'staff';

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getPermissions'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getPermissions')
            ->willReturn(['example' => [], 'client' => []]);

        $extensionServiceMock = $this->getMockBuilder(\Box\Mod\Extension\Service::class)->onlyMethods(['getSpecificModulePermissions'])->getMock();
        $extensionServiceMock->expects($this->atLeastOnce())
            ->method('getSpecificModulePermissions')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $extensionServiceMock);

        $serviceMock->setDi($di);

        $result = $serviceMock->hasPermission($member, 'example', 'get_list');
        $this->assertFalse($result);
    }

    public function testonAfterClientReplyTicket(): void
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $supportServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getTicketById')
            ->willReturn(new \Model_SupportTicket());
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new Service();

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
            if ($name == 'support') {
                return $supportServiceMock;
            }
            if ($name == 'email') {
                return $emailServiceMock;
            }
        });

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);
        $service->setDi($di);
        $service->onAfterClientReplyTicket($eventMock);
    }

    public function testonAfterClientReplyTicketException(): void
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $supportServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getTicketById')
            ->willReturn(new \Model_SupportTicket());
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->willThrowException(new \Exception('PHPunit controlled Exception'));

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new Service();

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
            if ($name == 'support') {
                return $supportServiceMock;
            }
            if ($name == 'email') {
                return $emailServiceMock;
            }
        });

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);
        $service->setDi($di);
        $service->onAfterClientReplyTicket($eventMock);
    }

    public function testonAfterClientCloseTicket(): void
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $supportServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getTicketById')
            ->willReturn(new \Model_SupportTicket());
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new Service();

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
            if ($name == 'support') {
                return $supportServiceMock;
            }
            if ($name == 'email') {
                return $emailServiceMock;
            }
        });

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);
        $service->setDi($di);
        $service->onAfterClientCloseTicket($eventMock);
    }

    public function testonAfterClientCloseTicketException(): void
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $supportServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getTicketById')
            ->willReturn(new \Model_SupportTicket());
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->willThrowException(new \Exception('PHPunit controlled Exception'));

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new Service();

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
            if ($name == 'support') {
                return $supportServiceMock;
            }
            if ($name == 'email') {
                return $emailServiceMock;
            }
        });

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);
        $service->setDi($di);
        $service->onAfterClientCloseTicket($eventMock);
    }

    public function testonAfterGuestPublicTicketOpen(): void
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $supportServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getPublicTicketById')
            ->willReturn(new \Model_SupportPTicket());
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('publicToApiArray')
            ->willReturn([]);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new Service();

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
            if ($name == 'support') {
                return $supportServiceMock;
            }
            if ($name == 'email') {
                return $emailServiceMock;
            }
        });

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);
        $service->setDi($di);
        $service->onAfterGuestPublicTicketOpen($eventMock);
    }

    public function testonAfterGuestPublicTicketOpenException(): void
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $supportServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getPublicTicketById')
            ->willReturn(new \Model_SupportPTicket());
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('publicToApiArray')
            ->willReturn([]);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->willThrowException(new \Exception('PHPunit controlled Exception'));

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new Service();

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
            if ($name == 'support') {
                return $supportServiceMock;
            }
            if ($name == 'email') {
                return $emailServiceMock;
            }
        });

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);
        $service->setDi($di);
        $service->onAfterGuestPublicTicketOpen($eventMock);
    }

    public function testonAfterGuestPublicTicketReply(): void
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $supportServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getPublicTicketById')
            ->willReturn(new \Model_SupportPTicket());
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('publicToApiArray')
            ->willReturn([]);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new Service();

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
            if ($name == 'support') {
                return $supportServiceMock;
            }
            if ($name == 'email') {
                return $emailServiceMock;
            }
        });

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);
        $service->setDi($di);
        $service->onAfterGuestPublicTicketReply($eventMock);
    }

    public function testonAfterGuestPublicTicketReplyException(): void
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $supportServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getPublicTicketById')
            ->willReturn(new \Model_SupportPTicket());
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('publicToApiArray')
            ->willReturn([]);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->willThrowException(new \Exception('PHPunit controlled Exception'));

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new Service();

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
            if ($name == 'support') {
                return $supportServiceMock;
            }
            if ($name == 'email') {
                return $emailServiceMock;
            }
        });

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);
        $service->setDi($di);
        $service->onAfterGuestPublicTicketReply($eventMock);
    }

    public function testonAfterClientSignUp(): void
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $clientMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $clientMock->expects($this->atLeastOnce())
            ->method('get')
            ->willReturn([]);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new Service();

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($name) use ($clientMock, $emailServiceMock) {
            if ($name == 'client') {
                return $clientMock;
            }
            if ($name == 'email') {
                return $emailServiceMock;
            }
        });

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);
        $service->setDi($di);
        $service->onAfterClientSignUp($eventMock);
    }

    public function testonAfterClientSignUpException(): void
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $clientMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $clientMock->expects($this->atLeastOnce())
            ->method('get')
            ->willReturn([]);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->willThrowException(new \Exception('PHPunit controlled Exception'));

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new Service();

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($name) use ($clientMock, $emailServiceMock) {
            if ($name == 'client') {
                return $clientMock;
            }
            if ($name == 'email') {
                return $emailServiceMock;
            }
        });

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);
        $service->setDi($di);
        $service->onAfterClientSignUp($eventMock);
    }

    public function testonAfterGuestPublicTicketClose(): void
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $supportServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('publicToApiArray')
            ->willReturn([]);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->willThrowException(new \Exception('PHPunit controlled Exception'));

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn(new \Model_SupportPTicket());

        $service = new Service();

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
            if ($name == 'Support') {
                return $supportServiceMock;
            }
            if ($name == 'Email') {
                return $emailServiceMock;
            }
        });
        $di['db'] = $dbMock;

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);
        $service->setDi($di);
        $service->onAfterGuestPublicTicketClose($eventMock);
    }

    public function testonAfterClientOpenTicketModStaffTicketOpen(): void
    {
        $di = new \Pimple\Container();

        $ticketModel = new \Model_SupportTicket();
        $ticketModel->loadBean(new \DummyBean());

        $supportServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getTicketById')
            ->willReturn($ticketModel);

        $supportTicketArray = [];
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($supportTicketArray);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();

        $emailConfig = [
            'to_staff' => true,
            'code' => 'mod_staff_ticket_open',
            'ticket' => $supportTicketArray,
        ];
        $emailServiceMock->expects($this->once())
            ->method('sendTemplate')
            ->with($emailConfig)
            ->willReturn(true);

        $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
            if ($name == 'support') {
                return $supportServiceMock;
            }
            if ($name == 'email') {
                return $emailServiceMock;
            }
        });

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn(null);
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = new \Model_Admin();

        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new Service();
        $service->onAfterClientOpenTicket($eventMock);
    }

    public function testonAfterClientOpenTicketModSupportHelpdeskTicketOpen(): void
    {
        $di = new \Pimple\Container();

        $ticketModel = new \Model_SupportTicket();
        $ticketModel->loadBean(new \DummyBean());

        $supportServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getTicketById')
            ->willReturn($ticketModel);

        $supportTicketArray = [];
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($supportTicketArray);

        $helpdeskModel = new \Model_SupportHelpdesk();
        $helpdeskModel->loadBean(new \DummyBean());
        $helpdeskModel->email = 'helpdesk@support.com';

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();
        $emailConfig = [
            'to' => $helpdeskModel->email,
            'code' => 'mod_support_helpdesk_ticket_open',
            'ticket' => $supportTicketArray,
        ];
        $emailServiceMock->expects($this->once())
            ->method('sendTemplate')
            ->with($emailConfig)
            ->willReturn(true);

        $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
            if ($name == 'support') {
                return $supportServiceMock;
            }
            if ($name == 'email') {
                return $emailServiceMock;
            }
        });

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($helpdeskModel);
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = new \Model_Admin();

        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new Service();
        $service->onAfterClientOpenTicket($eventMock);
    }

    public function testgetList(): void
    {
        $pagerMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['pager'] = $pagerMock;

        $service = new Service();
        $service->setDi($di);

        $result = $service->getList([]);
        $this->assertIsArray($result);
    }

    public static function searchFilters(): array
    {
        return [
            [
                [],
                'SELECT * FROM admin',
                [],
            ],
            [
                ['search' => 'keyword'],
                '(name LIKE :name OR email LIKE :email )',
                [':name' => '%keyword%', ':email' => '%keyword%'],
            ],
            [
                ['status' => 'active'],
                'status = :status',
                [':status' => 'active'],
            ],
            [
                ['no_cron' => 'true'],
                'role != :role',
                [':role' => \Model_Admin::ROLE_CRON],
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('searchFilters')]
    public function testgetSearchQuery(array $data, string $expectedStr, array $expectedParams): void
    {
        $di = new \Pimple\Container();

        $service = new Service();
        $service->setDi($di);
        $result = $service->getSearchQuery($data);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertTrue(str_contains($result[0], $expectedStr), $result[0]);
        $this->assertTrue(array_diff_key($result[1], $expectedParams) == []);
    }

    public function testgetCronAdminAlreadyExists(): void
    {
        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($adminModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $service = new Service();
        $service->setDi($di);

        $result = $service->getCronAdmin();
        $this->assertNotEmpty($result);
        $this->assertInstanceOf('\Model_Admin', $result);
    }

    public function testgetCronAdminCreateCronAdminAndReturn(): void
    {
        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($adminModel);

        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $passwordMock = $this->getMockBuilder(\FOSSBilling\PasswordManager::class)->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['tools'] = new \FOSSBilling\Tools();
        $di['password'] = $passwordMock;

        $service = new Service();
        $service->setDi($di);

        $result = $service->getCronAdmin();
        $this->assertNotEmpty($result);
        $this->assertInstanceOf('\Model_Admin', $result);
    }

    public function testtoModelAdminApiArray(): void
    {
        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \DummyBean());

        $adminGroupModel = new \Model_Admin();
        $adminGroupModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($adminGroupModel);

        $expected =
            [
                'id' => '',
                'role' => '',
                'admin_group_id' => '',
                'email' => '',
                'name' => '',
                'status' => '',
                'signature' => '',
                'created_at' => '',
                'updated_at' => '',
                'protected' => '',
                'group' => ['id' => '', 'name' => ''],
            ];

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $service = new Service();
        $service->setDi($di);
        $result = $service->toModel_AdminApiArray($adminModel);

        $this->assertNotEmpty($result);
        $this->assertIsArray($result);
        $this->assertTrue(count(array_diff(array_keys($expected), array_keys($result))) == 0, 'Missing array key values.');
    }

    public function testupdate(): void
    {
        $data = [
            'email' => 'test@example.com',
            'admin_group_id' => '1',
            'name' => 'testJohn',
            'status' => 'active',
            'signature' => '1345',
        ];

        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \DummyBean());

        $eventsMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $logMock = $this->getMockBuilder('\Box_Log')->getMock();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['hasPermission'])->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('hasPermission')->willReturn(true);

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventsMock;
        $di['logger'] = $logMock;
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);

        $result = $serviceMock->update($adminModel, $data);
        $this->assertTrue($result);
    }

    public function testdelete(): void
    {
        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \DummyBean());

        $eventsMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $logMock = $this->getMockBuilder('\Box_Log')->getMock();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['hasPermission'])->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('hasPermission')->willReturn(true);

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventsMock;
        $di['logger'] = $logMock;
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);

        $result = $serviceMock->delete($adminModel);
        $this->assertTrue($result);
    }

    public function testdeleteProtectedAccount(): void
    {
        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \DummyBean());
        $adminModel->protected = 1;

        $service = new Service();

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('This administrator account is protected and cannot be removed');
        $service->delete($adminModel);
    }

    public function testchangePassword(): void
    {
        $plainTextPassword = 'password';
        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \DummyBean());

        $eventsMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $logMock = $this->getMockBuilder('\Box_Log')->getMock();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $passwordMock = $this->getMockBuilder(\FOSSBilling\PasswordManager::class)->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt')
            ->with($plainTextPassword);

        $profileService = $this->getMockBuilder('\\' . \Box\Mod\Profile\Service::class)->getMock();

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['hasPermission'])->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('hasPermission')->willReturn(true);

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventsMock;
        $di['logger'] = $logMock;
        $di['db'] = $dbMock;
        $di['password'] = $passwordMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $profileService);

        $serviceMock->setDi($di);

        $result = $serviceMock->changePassword($adminModel, $plainTextPassword);
        $this->assertTrue($result);
    }

    public function testcreate(): void
    {
        $data = [
            'email' => 'test@example.com',
            'admin_group_id' => '1',
            'name' => 'testJohn',
            'status' => 'active',
            'password' => '1345',
        ];

        $newId = 1;

        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \DummyBean());

        $systemServiceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('checkLimits');

        $eventsMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($adminModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newId);

        $logMock = $this->getMockBuilder('\Box_Log')->getMock();

        $passwordMock = $this->getMockBuilder(\FOSSBilling\PasswordManager::class)->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt')
            ->with($data['password']);

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['hasPermission'])->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('hasPermission')->willReturn(true);

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventsMock;
        $di['logger'] = $logMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);

        $di['password'] = $passwordMock;

        $serviceMock->setDi($di);

        $result = $serviceMock->create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testcreateException(): void
    {
        $data = [
            'email' => 'test@example.com',
            'admin_group_id' => '1',
            'name' => 'testJohn',
            'status' => 'active',
            'password' => '1345',
        ];

        $newId = 1;

        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \DummyBean());

        $systemServiceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('checkLimits');

        $eventsMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($adminModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willThrowException(new \RedBeanPHP\RedException());

        $logMock = $this->getMockBuilder('\Box_Log')->getMock();

        $passwordMock = $this->getMockBuilder(\FOSSBilling\PasswordManager::class)->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt')
            ->with($data['password']);

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['hasPermission'])->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('hasPermission')->willReturn(true);

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventsMock;
        $di['logger'] = $logMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);

        $di['password'] = $passwordMock;

        $serviceMock->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(788954);
        $this->expectExceptionMessage("Staff member with email {$data['email']} is already registered.");
        $serviceMock->create($data);
    }

    public function testcreateAdmin(): void
    {
        $data = [
            'email' => 'test@example.com',
            'admin_group_id' => '1',
            'name' => 'testJohn',
            'status' => 'active',
            'password' => '1345',
        ];

        $newId = 1;

        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($adminModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newId);

        $logMock = $this->getMockBuilder('\Box_Log')->getMock();

        $systemService = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();

        $passwordMock = $this->getMockBuilder(\FOSSBilling\PasswordManager::class)->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt')
            ->with($data['password']);

        $di = new \Pimple\Container();
        $di['logger'] = $logMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function ($serviceName) use ($systemService) {
            if ($serviceName == 'system') {
                return $systemService;
            }
        });
        $di['password'] = $passwordMock;

        $service = new Service();
        $service->setDi($di);

        $result = $service->createAdmin($data);
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testgetAdminGroupPair(): void
    {
        $rows = [
            [
                'id' => '1',
                'name' => 'First Jogh',
            ],
            [
                'id' => '2',
                'name' => 'Another Smith',
            ],
        ];

        $expected = [
            1 => 'First Jogh',
            2 => 'Another Smith',
        ];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($rows);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $service = new Service();
        $service->setDi($di);

        $result = $service->getAdminGroupPair();

        $this->assertEquals($expected, $result);
        $this->assertIsArray($result);
    }

    public function testgetAdminGroupSearchQuery(): void
    {
        $service = new Service();

        $result = $service->getAdminGroupSearchQuery([]);

        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);
    }

    public function testcreateGroup(): void
    {
        $adminGroupModel = new \Model_AdminGroup();
        $adminGroupModel->loadBean(new \DummyBean());
        $newGroupId = 1;

        $systemServiceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('checkLimits');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($adminGroupModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newGroupId);

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['hasPermission'])->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('hasPermission')->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);

        $serviceMock->setDi($di);

        $result = $serviceMock->createGroup('new_group_name');
        $this->assertIsInt($result);
        $this->assertEquals($newGroupId, $result);
    }

    public function testtoAdminGroupApiArray(): void
    {
        $adminGroupModel = new \Model_AdminGroup();
        $adminGroupModel->loadBean(new \DummyBean());

        $expected =
            [
                'id' => '',
                'name' => '',
                'created_at' => '',
                'updated_at' => '',
            ];

        $service = new Service();

        $result = $service->toAdminGroupApiArray($adminGroupModel);

        $this->assertIsArray($result);
        $this->assertTrue(count(array_diff(array_keys($expected), array_keys($result))) == 0, 'Missing array key values.');
    }

    public function testdeleteGroup(): void
    {
        $adminGroupModel = new \Model_AdminGroup();
        $adminGroupModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(0);

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['hasPermission'])->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('hasPermission')->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->deleteGroup($adminGroupModel);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testdeleteGroupDeleteAdminGroup(): void
    {
        $adminGroupModel = new \Model_AdminGroup();
        $adminGroupModel->loadBean(new \DummyBean());
        $adminGroupModel->id = 1;

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['hasPermission'])->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('hasPermission')->willReturn(true);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Administrators group cannot be removed');
        $serviceMock->deleteGroup($adminGroupModel);
    }

    public function testdeleteGroupGroupHasMembers(): void
    {
        $adminGroupModel = new \Model_AdminGroup();
        $adminGroupModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(2);

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['hasPermission'])->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('hasPermission')->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Cannot remove group which has staff members');
        $serviceMock->deleteGroup($adminGroupModel);
    }

    public function testupdateGroup(): void
    {
        $adminGroupModel = new \Model_AdminGroup();
        $adminGroupModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['hasPermission'])->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('hasPermission')->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $data = ['name' => 'OhExampleName'];
        $result = $serviceMock->updateGroup($adminGroupModel, $data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public static function ActivityAdminHistorySearchFilters(): array
    {
        return [
            [
                [],
                'SELECT m.*, a.email, a.name',
                [],
            ],
            [
                ['search' => 'keyword'],
                'a.name LIKE :name OR a.id LIKE :id OR a.email LIKE :email',
                ['name' => '%keyword%', 'id' => '%keyword%', 'email' => '%keyword%'],
            ],
            [
                ['admin_id' => '2'],
                'm.admin_id = :admin_id',
                ['admin_id' => '2'],
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ActivityAdminHistorySearchFilters')]
    public function testgetActivityAdminHistorySearchQuery(array $data, string $expectedStr, array $expectedParams): void
    {
        $di = new \Pimple\Container();

        $service = new Service();
        $service->setDi($di);
        $result = $service->getActivityAdminHistorySearchQuery($data);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertTrue(str_contains($result[0], $expectedStr), $result[0]);
        $this->assertTrue(array_diff_key($result[1], $expectedParams) == []);
    }

    public function testtoActivityAdminHistoryApiArray(): void
    {
        $adminHistoryModel = new \Model_ActivityAdminHistory();
        $adminHistoryModel->loadBean(new \DummyBean());
        $adminHistoryModel->admin_id = 2;

        $expected = [
            'id' => '',
            'ip' => '',
            'created_at' => '',
            'staff' => [
                'id' => $adminHistoryModel->admin_id,
                'name' => '',
                'email' => '',
            ],
        ];

        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \DummyBean());
        $adminModel->id = 2;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($adminModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $service = new Service();
        $service->setDi($di);
        $result = $service->toActivityAdminHistoryApiArray($adminHistoryModel);

        $this->assertNotEmpty($result);
        $this->assertIsArray($result);
        $this->assertTrue(count(array_diff(array_keys($expected), array_keys($result))) == 0, 'Missing array key values.');
    }

    public function testdeleteLoginHistory(): void
    {
        $adminHistoryModel = new \Model_ActivityAdminHistory();
        $adminHistoryModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $service = new Service();
        $service->setDi($di);

        $result = $service->deleteLoginHistory($adminHistoryModel);
        $this->assertTrue($result);
    }

    public function testsetPermissions(): void
    {
        $pdoStatementMock = $this->getMockBuilder('\\' . PdoStatementMock::class)
            ->getMock();
        $pdoStatementMock->expects($this->atLeastOnce())
            ->method('execute');

        $pdoMock = $this->getMockBuilder('\\' . PdoMock::class)->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($pdoStatementMock);

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['hasPermission'])->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('hasPermission')->willReturn(true);

        $di = new \Pimple\Container();
        $di['pdo'] = $pdoMock;
        $serviceMock->setDi($di);

        $member_id = 1;
        $result = $serviceMock->setPermissions($member_id, []);
        $this->assertTrue($result);
    }

    public function testgetPermissionsPermAreEmpty(): void
    {
        $pdoStatementMock = $this->getMockBuilder('\\' . PdoStatementMock::class)
            ->getMock();
        $pdoStatementMock->expects($this->atLeastOnce())
            ->method('execute');
        $pdoStatementMock->expects($this->atLeastOnce())
            ->method('fetchColumn')
            ->willReturn('{}');

        $pdoMock = $this->getMockBuilder('\\' . PdoMock::class)->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($pdoStatementMock);

        $service = new Service();

        $di = new \Pimple\Container();
        $di['pdo'] = $pdoMock;
        $service->setDi($di);

        $member_id = 1;
        $result = $service->getPermissions($member_id);
        $this->assertIsArray($result);
        $this->assertEquals([], $result);
    }

    public function testgetPermissions(): void
    {
        $pdoStatementMock = $this->getMockBuilder('\\' . PdoStatementMock::class)
            ->getMock();
        $pdoStatementMock->expects($this->atLeastOnce())
            ->method('execute');
        $queryResult = '{"id" : "1"}';
        $pdoStatementMock->expects($this->atLeastOnce())
            ->method('fetchColumn')
            ->willReturn($queryResult);

        $pdoMock = $this->getMockBuilder('\\' . PdoMock::class)->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($pdoStatementMock);

        $service = new Service();

        $di = new \Pimple\Container();
        $di['pdo'] = $pdoMock;
        $service->setDi($di);

        $member_id = 1;
        $expected = json_decode($queryResult ?? '', true);
        $result = $service->getPermissions($member_id);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testauthorizeAdminDidntFoundEmail(): void
    {
        $email = 'example@fossbilling.vm';
        $password = '123456';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('Admin', 'email = ? AND status = ? AND role != ?')
            ->willReturn(null);

        $authMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authMock->expects($this->atLeastOnce())
            ->method('authorizeUser')
            ->with(null, $password)
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['auth'] = $authMock;

        $service = new Service();
        $service->setDi($di);

        $result = $service->authorizeAdmin($email, $password);
        $this->assertNull($result);
    }

    public function testauthorizeAdmin(): void
    {
        $email = 'example@fossbilling.vm';
        $password = '123456';

        $model = new \Model_Admin();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('Admin', 'email = ? AND status = ? AND role != ?')
            ->willReturn($model);

        $authMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authMock->expects($this->atLeastOnce())
            ->method('authorizeUser')
            ->with($model, $password)
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['auth'] = $authMock;

        $service = new Service();
        $service->setDi($di);

        $result = $service->authorizeAdmin($email, $password);
        $this->assertInstanceOf('\Model_Admin', $result);
    }
}
