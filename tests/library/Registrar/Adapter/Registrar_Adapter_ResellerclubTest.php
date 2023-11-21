<?php
#[\PHPUnit\Framework\Attributes\Group('Core')]
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
        $this->expectExceptionMessage('ResellerClub" domain registrar is not fully configured. Please configure the ResellerClub Reseller ID');

        $adapter = new \Registrar_Adapter_Resellerclub($options);
    }

    public function testConstruction_MissingApiKey()
    {
        $options = array(
            'userid' => '12345',
        );

        $this->expectException(Registrar_Exception::class);
        $this->expectExceptionMessage('The "ResellerClub" domain registrar is not fully configured. Please configure the ResellerClub API Key');

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
            ->onlyMethods(array('_makeRequest'))
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
            ->onlyMethods(array('_makeRequest'))
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
            ->onlyMethods(array('_makeRequest'))
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

    public function testisDomaincanBeTransferred()
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->onlyMethods(array('_makeRequest'))
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com');

        $requestResult = 'true';
        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->with('domains/validate-transfer')
            ->willReturn($requestResult);

        $result = $adapterMock->isDomaincanBeTransferred($registrarDomain);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testmodifyNs()
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->onlyMethods(array('_makeRequest'))
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com');

        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->willReturnCallback(function (...$args) {
                $value = match($args[0]) {
                    'domains/orderid' => 1,
                    'domains/modify-ns'=> ['status' => 'Success']
                };

                return $value;
            });

        $result = $adapterMock->modifyNs($registrarDomain);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testmodifyContact()
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->onlyMethods(array('_makeRequest'))
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com')->setContactRegistrar(new Registrar_Domain_Contact());

        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->willReturnCallback(function (...$args) {
                $value = match($args[0]) {
                    'customers/details' => ['customerid' => 1],
                    'contacts/default' => ['Contact' => ['registrant' => 1]],
                    'contacts/modify' => ['status' => 'Success']
                };

                return $value;
            });

        $result = $adapterMock->modifyContact($registrarDomain);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testtransferDomain()
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->onlyMethods(array('_makeRequest'))
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com')->setContactRegistrar(new Registrar_Domain_Contact());

        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->willReturnCallback(function (...$args) {
                $value = match($args[0]) {
                    'customers/details' => ['customerid' => 1],
                    'contacts/default' => ['Contact' => ['registrant' => 1]],
                    'domains/transfer' => ['status' => 'Success']
                };

                return $value;
            });

        $result = $adapterMock->transferDomain($registrarDomain);
        $this->assertIsArray($result);
    }

    public function testregisterDomain()
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->onlyMethods(array('_makeRequest'))
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com')->setContactRegistrar(new Registrar_Domain_Contact());

        $requestResult = array('status' => 'Success');
        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->willReturnCallback(function (...$args) {
                $value = match($args[0]) {
                    'domains/orderid' => 1,
                    'domains/details' => ['currentstatus' => ''],
                    'customers/details' => ['customerid' => 1],
                    'contacts/search' => ['recsonpage' => 1, 'result' => [['entity.entityid' => 2]]],
                    'contacts/delete' => [],
                    'contacts/add' => 2,
                    'domains/register' => ['status' => 'Success']
                };

                return $value;
            });

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

    public static function providerTestArray()
    {
        return [
            [
                [], 'NotExistingKey', false,
            ],
            [
                ['api-key' => ''], 'api-key', false,
            ],
            [
                ['api-key' => '   '], 'api-key', false,
            ],
            [
                ['api-key' => '123'], 'api-key', true,
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerTestArray')]
    public function testisKeyValueNotEmpty($array, $key, $expected)
    {
        $options = [
            'userid' => 'TEST',
            'api-key' => 'TEST'
        ];

        $regAdapter = new \Registrar_Adapter_Resellerclub($options);
        $result = $regAdapter->isKeyValueNotEmpty($array, $key);

        $this->assertEquals($expected, $result);
    }
}
