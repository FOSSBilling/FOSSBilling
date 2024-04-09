<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Registrar_Adapter_ResellerclubTest extends PHPUnit\Framework\TestCase
{
    private function getAdapter()
    {
        $options = [
            'userid' => '12345',
            'api-key' => 'api-token',
        ];

        return new Registrar_Adapter_Resellerclub($options);
    }

    public function testConstructionMissingUserId(): void
    {
        $options = [];

        $this->expectException(Registrar_Exception::class);
        $this->expectExceptionMessage('ResellerClub" domain registrar is not fully configured. Please configure the ResellerClub Reseller ID');

        $adapter = new Registrar_Adapter_Resellerclub($options);
    }

    public function testConstructionMissingApiKey(): void
    {
        $options = [
            'userid' => '12345',
        ];

        $this->expectException(Registrar_Exception::class);
        $this->expectExceptionMessage('The "ResellerClub" domain registrar is not fully configured. Please configure the ResellerClub API Key');

        new Registrar_Adapter_Resellerclub($options);
    }

    public function testConstruction(): void
    {
        $options = [
            'userid' => '12345',
            'api-key' => 'api-key Token',
        ];
        $adapter = new Registrar_Adapter_Resellerclub($options);

        $this->assertEquals($options['userid'], $adapter->config['userid']);
        $this->assertEquals($options['api-key'], $adapter->config['api-key']);
        $this->assertNull($adapter->config['password']);
    }

    public function testgetConfig(): void
    {
        $adapter = $this->getAdapter();
        $result = $adapter->getConfig();

        $this->assertNotEmpty($result);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('label', $result);
        $this->assertArrayHasKey('form', $result);
    }

    public function testisDomainAvailableFoundInArray(): void
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->onlyMethods(['_makeRequest'])
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com');

        $requestResult = [];
        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->with('domains/available')
            ->willReturn($requestResult);

        $result = $adapterMock->isDomainAvailable($registrarDomain);
        $this->assertTrue($result);
    }

    public function testisDomainAvailableStatusAvailable(): void
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->onlyMethods(['_makeRequest'])
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com');

        $requestResult = [$registrarDomain->getName() => ['status' => 'available']];
        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->with('domains/available')
            ->willReturn($requestResult);

        $result = $adapterMock->isDomainAvailable($registrarDomain);
        $this->assertTrue($result);
    }

    public function testisDomainAvailableIsNotAvailable(): void
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->onlyMethods(['_makeRequest'])
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com');

        $requestResult = [$registrarDomain->getName() => []];
        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->with('domains/available')
            ->willReturn($requestResult);

        $result = $adapterMock->isDomainAvailable($registrarDomain);
        $this->assertFalse($result);
    }

    public function testisDomaincanBeTransferred(): void
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->onlyMethods(['_makeRequest'])
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

    public function testmodifyNs(): void
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->onlyMethods(['_makeRequest'])
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com');

        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->willReturnCallback(function (...$args) {
                $value = match ($args[0]) {
                    'domains/orderid' => 1,
                    'domains/modify-ns' => ['status' => 'Success']
                };

                return $value;
            });

        $result = $adapterMock->modifyNs($registrarDomain);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testmodifyContact(): void
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->onlyMethods(['_makeRequest'])
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com')->setContactRegistrar(new Registrar_Domain_Contact());

        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->willReturnCallback(function (...$args) {
                $value = match ($args[0]) {
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

    public function testtransferDomain(): void
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->onlyMethods(['_makeRequest'])
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com')->setContactRegistrar(new Registrar_Domain_Contact());

        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->willReturnCallback(function (...$args) {
                $value = match ($args[0]) {
                    'customers/details' => ['customerid' => 1],
                    'contacts/default' => ['Contact' => ['registrant' => 1]],
                    'domains/transfer' => ['status' => 'Success']
                };

                return $value;
            });

        $result = $adapterMock->transferDomain($registrarDomain);
        $this->assertIsArray($result);
    }

    public function testregisterDomain(): void
    {
        $adapterMock = $this->getMockBuilder('Registrar_Adapter_Resellerclub')->disableOriginalConstructor()
            ->onlyMethods(['_makeRequest'])
            ->getMock();

        $registrarDomain = new Registrar_Domain();
        $registrarDomain->setSld('example')->setTld('.com')->setContactRegistrar(new Registrar_Domain_Contact());

        $requestResult = ['status' => 'Success'];
        $adapterMock->expects($this->atLeastOnce())
            ->method('_makeRequest')
            ->willReturnCallback(function (...$args) {
                $value = match ($args[0]) {
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

    public function testincludeAuthorizationParamsApiKeyProvided(): void
    {
        $options = [
            'userid' => '12345',
            'api-key' => 'password',
        ];
        $adapter = new Registrar_Adapter_Resellerclub($options);

        $params = [];
        $result = $adapter->includeAuthorizationParams($params);
        $this->assertArrayHasKey('auth-userid', $result);
        $this->assertArrayHasKey('api-key', $result);
    }

    public function testincludeAuthorizationParamsBothProvidedApiKeyIsUsed(): void
    {
        $options = [
            'userid' => '12345',
            'password' => 'password',
            'api-key' => 'password',
        ];
        $adapter = new Registrar_Adapter_Resellerclub($options);

        $params = [];
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

    #[PHPUnit\Framework\Attributes\DataProvider('providerTestArray')]
    public function testisKeyValueNotEmpty($array, $key, $expected): void
    {
        $options = [
            'userid' => 'TEST',
            'api-key' => 'TEST',
        ];

        $regAdapter = new Registrar_Adapter_Resellerclub($options);
        $result = $regAdapter->isKeyValueNotEmpty($array, $key);

        $this->assertEquals($expected, $result);
    }
}
