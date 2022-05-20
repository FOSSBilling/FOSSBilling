<?php

namespace Box\Mod\Staff;

class PdoMock extends \PDO
{
    public function __construct() { }
}

class PdoStatementMock extends \PDOStatement
{
    public function __construct() { }
}

class ServiceTest extends \BBTestCase
{

    public function testLogin()
    {
        $email    = 'email@domain.com';
        $password = 'pass';
        $ip       = '127.0.0.1';

        $admin = new \Model_Admin();
        $admin->loadBean(new \RedBeanPHP\OODBBean());
        $admin->id    = 1;
        $admin->email = $email;
        $admin->name  = 'Admin';
        $admin->role  = 'admin';


        $emMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('fire')
            ->will($this->returnValue(true));

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($admin));

        $sessionMock = $this->getMockBuilder('\Box_Session')
            ->disableOriginalConstructor()
            ->getMock();
        $sessionMock->expects($this->atLeastOnce())
            ->method('set')
            ->will($this->returnValue(null));

        $authMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authMock->expects($this->atLeastOnce())
            ->method('authorizeUser')
            ->with($admin, $password)
            ->willReturn($admin);

        $di                   = new \Box_Di();
        $di['events_manager'] = $emMock;
        $di['db']             = $dbMock;
        $di['session']        = $sessionMock;
        $di['logger']         = new \Box_Log();
        $di['auth']           = $authMock;

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $result = $service->login($email, $password, $ip);

        $expected = array(
            'id'    => 1,
            'email' => $email,
            'name'  => 'Admin',
            'role'  => 'admin',
        );

