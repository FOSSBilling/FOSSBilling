<?php


namespace Box\Mod\Cron;


class ServiceTest extends \BBTestCase {

    public function testgetDi()
    {
        $di = new \Box_Di();
        $service = new \Box\Mod\Cron\Service();
        $service->setDi($di);
        $getDi = $service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetCronInfo()
    {
        $systemServiceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemServiceMock->expects($this->atLeastOnce())->method('getParamValue');

        $di = new \Box_Di();
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
            ->setMethods(array('_exec'))
            ->getMock();

        $serviceMock->expects($this->exactly(13))
            ->method('_exec')
            ->withConsecutive(
                array($this->equalTo($apiSystem), $this->equalTo('hook_batch_connect')),
                array($this->equalTo($apiSystem), $this->equalTo('invoice_batch_pay_with_credits')),
                array($this->equalTo($apiSystem), $this->equalTo('invoice_batch_activate_paid')),
                array($this->equalTo($apiSystem), $this->equalTo('invoice_batch_send_reminders')),
                array($this->equalTo($apiSystem), $this->equalTo('invoice_batch_generate')),
                array($this->equalTo($apiSystem), $this->equalTo('invoice_batch_invoke_due_event')),
                array($this->equalTo($apiSystem), $this->equalTo('order_batch_suspend_expired')),
                array($this->equalTo($apiSystem), $this->equalTo('order_batch_cancel_suspended')),
                array($this->equalTo($apiSystem), $this->equalTo('support_batch_ticket_auto_close')),
                array($this->equalTo($apiSystem), $this->equalTo('support_batch_public_ticket_auto_close')),
                array($this->equalTo($apiSystem), $this->equalTo('client_batch_expire_password_reminders')),
                array($this->equalTo($apiSystem), $this->equalTo('cart_batch_expire')),
                array($this->equalTo($apiSystem), $this->equalTo('email_batch_sendmail'))
            );

        $systemServiceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('setParamValue');

        $eventsMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Box_Di();
        $di['logger'] = new \Box_Log();
        $di['events_manager'] = $eventsMock;
        $di['api_system'] = $apiSystem;
        $di['mod_service'] = $di->protect(function() use($systemServiceMock) {return $systemServiceMock;});
        $serviceMock->setDi($di);

        $result = $serviceMock->runCrons();
        $this->assertTrue($result);
    }

    public function testgetLastExecutionTime()
    {
        $systemServiceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->will($this->returnValue('2012-12-12 12:12:12'));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use($systemServiceMock) {return $systemServiceMock;});
        $service = new \Box\Mod\Cron\Service();
        $service->setDi($di);

        $result = $service->getLastExecutionTime();
        $this->assertIsString($result);
    }

    public function testisLate()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Cron\Service')
            ->setMethods(array('getLastExecutionTime'))
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getLastExecutionTime')
            ->will($this->returnValue(date('Y-m-d H:i:s')));

        $result = $serviceMock->isLate();
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }
}
 