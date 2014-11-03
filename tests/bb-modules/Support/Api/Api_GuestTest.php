<?php

namespace Box\Tests\Mod\Support\Api;


class GuestTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Box\Mod\Support\Api\Guest
     */
    protected $guestApi = null;

    public function setup()
    {
        $this->guestApi = new \Box\Mod\Support\Api\Guest();
    }

    public function testTicket_create()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('ticketCreateForGuest'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketCreateForGuest')
            ->will($this->returnValue(sha1(uniqid())));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));


        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->guestApi ->setDi($di);

        $this->guestApi ->setService($serviceMock);

        $data   = array(
            'name'    => 'Name',
            'email'   => 'email@wxample.com',
            'subject' => 'Subject',
            'message' => 'Message',
        );
        $result = $this->guestApi->ticket_create($data);

        $this->assertInternalType('string', $result);
        $this->assertEquals(strlen($result), 40);
    }

    /**
     * @expectedException \Box_Exception
     */
    public function testTicket_createMessageTooShortException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('ticketCreateForGuest'))->getMock();
        $serviceMock->expects($this->never())->method('ticketCreateForGuest')
            ->will($this->returnValue(sha1(uniqid())));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));


        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->guestApi ->setDi($di);

        $this->guestApi ->setService($serviceMock);

        $data   = array(
            'name'    => 'Name',
            'email'   => 'email@wxample.com',
            'subject' => 'Subject',
            'message' => '',
        );
        $result = $this->guestApi->ticket_create($data);

        $this->assertInternalType('string', $result);
        $this->assertEquals(strlen($result), 40);
    }

    public function testTicket_get()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('publicFindOneByHash', 'publicToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicFindOneByHash')
            ->will($this->returnValue(new \Model_SupportPTicket()));
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));


        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->guestApi ->setDi($di);

        $this->guestApi ->setService($serviceMock);

        $data   = array(
            'hash' => sha1(uniqid()),
        );
        $result = $this->guestApi->ticket_get($data);

        $this->assertInternalType('array', $result);
    }

    public function testTicket_close()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('publicFindOneByHash', 'publicCloseTicket'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicFindOneByHash')
            ->will($this->returnValue(new \Model_SupportPTicket()));
        $serviceMock->expects($this->atLeastOnce())->method('publicCloseTicket')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));


        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->guestApi ->setDi($di);

        $this->guestApi ->setService($serviceMock);

        $data   = array(
            'hash' => sha1(uniqid()),
        );
        $result = $this->guestApi->ticket_close($data);

        $this->assertInternalType('array', $result);
    }

    public function testTicket_reply()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('publicFindOneByHash', 'publicTicketReplyForGuest'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicFindOneByHash')
            ->will($this->returnValue(new \Model_SupportPTicket()));
        $serviceMock->expects($this->atLeastOnce())->method('publicTicketReplyForGuest')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));


        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->guestApi ->setDi($di);

        $this->guestApi ->setService($serviceMock);

        $data   = array(
            'hash'    => sha1(uniqid()),
            'message' => 'Message'
        );
        $result = $this->guestApi->ticket_reply($data);

        $this->assertInternalType('array', $result);
    }
}
 