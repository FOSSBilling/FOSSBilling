<?php


namespace Box\Mod\Spamchecker;


class ServiceTest extends \BBTestCase {

    /**
     * @var \Box\Mod\Spamchecker\Service
     */
    protected $service = null;

    public function setup(): void
    {
        $this->service= new \Box\Mod\Spamchecker\Service();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testforumSpamChecker()
    {
        $params = array(
            'client_id' => 1,
            'message' => 'Hello world',
        );

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($model);

        $spamCheckerService = $this->getMockBuilder('\Box\Mod\Spamchecker\Service')->getMock();
        $spamCheckerService->expects($this->atLeastOnce())
            ->method('isCommentSpam');
        $spamCheckerService->expects($this->atLeastOnce())
            ->method('isBlockedIp');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($spamCheckerService){
            return $spamCheckerService;
        });

        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($params);

        $this->service->forumSpamChecker($boxEventMock);
    }

    public function testonBeforeClientCreateForumTopic()
    {
        $spamCheckerService = $this->getMockBuilder('\Box\Mod\Spamchecker\Service')->getMock();
        $spamCheckerService->expects($this->atLeastOnce())
            ->method('forumSpamChecker');

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($spamCheckerService){
            return $spamCheckerService;
        });
        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->onBeforeClientCreateForumTopic($boxEventMock);

        //TODO maybe we can write better test here?
        $this->assertTrue(true);
    }

    public function testonBeforeClientRepliedInForum()
    {
        $spamCheckerService = $this->getMockBuilder('\Box\Mod\Spamchecker\Service')->getMock();
        $spamCheckerService->expects($this->atLeastOnce())
            ->method('forumSpamChecker');

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($spamCheckerService){
            return $spamCheckerService;
        });
        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->onBeforeClientRepliedInForum($boxEventMock);
    }

    public function testonBeforeClientSignUp()
    {
        $spamCheckerService = $this->getMockBuilder('\Box\Mod\Spamchecker\Service')->getMock();
        $spamCheckerService->expects($this->atLeastOnce())
            ->method('isBlockedIp');
        $spamCheckerService->expects($this->atLeastOnce())
            ->method('isSpam');

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($spamCheckerService){
            return $spamCheckerService;
        });
        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->onBeforeClientSignUp($boxEventMock);
    }

    public function testonBeforeGuestPublicTicketOpen()
    {
        $spamCheckerService = $this->getMockBuilder('\Box\Mod\Spamchecker\Service')->getMock();
        $spamCheckerService->expects($this->atLeastOnce())
            ->method('isBlockedIp');
        $spamCheckerService->expects($this->atLeastOnce())
            ->method('isSpam');

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($spamCheckerService){
            return $spamCheckerService;
        });
        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->onBeforeGuestPublicTicketOpen($boxEventMock);
    }

    public function testisBlockedIp_IpBlocked()
    {
        $clientIp = '1.1.1.1';
        $modConfig = array(
            'block_ips' => true,
            'blocked_ips' => '1.1.1.1'.PHP_EOL.'2.2.2.2',
        );


        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('getIpv4')
            ->willReturn($clientIp);

        $di = new \Box_Di();
        $di['mod_config'] = $di->protect(function($modName) use ($modConfig ){
            if ($modName == 'Spamchecker'){
                return $modConfig;
            }
        });
        $di['tools'] = $toolsMock;

        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage(sprintf("IP %s is blocked", $clientIp), 403);
        $this->service->isBlockedIp($boxEventMock);
    }

    public function testisBlockedIp_IpNotBlocked()
    {
        $clientIp = '214.1.4.99';
        $modConfig = array(
            'block_ips' => true,
            'blocked_ips' => '1.1.1.1'.PHP_EOL.'2.2.2.2',
        );


        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('getIpv4')
            ->willReturn($clientIp);

        $di = new \Box_Di();
        $di['mod_config'] = $di->protect(function($modName) use ($modConfig ){
            if ($modName == 'Spamchecker'){
                return $modConfig;
            }
        });
        $di['tools'] = $toolsMock;

        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->isBlockedIp($boxEventMock);
    }

    public function testisBlockedIp_BlockIpsNotEnabled()
    {
        $modConfig = array(
            'block_ips' => false,
        );


        $di = new \Box_Di();
        $di['mod_config'] = $di->protect(function($modName) use ($modConfig ){
            if ($modName == 'Spamchecker'){
                return $modConfig;
            }
        });

        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->isBlockedIp($boxEventMock);
    }

    public function testisInStopForumSpamDatabase_InvalidResponse()
    {
        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('file_get_contents')
            ->willReturn('{}');

        $di = new \Box_Di();
        $di['tools'] = $toolsMock;

        $data = array();
        $this->service->setDi($di);
        $result = $this->service->isInStopForumSpamDatabase($data);
        $this->assertFalse($result);
    }

    public function dataProviderSpamResponses()
    {
        return array(
            array(
                '{"success" : "true", "username" : {"appears" : "true" }}', 'Your Username is blacklisted in global database'
            ),
            array(
                '{"success" : "true", "email" : {"appears" : "true" }}', 'Your Email is blacklisted in global database'
            ),
            array(
                '{"success" : "true", "ip" : {"appears" : "true" }}', 'Your IP is blacklisted in global database'
            ),
        );
    }

    /**
     * @dataProvider dataProviderSpamResponses
     */
    public function testisInStopForumSpamDatabase_UserNameBlackListed($json, $exceptionMessage)
    {
        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('file_get_contents')
            ->willReturn($json);

        $di = new \Box_Di();
        $di['tools'] = $toolsMock;

        $data = array();
        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->service->isInStopForumSpamDatabase($data);
    }

    public function testisInStopForumSpamDatabase_NotExists()
    {
        $json = '{"success" : "true", "username" : {}}';
        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('file_get_contents')
            ->willReturn($json);

        $di = new \Box_Di();
        $di['tools'] = $toolsMock;

        $data = array();
        $this->service->setDi($di);
        $result = $this->service->isInStopForumSpamDatabase($data);
        $this->assertFalse($result);
    }


}