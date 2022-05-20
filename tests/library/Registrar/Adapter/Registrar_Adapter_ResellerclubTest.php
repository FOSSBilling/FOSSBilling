<?php
/**
 * @group Core
 */
class Registrar_Adapter_ResellerclubTest extends PHPUnit\Framework\TestCase
{
    private function getAdapter()
    {
        $options = array(
            'userid' => '12345',
            'api-key' => 'api-token'
        );
        return new \Registrar_Adapter_Resellerclub($options);
    }

    public function testConstruction_MissingUserId()
    {
        $options = array();

        $this->expectException(Registrar_Exception::class);
        $this->expectExceptionMessage('Domain registrar "ResellerClub" is not configured properly. Please update configuration parameter "ResellerClub Reseller ID" at "Configuration -> Domain registration".');

        $adapter = new \Registrar_Adapter_Resellerclub($options);
    }

    public function testConstruction_MissingApiKey()
    {
        $options = array(
            'userid' => '12345',
        );

        $this->expectException(Registrar_Exception::class);
        $this->expectExceptionMessage('Domain registrar "ResellerClub" is not configured properly. Please update configuration parameter "ResellerClub API Key" at "Configuration -> Domain registration".');

        new \Registrar_Adapter_Resellerclub($options);
    }

    public function testConstruction()
    {
        $options = array(
            'userid' => '12345',
            'api-key' => 'api-key Token'
        );
        $adapter = new \Registrar_Adapter_Resellerclub($options);

        $this->assertEquals($options['userid'], $adapter->config['userid']);
        $this->assertEquals($options['api-key'], $adapter->config['api-key']);
        $this->assertNull($adapter->config['password']);
    }

    public function testgetConfig()
    {
        $adapter = $this->getAdapter();
        $result = $adapter->getConfig();

        $this->assertNotEmpty($result);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('label', $result);
        $this->assertArrayHasKey('form', $result);
    }

    public function testgetTlds()
    {
        $adapter = $this->getAdapter();
        $result = $adapter->getTlds();

        $this->assertNotEmpty($result);
        $this->assertIsArray($result);
    }

    public function testisDomainAvailable_foundInArray()
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->setMethods(array('_makeRequest'))
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com');

        $requestResult = array();
        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->with('domains/available')
            ->willReturn($requestResult);

        $result = $adapterMock->isDomainAvailable($registrarDomain);
        $this->assertTrue($result);
    }

    public function testisDomainAvailable_StatusAvailable()
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->setMethods(array('_makeRequest'))
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com');

        $requestResult = array($registrarDomain->getName() => array( 'status' => 'available'));
        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->with('domains/available')
            ->willReturn($requestResult);

        $result = $adapterMock->isDomainAvailable($registrarDomain);
        $this->assertTrue($result);
    }

    public function testisDomainAvailable_isNotAvailable()
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->setMethods(array('_makeRequest'))
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com');

        $requestResult = array($registrarDomain->getName() => array());
        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->with('domains/available')
            ->willReturn($requestResult);

        $result = $adapterMock->isDomainAvailable($registrarDomain);
        $this->assertFalse($result);
    }

    public function testisDomainCanBeTransfered()
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->setMethods(array('_makeRequest'))
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com');

        $requestResult = 'true';
        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->with('domains/validate-transfer')
            ->willReturn($requestResult);

        $result = $adapterMock->isDomainCanBeTransfered($registrarDomain);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testmodifyNs()
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->setMethods(array('_makeRequest'))
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com');

        $requestResult = array('status' => 'Success');
        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->withConsecutive(array('domains/orderid'), array('domains/modify-ns'))
            ->willReturnOnConsecutiveCalls(1, $requestResult);

        $result = $adapterMock->modifyNs($registrarDomain);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testmodifyContact()
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->setMethods(array('_makeRequest'))
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com')->setContactRegistrar(new Registrar_Domain_Contact());

        $requestResult = array('status' => 'Success');
        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->withConsecutive(array('customers/details'), array('contacts/default'), array('contacts/modify'))
            ->willReturnOnConsecutiveCalls(array('customerid' => 1), array('Contact' => array('registrant' => 1)), $requestResult);

        $result = $adapterMock->modifyContact($registrarDomain);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testtransferDomain()
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->setMethods(array('_makeRequest'))
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com')->setContactRegistrar(new Registrar_Domain_Contact());

        $requestResult = array('status' => 'Success');
        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->withConsecutive(array('customers/details'), array('contacts/default'), array('domains/transfer'))
            ->willReturnOnConsecutiveCalls(array('customerid' => 1), array('Contact' => array('registrant' => 1)), $requestResult);

        $result = $adapterMock->transferDomain($registrarDomain);
        $this->assertIsArray($result);
    }

    public function testregisterDomain()
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->setMethods(array('_makeRequest'))
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com')->setContactRegistrar(new Registrar_Domain_Contact());

        $requestResult = array('status' => 'Success');
        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->withConsecutive(
                array('domains/orderid'),
                array('domains/details'),
                array('customers/details'),
                array('contacts/search'),
                array('contacts/delete'),
                array('contacts/add'),
                array('domains/register')

            )
            ->willReturnOnConsecutiveCalls(
                1,
                array('currentstatus' => ''),
                array('customerid' => 1),
                array('recsonpage' => 1, 'result' => array(array('entity.entityid' => 2))),
                array(),
                2,
                $requestResult
            );

        $result = $adapterMock->registerDomain($registrarDomain);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testincludeAuthorizationParams_ApiKeyProvided()
    {
        $options = array(
            'userid' => '12345',
            'api-key' => 'password'
        );
        $adapter = new \Registrar_Adapter_Resellerclub($options);

        $params = array();
        $result = $adapter->includeAuthorizationParams($params);
        $this->assertArrayHasKey('auth-userid', $result);
        $this->assertArrayHasKey('api-key', $result);
    }

    public function testincludeAuthorizationParams_BothProvided_ApiKeyIsUsed()
    {
        $options = array(
            'userid' => '12345',
            'password' => 'password',
            'api-key' => 'password'
        );
        $adapter = new \Registrar_Adapter_Resellerclub($options);

        $params = array();
        $result = $adapter->includeAuthorizationParams($params);
        $this->assertArrayHasKey('auth-userid', $result);
        $this->assertArrayHasKey('api-key', $result);
        $this->assertArrayNotHasKey('auth-password', $result);
    }


    public function providerTestArray()
    {
        return array(
            array(
                array(), 'NotExistingKey', false,
            ),
            array(
                array('api-key' => ''), 'api-key', false,
            ),
            array(
                array('api-key' => '   '), 'api-key', false,
            ),
            array(
                array('api-key' => '123'), 'api-key', true,
            ),
        );
    }

    /**
     * @dataProvider providerTestArray
     */
    public function testisKeyValueNotEmpty($array, $key, $expected)
    {
        $adapterMock = $this->getMockBuilder('\Registrar_Adapter_Resellerclub')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $result = $adapterMock->isKeyValueNotEmpty($array, $key);
        $this->assertEquals($expected, $result);
    }

}