<?php
namespace Box\Tests\Mod\Servicedomain\Api;

class Api_GuestTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Servicedomain\Api\Guest
     */
    protected $guestApi = null;

    public function setup(): void
    {
        $this->guestApi = new \Box\Mod\Servicedomain\Api\Guest();
    }

    public function testTlds()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('tldToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldToApiArray')
            ->will($this->returnValue(array()));

        $this->guestApi->setService($serviceMock);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array(new \Model_Tld())));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $this->guestApi->setDi($di);

        $result = $this->guestApi->tlds(array());
        $this->assertIsArray($result);
        $this->assertIsArray($result[0]);
    }

    public function testPricing()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('tldFindOneByTld', 'tldToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->will($this->returnValue(new \Model_Tld()));
        $serviceMock->expects($this->atLeastOnce())->method('tldToApiArray')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);
        $this->guestApi->setService($serviceMock);

        $data = array(
            'tld' => '.com'
        );

        $result = $this->guestApi->pricing($data);
        $this->assertIsArray($result);
    }

    public function testPricingTldNotFoundException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('tldFindOneByTld', 'tldToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->will($this->returnValue(null));
        $serviceMock->expects($this->never())->method('tldToApiArray')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);
        $this->guestApi->setService($serviceMock);

        $data = array(
            'tld' => '.com'
        );

        $this->expectException(\Box_Exception::class);
        $result = $this->guestApi->pricing($data);
        $this->assertIsArray($result);
    }

    public function testCheck()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('tldFindOneByTld', 'isDomainAvailable'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->will($this->returnValue(new \Model_Tld()));
        $serviceMock->expects($this->atLeastOnce())->method('isDomainAvailable')
            ->will($this->returnValue(true));

        $this->guestApi->setService($serviceMock);

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->will($this->returnValue(true));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $data = array(
            'tld' => '.com',
            'sld' => 'example'
        );

        $result = $this->guestApi->check($data);
        $this->assertTrue($result);
    }

    public function testCheckSldNotValidException()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->will($this->returnValue(false));
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $data = array(
            'tld' => '.com',
            'sld' => 'example'
        );

        $this->expectException(\Box_Exception::class);
        $this->guestApi->check($data);
    }

    public function testCheckTldNotFoundException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('tldFindOneByTld', 'isDomainAvailable'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->will($this->returnValue(null));
        $serviceMock->expects($this->never())->method('isDomainAvailable')
            ->will($this->returnValue(true));

        $this->guestApi->setService($serviceMock);

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->will($this->returnValue(true));
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $data = array(
            'tld' => '.com',
            'sld' => 'example'
        );

        $this->expectException(\Box_Exception::class);
        $this->guestApi->check($data);
    }

    public function testCheckDomainNotAvailableException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('tldFindOneByTld', 'isDomainAvailable'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->will($this->returnValue(new \Model_Tld()));
        $serviceMock->expects($this->atLeastOnce())->method('isDomainAvailable')
            ->will($this->returnValue(false));

        $this->guestApi->setService($serviceMock);

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->will($this->returnValue(true));
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $data = array(
            'tld' => '.com',
            'sld' => 'example'
        );

        $this->expectException(\Box_Exception::class);
        $this->guestApi->check($data);
    }

    public function testCan_be_transferred()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('tldFindOneByTld', 'canBeTransfered'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->will($this->returnValue(new \Model_Tld()));
        $serviceMock->expects($this->atLeastOnce())->method('canBeTransfered')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = array(
            'tld' => '.com',
            'sld' => 'example'
        );

        $result = $this->guestApi->can_be_transferred($data);
        $this->assertTrue($result);
    }

    public function testCan_be_transferredTldNotFoundException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('tldFindOneByTld', 'canBeTransfered'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->will($this->returnValue(null));
        $serviceMock->expects($this->never())->method('canBeTransfered')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);
        $this->guestApi->setService($serviceMock);

        $data = array(
            'tld' => '.com',
            'sld' => 'example'
        );
        
        $this->expectException(\Box_Exception::class);
        $this->guestApi->can_be_transferred($data);
    }

    public function testCan_be_transferredCanNotBeTransferredException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('tldFindOneByTld', 'canBeTransfered'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->will($this->returnValue(new \Model_Tld()));
        $serviceMock->expects($this->atLeastOnce())->method('canBeTransfered')
            ->will($this->returnValue(false));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);
        $this->guestApi->setService($serviceMock);

        $data = array(
            'tld' => '.com',
            'sld' => 'example'
        );
        
        $this->expectException(\Box_Exception::class);
        $this->guestApi->can_be_transferred($data);
    }


}
 