        $this->assertEquals($expected, $result);
    }

    public function testLogin_Exception()
    {
        $email    = 'email@domain.com';
        $password = 'pass';
        $ip       = '127.0.0.1';

        $emMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('fire')
            ->will($this->returnValue(true));

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(null));

        $di                   = new \Box_Di();
        $di['events_manager'] = $emMock;
        $di['db']             = $dbMock;

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('Check your login details');
        $service->login($email, $password, $ip);
    }

    public function testgetAdminCount()
    {
        $countResult = 3;

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue($countResult));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $result = $service->getAdminsCount();
        $this->assertIsInt($result);
        $this->assertEquals($countResult, $result);
    }

    public function testhasPermissionRoleAdmin()
    {
        $member = new \Model_Client();
        $member->loadBean(new \RedBeanPHP\OODBBean());
        $member->role = 'admin';

        $service = new \Box\Mod\Staff\Service();

        $result = $service->hasPermission($member, 'example');
        $this->assertTrue($result);
    }

    public function testhasPermissionRoleStaffWithEmptyPerms()
    {
        $member = new \Model_Client();
        $member->loadBean(new \RedBeanPHP\OODBBean());
        $member->role = 'staff';

        $serviceMock = $this->getMockBuilder('\Box\Mod\Staff\Service')
            ->setMethods(array('getPermissions'))
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getPermissions');

        $result = $serviceMock->hasPermission($member, 'example');
        $this->assertFalse($result);
    }

    public function testhasPermissionRoleStaffWithNoPerm()
    {
        $member = new \Model_Client();
        $member->loadBean(new \RedBeanPHP\OODBBean());
        $member->role = 'staff';

        $serviceMock = $this->getMockBuilder('\Box\Mod\Staff\Service')
            ->setMethods(array('getPermissions'))
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getPermissions')
            ->will($this->returnValue(array('cart' => array(), 'client' => array())));


        $result = $serviceMock->hasPermission($member, 'example');
        $this->assertFalse($result);
    }

    public function testhasPermissionRoleStaffWithNoMethodPerm()
    {
        $member = new \Model_Client();
        $member->loadBean(new \RedBeanPHP\OODBBean());
        $member->role = 'staff';

        $serviceMock = $this->getMockBuilder('\Box\Mod\Staff\Service')
            ->setMethods(array('getPermissions'))
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getPermissions')
            ->will($this->returnValue(array('example' => array(), 'client' => array())));


        $result = $serviceMock->hasPermission($member, 'example', 'get_list');
        $this->assertFalse($result);
    }

    public function testhasPermissionRoleStaffWithGoodPerms()
    {
        $member = new \Model_Client();
        $member->loadBean(new \RedBeanPHP\OODBBean());
        $member->role = 'staff';

        $serviceMock = $this->getMockBuilder('\Box\Mod\Staff\Service')
            ->setMethods(array('getPermissions'))
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getPermissions')
            ->will($this->returnValue(array('example' => array('get_list'), 'client' => array())));


        $result = $serviceMock->hasPermission($member, 'example', 'get_list');
        $this->assertTrue($result);
    }

    public function testonAfterClientReplyTicket()
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $supportServiceMock = $this->getMockBuilder('\Box\Mod\Support\Service')->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getTicketById')
            ->will($this->returnValue(new \Model_SupportTicket()));
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new \Box\Mod\Staff\Service();

        $di                = new \Box_Di();
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
            ->will($this->returnValue($di));
        $service->setDi($di);
        $service->onAfterClientReplyTicket($eventMock);
    }

    public function testonAfterClientReplyTicket_Exception()
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $supportServiceMock = $this->getMockBuilder('\Box\Mod\Support\Service')->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getTicketById')
            ->will($this->returnValue(new \Model_SupportTicket()));
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->willThrowException(new \Exception('PHPunit controlled Exception'));

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new \Box\Mod\Staff\Service();

        $di                = new \Box_Di();
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
            ->will($this->returnValue($di));
        $service->setDi($di);
        $service->onAfterClientReplyTicket($eventMock);
    }

    public function testonAfterClientCloseTicket()
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $supportServiceMock = $this->getMockBuilder('\Box\Mod\Support\Service')->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getTicketById')
            ->will($this->returnValue(new \Model_SupportTicket()));
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new \Box\Mod\Staff\Service();

        $di                = new \Box_Di();
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
            ->will($this->returnValue($di));
        $service->setDi($di);
        $service->onAfterClientCloseTicket($eventMock);
    }

    public function testonAfterClientCloseTicket_Exception()
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $supportServiceMock = $this->getMockBuilder('\Box\Mod\Support\Service')->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getTicketById')
            ->will($this->returnValue(new \Model_SupportTicket()));
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->willThrowException(new \Exception('PHPunit controlled Exception'));

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new \Box\Mod\Staff\Service();

        $di                = new \Box_Di();
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
            ->will($this->returnValue($di));
        $service->setDi($di);
        $service->onAfterClientCloseTicket($eventMock);
    }

    public function testonAfterGuestPublicTicketOpen()
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $supportServiceMock = $this->getMockBuilder('\Box\Mod\Support\Service')->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getPublicTicketById')
            ->will($this->returnValue(new \Model_SupportPTicket()));
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('publicToApiArray')
            ->will($this->returnValue(array()));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new \Box\Mod\Staff\Service();

        $di                = new \Box_Di();
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
            ->will($this->returnValue($di));
        $service->setDi($di);
        $service->onAfterGuestPublicTicketOpen($eventMock);
    }

    public function testonAfterGuestPublicTicketOpen_Exception()
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $supportServiceMock = $this->getMockBuilder('\Box\Mod\Support\Service')->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getPublicTicketById')
            ->will($this->returnValue(new \Model_SupportPTicket()));
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('publicToApiArray')
            ->will($this->returnValue(array()));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->willThrowException(new \Exception('PHPunit controlled Exception'));

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new \Box\Mod\Staff\Service();

        $di                = new \Box_Di();
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
            ->will($this->returnValue($di));
        $service->setDi($di);
        $service->onAfterGuestPublicTicketOpen($eventMock);
    }

    public function testonAfterGuestPublicTicketReply()
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $supportServiceMock = $this->getMockBuilder('\Box\Mod\Support\Service')->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getPublicTicketById')
            ->will($this->returnValue(new \Model_SupportPTicket()));
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('publicToApiArray')
            ->will($this->returnValue(array()));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new \Box\Mod\Staff\Service();

        $di                = new \Box_Di();
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
            ->will($this->returnValue($di));
        $service->setDi($di);
        $service->onAfterGuestPublicTicketReply($eventMock);
    }

    public function testonAfterGuestPublicTicketReply_Exception()
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $supportServiceMock = $this->getMockBuilder('\Box\Mod\Support\Service')->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getPublicTicketById')
            ->will($this->returnValue(new \Model_SupportPTicket()));
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('publicToApiArray')
            ->will($this->returnValue(array()));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->willThrowException(new \Exception('PHPunit controlled Exception'));

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new \Box\Mod\Staff\Service();

        $di                = new \Box_Di();
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
            ->will($this->returnValue($di));
        $service->setDi($di);
        $service->onAfterGuestPublicTicketReply($eventMock);
    }

    public function testonAfterClientSignUp()
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $clientMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $clientMock->expects($this->atLeastOnce())
            ->method('get')
            ->will($this->returnValue(array()));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new \Box\Mod\Staff\Service();

        $di                = new \Box_Di();
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
            ->will($this->returnValue($di));
        $service->setDi($di);
        $service->onAfterClientSignUp($eventMock);
    }

    public function testonAfterClientSignUp_Exception()
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $clientMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $clientMock->expects($this->atLeastOnce())
            ->method('get')
            ->will($this->returnValue(array()));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->willThrowException(new \Exception('PHPunit controlled Exception'));

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $service = new \Box\Mod\Staff\Service();

        $di                = new \Box_Di();
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
            ->will($this->returnValue($di));
        $service->setDi($di);
        $service->onAfterClientSignUp($eventMock);
    }

    public function testonAfterGuestPublicTicketClose()
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $supportServiceMock = $this->getMockBuilder('\Box\Mod\Support\Service')->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('publicToApiArray')
            ->will($this->returnValue(array()));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->willThrowException(new \Exception('PHPunit controlled Exception'));

        $eventMock->expects($this->atLeastOnce())
            ->method('getparameters');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue(new \Model_SupportPTicket()));

        $service = new \Box\Mod\Staff\Service();

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
            if ($name == 'Support') {
                return $supportServiceMock;
            }
            if ($name == 'Email') {
                return $emailServiceMock;
            }
        });
        $di['db']          = $dbMock;

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));
        $service->setDi($di);
        $service->onAfterGuestPublicTicketClose($eventMock);
    }

    public function testonAfterClientOpenTicket_mod_staff_ticket_open()
    {
        $di = new \Box_Di();

        $ticketModel = new \Model_SupportTicket();
        $ticketModel->loadBean(new \RedBeanPHP\OODBBean());

        $supportServiceMock = $this->getMockBuilder('\Box\Mod\Support\Service')->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getTicketById')
            ->will($this->returnValue($ticketModel));

        $supportTicketArray = array();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($supportTicketArray);

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();

        $emailConfig = array(
            'to_staff' => true,
            'code' => 'mod_staff_ticket_open',
            'ticket' => $supportTicketArray,
        );
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

        $service = new \Box\Mod\Staff\Service();
        $service->onAfterClientOpenTicket($eventMock);
    }

    public function testonAfterClientOpenTicket_mod_support_helpdesk_ticket_open()
    {
        $di = new \Box_Di();

        $ticketModel = new \Model_SupportTicket();
        $ticketModel->loadBean(new \RedBeanPHP\OODBBean());

        $supportServiceMock = $this->getMockBuilder('\Box\Mod\Support\Service')->getMock();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('getTicketById')
            ->will($this->returnValue($ticketModel));

        $supportTicketArray = array();
        $supportServiceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($supportTicketArray);

        $helpdeskModel = new \Model_SupportHelpdesk();
        $helpdeskModel->loadBean(new \RedBeanPHP\OODBBean());
        $helpdeskModel->email = 'helpdesk@support.com';

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailConfig = array(
            'to' => $helpdeskModel->email,
            'code' => 'mod_support_helpdesk_ticket_open',
            'ticket' => $supportTicketArray,
        );
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

        $service = new \Box\Mod\Staff\Service();
        $service->onAfterClientOpenTicket($eventMock);
    }

    public function testgetList()
    {
        $pagerMock = $this->getMockBuilder('\Box_Pagination')->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue(array()));

        $di              = new \Box_Di();
        $di['pager']     = $pagerMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $result = $service->getList(array());
        $this->assertIsArray($result);
    }

    public function searchFilters()
    {
        return array(
            array(
                array(),
                'SELECT * FROM admin',
                array()),
            array(
                array('search' => 'keyword'),
                '(name LIKE :name OR email LIKE :email )',
                array(':name' => '%keyword%', ':email' => '%keyword%')),
            array(
                array('status' => 'active'),
                'status = :status',
                array(':status' => 'active')),
            array(
                array('no_cron' => 'true'),
                'role != :role',
                array(':role' => \Model_Admin::ROLE_CRON)),
        );
    }

    /**
     * @dataProvider searchFilters
     */
    public function testgetSearchQuery($data, $expectedStr, $expectedParams)
    {
        $di              = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $service         = new \Box\Mod\Staff\Service();
        $service->setDi($di);
        $result = $service->getSearchQuery($data);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertTrue(strpos($result[0], $expectedStr) !== false, $result[0]);
        $this->assertTrue(array_diff_key($result[1], $expectedParams) == array());
    }

    public function testgetCronAdminAlreadyExists()
    {
        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder(('\Box_Database'))->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($adminModel));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $result = $service->getCronAdmin();
        $this->assertNotEmpty($result);
        $this->assertInstanceOf('\Model_Admin', $result);
    }

    public function testgetCronAdminCreateCronAdminAndReturn()
    {
        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder(('\Box_Database'))->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(null));

        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($adminModel));

        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $passwordMock = $this->getMockBuilder('\Box_Password')->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt');

        $di             = new \Box_Di();
        $di['db']       = $dbMock;
        $di['tools']    = new \Box_Tools();
        $di['password'] = $passwordMock;

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $result = $service->getCronAdmin();
        $this->assertNotEmpty($result);
        $this->assertInstanceOf('\Model_Admin', $result);
    }

    public function testtoModel_AdminApiiArray()
    {
        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \RedBeanPHP\OODBBean());

        $adminGroupModel = new \Model_Admin();
        $adminGroupModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder(('\Box_Database'))->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($adminGroupModel));

        $expected =
            array(
                'id'             => '',
                'role'           => '',
                'admin_group_id' => '',
                'email'          => '',
                'name'           => '',
                'status'         => '',
                'signature'      => '',
                'created_at'     => '',
                'updated_at'     => '',
                'protected'      => '',
                'group'          => array('id' => '', 'name' => ''),
            );

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);
        $result = $service->toModel_AdminApiiArray($adminModel);

        $this->assertNotEmpty($result);
        $this->assertIsArray($result);
        $this->assertTrue(count(array_diff(array_keys($expected), array_keys($result))) == 0, 'Missing array key values.');
    }

    public function testupdate()
    {
        $data = array(
            'email'          => 'test@example.com',
            'admin_group_id' => '1',
            'name'           => 'testJohn',
            'status'         => 'active',
            'signature'      => '1345',
        );

        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \RedBeanPHP\OODBBean());

        $eventsMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $logMock = $this->getMockBuilder('\Box_Log')->getMock();

        $dbMock = $this->getMockBuilder(('\Box_Database'))->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di                   = new \Box_Di();
        $di['events_manager'] = $eventsMock;
        $di['logger']         = $logMock;
        $di['db']             = $dbMock;
        $di['array_get']      = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $result = $service->update($adminModel, $data);
        $this->assertTrue($result);
    }

    public function testdelete()
    {
        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \RedBeanPHP\OODBBean());

        $eventsMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $logMock = $this->getMockBuilder('\Box_Log')->getMock();

        $dbMock = $this->getMockBuilder(('\Box_Database'))->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di                   = new \Box_Di();
        $di['events_manager'] = $eventsMock;
        $di['logger']         = $logMock;
        $di['db']             = $dbMock;

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $result = $service->delete($adminModel);
        $this->assertTrue($result);
    }

    public function testdeleteProtectedAccount()
    {
        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \RedBeanPHP\OODBBean());
        $adminModel->protected = 1;

        $service = new \Box\Mod\Staff\Service();

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('This administrator account is protected and can not be removed');
        $service->delete($adminModel);
    }

    public function testchangePassword()
    {
        $plainTextPassword = 'password';
        $adminModel        = new \Model_Admin();
        $adminModel->loadBean(new \RedBeanPHP\OODBBean());

        $eventsMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $logMock = $this->getMockBuilder('\Box_Log')->getMock();

        $dbMock = $this->getMockBuilder(('\Box_Database'))->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $passwordMock = $this->getMockBuilder('\Box_Password')->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt')
            ->with($plainTextPassword);

        $di                   = new \Box_Di();
        $di['events_manager'] = $eventsMock;
        $di['logger']         = $logMock;
        $di['db']             = $dbMock;
        $di['password']       = $passwordMock;

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $result = $service->changePassword($adminModel, $plainTextPassword);
        $this->assertTrue($result);
    }

    public function testcreate()
    {
        $data = array(
            'email'          => 'test@example.com',
            'admin_group_id' => '1',
            'name'           => 'testJohn',
            'status'         => 'active',
            'password'       => '1345',
        );

        $newId = 1;

        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \RedBeanPHP\OODBBean());

        $systemServiceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('checkLimits');

        $eventsMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder(('\Box_Database'))->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($adminModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($newId));

        $logMock = $this->getMockBuilder('\Box_Log')->getMock();

        $passwordMock = $this->getMockBuilder('\Box_Password')->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt')
            ->with($data['password']);

        $di                   = new \Box_Di();
        $di['events_manager'] = $eventsMock;
        $di['logger']         = $logMock;
        $di['db']             = $dbMock;
        $di['mod_service']    = $di->protect(function () use ($systemServiceMock) { return $systemServiceMock; });
        $di['password']       = $passwordMock;
        $di['array_get']      = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $result = $service->create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testcreate_Exception()
    {
        $data = array(
            'email'          => 'test@example.com',
            'admin_group_id' => '1',
            'name'           => 'testJohn',
            'status'         => 'active',
            'password'       => '1345',
        );

        $newId = 1;

        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \RedBeanPHP\OODBBean());

        $systemServiceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('checkLimits');

        $eventsMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder(('\Box_Database'))->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($adminModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willThrowException(new \RedBeanPHP\RedException());

        $logMock = $this->getMockBuilder('\Box_Log')->getMock();

        $passwordMock = $this->getMockBuilder('\Box_Password')->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt')
            ->with($data['password']);

        $di                   = new \Box_Di();
        $di['events_manager'] = $eventsMock;
        $di['logger']         = $logMock;
        $di['db']             = $dbMock;
        $di['mod_service']    = $di->protect(function () use ($systemServiceMock) { return $systemServiceMock; });
        $di['password']       = $passwordMock;
        $di['array_get']      = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(788954);
        $this->expectExceptionMessage(sprintf('Staff member with email %s is already registered', $data['email']));
        $service->create($data);
    }

    public function testcreateAdmin()
    {
        $data = array(
            'email'          => 'test@example.com',
            'admin_group_id' => '1',
            'name'           => 'testJohn',
            'status'         => 'active',
            'password'       => '1345',
        );

        $newId = 1;

        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder(('\Box_Database'))->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($adminModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($newId));

        $logMock = $this->getMockBuilder('\Box_Log')->getMock();

        $systemService = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue');

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailServiceMock->expects($this->atLeastOnce())
            ->method('sendMail');

        $urlMock = $this->getMockBuilder('\Box_Url')->getMock();
        $urlMock->expects($this->atLeastOnce())
            ->method('link')
            ->willReturn('');
        $urlMock->expects($this->atLeastOnce())
            ->method('adminLink')
            ->willReturn('');


        $passwordMock = $this->getMockBuilder('\Box_Password')->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt')
            ->with($data['password']);

        $di                = new \Box_Di();
        $di['logger']      = $logMock;
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function ($serviceName) use ($systemService, $emailServiceMock) {
            if ('system' == $serviceName) {
                return $systemService;
            }
            if ('Email' == $serviceName) {
                return $emailServiceMock;
            }
        });
        $di['url']         = $urlMock;
        $di['password']    = $passwordMock;

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $result = $service->createAdmin($data);
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testgetAdminGroupPair()
    {
        $rows = array(
            array(
                'id'   => '1',
                'name' => 'First Jogh',
            ),
            array(
                'id'   => '2',
                'name' => 'Another Smith',
            ),
        );

        $expected = array(
            1 => 'First Jogh',
            2 => 'Another Smith',
        );

        $dbMock = $this->getMockBuilder(('\Box_Database'))->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->will($this->returnValue($rows));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $result = $service->getAdminGroupPair();

        $this->assertEquals($expected, $result);
        $this->assertIsArray($result);
    }

    public function testgetAdminGroupSearchQuery()
    {
        $service = new \Box\Mod\Staff\Service();

        $result = $service->getAdminGroupSearchQuery(array());

        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);
    }

    public function testcreateGroup()
    {
        $adminGroupModel = new \Model_AdminGroup();
        $adminGroupModel->loadBean(new \RedBeanPHP\OODBBean());
        $newGroupId = 1;

        $systemServiceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('checkLimits');

        $dbMock = $this->getMockBuilder(('\Box_Database'))->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($adminGroupModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($newGroupId));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['logger']      = new \Box_Log();
        $di['mod_service'] = $di->protect(function () use ($systemServiceMock) { return $systemServiceMock; });

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $result = $service->createGroup('new_group_name');
        $this->assertIsInt($result);
        $this->assertEquals($newGroupId, $result);
    }

    public function testtoAdminGroupApiArray()
    {
        $adminGroupModel = new \Model_AdminGroup();
        $adminGroupModel->loadBean(new \RedBeanPHP\OODBBean());

        $expected =
            array(
                'id'         => '',
                'name'       => '',
                'created_at' => '',
                'updated_at' => '',
            );

        $service = new \Box\Mod\Staff\Service();

        $result = $service->toAdminGroupApiArray($adminGroupModel);

        $this->assertIsArray($result);
        $this->assertTrue(count(array_diff(array_keys($expected), array_keys($result))) == 0, 'Missing array key values.');
    }

    public function testdeleteGroup()
    {
        $adminGroupModel = new \Model_AdminGroup();
        $adminGroupModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder(('\Box_Database'))->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(0));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = new \Box_Log();

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $result = $service->deleteGroup($adminGroupModel);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testdeleteGroupDeleteAdminGroup()
    {
        $adminGroupModel = new \Model_AdminGroup();
        $adminGroupModel->loadBean(new \RedBeanPHP\OODBBean());
        $adminGroupModel->id = 1;

        $service = new \Box\Mod\Staff\Service();

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Administrators group can not be removed');
        $service->deleteGroup($adminGroupModel);
    }

    public function testdeleteGroupGroupHasMembers()
    {
        $adminGroupModel = new \Model_AdminGroup();
        $adminGroupModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder(('\Box_Database'))->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(2));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Can not remove group which has staff members');
        $service->deleteGroup($adminGroupModel);
    }

    public function testupdateGroup()
    {
        $adminGroupModel = new \Model_AdminGroup();
        $adminGroupModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder(('\Box_Database'))->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = new \Box_Log();

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $data   = array('name' => 'OhExampleName');
        $result = $service->updateGroup($adminGroupModel, $data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function ActivityAdminHistorySearchFilters()
    {
        return array(
            array(
                array(),
                'SELECT m.*, a.email, a.name',
                array()),
            array(
                array('search' => 'keyword'),
                'a.name LIKE :name OR a.id LIKE :id OR a.email LIKE :email',
                array('name' => '%keyword%', 'id' => '%keyword%', 'email' => '%keyword%')),
            array(
                array('admin_id' => '2'),
                'm.admin_id = :admin_id',
                array('admin_id' => '2')),
        );
    }

    /**
     * @dataProvider ActivityAdminHistorySearchFilters
     */

    public function testgetActivityAdminHistorySearchQuery($data, $expectedStr, $expectedParams)
    {
        $di              = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $service         = new \Box\Mod\Staff\Service();
        $service->setDi($di);
        $result = $service->getActivityAdminHistorySearchQuery($data);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertTrue(strpos($result[0], $expectedStr) !== false, $result[0]);
        $this->assertTrue(array_diff_key($result[1], $expectedParams) == array());
    }

    public function testtoActivityAdminHistoryApiArray()
    {
        $adminHistoryModel = new \Model_ActivityAdminHistory();
        $adminHistoryModel->loadBean(new \RedBeanPHP\OODBBean());
        $adminHistoryModel->admin_id = 2;

        $expected = array(
            'id'         => '',
            'ip'         => '',
            'created_at' => '',
            'staff'      => array(
                'id'    => $adminHistoryModel->admin_id,
                'name'  => '',
                'email' => '',

            ),
        );

        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \RedBeanPHP\OODBBean());
        $adminModel->id = 2;

        $dbMock = $this->getMockBuilder(('\Box_Database'))->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($adminModel));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);
        $result = $service->toActivityAdminHistoryApiArray($adminHistoryModel);

        $this->assertNotEmpty($result);
        $this->assertIsArray($result);
        $this->assertTrue(count(array_diff(array_keys($expected), array_keys($result))) == 0, 'Missing array key values.');
    }

    public function testdeleteLoginHistory()
    {
        $adminHistoryModel = new \Model_ActivityAdminHistory();
        $adminHistoryModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder(('\Box_Database'))->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $result = $service->deleteLoginHistory($adminHistoryModel);
        $this->assertTrue($result);
    }

    public function testsetPermissions()
    {
        $pdoStatementMock = $this->getMockBuilder('\Box\Mod\Staff\PdoStatementMock')
            ->getMock();
        $pdoStatementMock->expects($this->atLeastOnce())
            ->method('execute');

        $pdoMock = $this->getMockBuilder('\Box\Mod\Staff\PdoMock')->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->will($this->returnValue($pdoStatementMock));

        $service = new \Box\Mod\Staff\Service();

        $di        = new \Box_Di();
        $di['pdo'] = $pdoMock;
        $service->setDi($di);

        $member_id = 1;
        $result    = $service->setPermissions($member_id, array());
        $this->assertTrue($result);
    }

    public function testgetPermissions_PermAreEmpty()
    {
        $pdoStatementMock = $this->getMockBuilder('\Box\Mod\Staff\PdoStatementMock')
            ->getMock();
        $pdoStatementMock->expects($this->atLeastOnce())
            ->method('execute');
        $pdoStatementMock->expects($this->atLeastOnce())
            ->method('fetchColumn')
            ->will($this->returnValue('{}'));

        $pdoMock = $this->getMockBuilder('\Box\Mod\Staff\PdoMock')->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->will($this->returnValue($pdoStatementMock));

        $service = new \Box\Mod\Staff\Service();

        $di        = new \Box_Di();
        $di['pdo'] = $pdoMock;
        $service->setDi($di);

        $member_id = 1;
        $result    = $service->getPermissions($member_id);
        $this->assertIsArray($result);
        $this->assertEquals(array(), $result);
    }

    public function testgetPermissions()
    {
        $pdoStatementMock = $this->getMockBuilder('\Box\Mod\Staff\PdoStatementMock')
            ->getMock();
        $pdoStatementMock->expects($this->atLeastOnce())
            ->method('execute');
        $queryResult = '{"id" : "1"}';
        $pdoStatementMock->expects($this->atLeastOnce())
            ->method('fetchColumn')
            ->will($this->returnValue($queryResult));

        $pdoMock = $this->getMockBuilder('\Box\Mod\Staff\PdoMock')->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->will($this->returnValue($pdoStatementMock));

        $service = new \Box\Mod\Staff\Service();

        $di        = new \Box_Di();
        $di['pdo'] = $pdoMock;
        $service->setDi($di);

        $member_id = 1;
        $expected  = json_decode($queryResult, 1);
        $result    = $service->getPermissions($member_id);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }


    public function testauthorizeAdmin_DidntFoundEmail()
    {
        $email    = 'example@boxbilling.vm';
        $password = '123456';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('Admin', 'email = ? AND status = ?')
            ->willReturn(null);

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $result = $service->authorizeAdmin($email, $password);
        $this->assertNull($result);
    }

    public function testauthorizeAdmin()
    {
        $email    = 'example@boxbilling.vm';
        $password = '123456';

        $model = new \Model_Admin();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('Admin', 'email = ? AND status = ?')
            ->willReturn($model);

        $authMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authMock->expects($this->atLeastOnce())
            ->method('authorizeUser')
            ->with($model, $password)
            ->willReturn($model);

        $di         = new \Box_Di();
        $di['db']   = $dbMock;
        $di['auth'] = $authMock;

        $service = new \Box\Mod\Staff\Service();
        $service->setDi($di);

        $result = $service->authorizeAdmin($email, $password);
        $this->assertInstanceOf('\Model_Admin', $result);
    }
}
 