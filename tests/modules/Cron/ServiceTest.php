<?php


namespace Box\Mod\Cron;


class ServiceTest extends \BBTestCase {

    public function testgetDi()
    {
        $di = new \Pimple\Container();
        $service = new \Box\Mod\Cron\Service();
        $service->setDi($di);
        $getDi = $service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetCronInfo()
    {
        $systemServiceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemServiceMock->expects($this->atLeastOnce())->method('getParamValue');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($name) use($systemServiceMock) {return $systemServiceMock;});
        $service = new \Box\Mod\Cron\Service();
        $service->setDi($di);

        $result = $service->getCronInfo();
        $this->assertIsArray($result);
    }

    public function testrunCrons()
    {
        $apiSystem = new \Api_Handler(new \Model_Admin());
        $serviceMock = $this->getMockBuilder('\Box\Mod\Cron\Service')
            ->onlyMethods(array('_exec'))
            ->getMock();

        $serviceMock->expects($this->exactly(13))
            ->method('_exec')
            ->willReturnCallback(function (...$args) use ($apiSystem) {
                $series = [
                    [[$apiSystem], 'hook_batch_connect'],
                    [[$apiSystem], 'invoice_batch_pay_with_credits'],
                    [[$apiSystem], 'invoice_batch_activate_paid'],
                    [[$apiSystem], 'invoice_batch_send_reminders'],
                    [[$apiSystem], 'invoice_batch_generate'],
                    [[$apiSystem], 'invoice_batch_invoke_due_event'],
                    [[$apiSystem], 'order_batch_suspend_expired'],
                    [[$apiSystem], 'order_batch_cancel_suspended'],
                    [[$apiSystem], 'support_batch_ticket_auto_close'],
                    [[$apiSystem], 'support_batch_public_ticket_auto_close'],
                    [[$apiSystem], 'client_batch_expire_password_reminders'],
                    [[$apiSystem], 'cart_batch_expire'],
                    [[$apiSystem], 'email_batch_sendmail']
                ];

                [$expectedApiSystem, $expectedMethod] = array_shift($series);
                $this->equalTo($expectedApiSystem, $args[0]);
                $this->equalTo($expectedMethod, $args[1]);
            });

        $systemServiceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('setParamValue');

        $eventsMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder('Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('exec');

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['events_manager'] = $eventsMock;
        $di['api_system'] = $apiSystem;
        $di['mod_service'] = $di->protect(function() use($systemServiceMock) {return $systemServiceMock;});
        $serviceMock->setDi($di);
        $di['db'] = $dbMock;
        $di['cache'] = new \Symfony\Component\Cache\Adapter\FilesystemAdapter('sf_cache', 24 * 60 * 60, PATH_CACHE);
        $di['config'] = [
            'security' => [
                'mode' => 'strict',
                'force_https' => true,
                'cookie_lifespan' => 7200,
            ],
        ];

        $result = $serviceMock->runCrons();
        $this->assertTrue($result);
    }

    public function testgetLastExecutionTime()
    {
        $systemServiceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->will($this->returnValue('2012-12-12 12:12:12'));

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($name) use($systemServiceMock) {return $systemServiceMock;});
        $service = new \Box\Mod\Cron\Service();
        $service->setDi($di);

        $result = $service->getLastExecutionTime();
        $this->assertIsString($result);
    }

    public function testisLate()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Cron\Service')
            ->onlyMethods(array('getLastExecutionTime'))
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getLastExecutionTime')
            ->will($this->returnValue(date('Y-m-d H:i:s')));

        $result = $serviceMock->isLate();
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }
}
