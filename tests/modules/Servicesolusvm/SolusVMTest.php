<?php


namespace Box\Mod\Servicesolusvm;

class SolusVMTest extends \BBTestCase {

    public function testgetDi()
    {
        $di = new \Box_Di();
        $solusVm = new SolusVM();
        $solusVm ->setDi($di);
        $getDi = $solusVm->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testbuildUrl()
    {
        $config = array(
            'protocol' => 'http',
            'ipaddress' => '10.20.30.40',
            'port' => '5656',
            'usertype' => 'admin'
        );

        $solusVm = new SolusVM();
        $result = $solusVm->buildUrl($config);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        $expected = $config['protocol'] ."://". $config['ipaddress'] . ":" . $config['port'] . "/api/" . $config['usertype'] . "/command.php";
        $this->assertEquals($expected, $result);
    }

    public function testgetSecureUrl()
    {
        $config = array(
            'ipaddress' => '10.20.30.40',
            'usertype' => 'admin'
        );

        $updatedConfig = array(
            'ipaddress' => '10.20.30.40',
            'usertype' => 'admin',
            'protocol' => 'https',
            'port' => '5656',
        );

        $solusVmMock = $this->getMockBuilder('\Box\Mod\Servicesolusvm\SolusVM')
            ->setMethods(array('buildUrl'))
            ->getMock();

        $expected = $updatedConfig['protocol'] ."://". $updatedConfig['ipaddress'] . ":" . $updatedConfig['port'] . "/api/" . $updatedConfig['usertype'] . "/command.php";
        $solusVmMock->expects($this->atLeastOnce())
            ->method('buildUrl')
            ->with($updatedConfig)
            ->willReturn($expected);

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $solusVmMock->setDi($di);
        $result = $solusVmMock->getSecureUrl($config);
        $this->assertEquals($expected, $result);
    }

    public function testgetUrl()
    {
        $config = array(
            'ipaddress' => '10.20.30.40',
            'usertype' => 'admin'
        );

        $updatedConfig = array(
            'ipaddress' => '10.20.30.40',
            'usertype' => 'admin',
            'protocol' => 'http',
            'port' => '5353',
        );

        $solusVmMock = $this->getMockBuilder('\Box\Mod\Servicesolusvm\SolusVM')
            ->setMethods(array('buildUrl'))
            ->getMock();

        $expected = $updatedConfig['protocol'] ."://". $updatedConfig['ipaddress'] . ":" . $updatedConfig['port'] . "/api/" . $updatedConfig['usertype'] . "/command.php";
        $solusVmMock->expects($this->atLeastOnce())
            ->method('buildUrl')
            ->with($updatedConfig)
            ->willReturn($expected);

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $solusVmMock->setDi($di);
        $result = $solusVmMock->getUrl($config);
        $this->assertEquals($expected, $result);
    }


    public function testsetConfig()
    {
        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $config = array(
            'id' => 123,
            'key' => 'verysecrectKey',
            'ipaddress' => '123.123.123.123',
        );

        $updatedConfig = array(
            'id' => 123,
            'key' => 'verysecrectKey',
            'ipaddress' => '123.123.123.123',
            'usertype' => 'admin',
            'secure' => false,
            'port' => NULL,
        );

        $solusVmMock = $this->getMockBuilder('\Box\Mod\Servicesolusvm\SolusVM')
            ->setMethods(array('getUrl', 'getSecureUrl'))
            ->getMock();

        $url = "http://". $updatedConfig['ipaddress'] . ":5353/api/" . $updatedConfig['usertype'] . "/command.php";
        $solusVmMock->expects($this->atLeastOnce())
            ->method('getUrl')
            ->with($updatedConfig)
            ->willReturn($url);

        $solusVmMock->expects($this->never())
            ->method('getSecureUrl');

        $solusVmMock->setDi($di);
        $solusVmMock->setConfig($config);

        $this->assertEquals($url, $solusVmMock->getApiHost());
        $this->assertEquals($config['id'], $solusVmMock->getApiID());
        $this->assertEquals($config['key'], $solusVmMock->getApiKey());
    }
}
