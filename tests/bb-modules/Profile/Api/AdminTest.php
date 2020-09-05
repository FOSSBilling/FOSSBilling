<?php

namespace Box\Tests\Mod\Profile\Api;

class AdminTest extends \BBTestCase
{
    public function testGet()
    {
        $service = new \Box\Mod\Profile\Service();

        $model = new \Model_Admin();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = 1;
        $model->role = 'admin';
        $model->admin_group_id = 1;
        $model->email = 'admin@boxbilling.com';
        $model->name = 'Admin';
        $model->signature = 'Sincerely';
        $model->status = 'active';
        $model->created_at = '2014-01-01';
        $model->updated_at = '2014-01-01';

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setIdentity($model);
        $adminApi->setService($service);
        $result = $adminApi->get();
        $expected = array(
            'id'    => $model->id,
            'role'    =>  $model->role,
            'admin_group_id'    =>  $model->admin_group_id,
            'email'    =>  $model->email,
            'name'    =>  $model->name,
            'signature'    =>  $model->signature,
            'status'    =>  $model->status,
            'api_token' => null,
            'created_at'    =>  $model->created_at,
            'updated_at'    =>  $model->updated_at,
        );
        $this->assertEquals($expected, $result);
    }

    public function testLogout()
    {
        $sessionMock = $this->getMockBuilder('\Box_Session')
            ->disableOriginalConstructor()
            ->getMock();

        $di = new \Box_Di();
        $di['cookie'] = new \Box_Cookie();
        $di['session'] = $sessionMock;
        $di['logger'] = new \Box_Log();

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setDi($di);
        $result = $adminApi->logout();
        $this->assertTrue($result);
    }

    public function testUpdate()
    {
        $model = new \Model_Admin();

        $serviceMock = $this->getMockBuilder('\Box\Mod\Profile\Service')
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('updateAdmin')
            ->will($this->returnValue(true));

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setIdentity($model);
        $adminApi->setService($serviceMock);
        $result = $adminApi->update(array('name'=>'Root'));
        $this->assertTrue($result);
    }

    public function testGenerateApiKey()
    {
        $model = new \Model_Admin();

        $serviceMock = $this->getMockBuilder('\Box\Mod\Profile\Service')
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('generateNewApiKey')
            ->will($this->returnValue(true));

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setIdentity($model);
        $adminApi->setService($serviceMock);
        $result = $adminApi->generate_api_key(array());
        $this->assertTrue($result);
    }

    public function testChangePasswordExceptions()
    {
        $adminApi = new \Box\Mod\Profile\Api\Admin();
        
        $this->expectException(\Exception::class);
        $adminApi->change_password(array());
        $this->fail('password should be passed');
        

        $this->expectException(\Exception::class);
        $adminApi->change_password(array('password'=>'new_pass'));
        $this->fail('password confirmation should be passed');
        
    }

    public function testChangePassword()
    {
        $di = new \Box_Di();
        $di['validator'] = new \Box_Validate();

        $model = new \Model_Admin();

        $serviceMock = $this->getMockBuilder('\Box\Mod\Profile\Service')
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('changeAdminPassword')
            ->will($this->returnValue(true));

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setDi($di);
        $adminApi->setIdentity($model);
        $adminApi->setService($serviceMock);
        $result = $adminApi->change_password(array('password'=>'84asasd221as', 'password_confirm'=>'84asasd221as'));
        $this->assertTrue($result);
    }
}
 