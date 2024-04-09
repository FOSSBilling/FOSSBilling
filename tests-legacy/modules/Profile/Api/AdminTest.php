<?php

namespace Box\Tests\Mod\Profile\Api;

class AdminTest extends \BBTestCase
{
    public function testGet(): void
    {
        $service = new \Box\Mod\Profile\Service();

        $model = new \Model_Admin();
        $model->loadBean(new \DummyBean());
        $model->id = 1;
        $model->role = 'admin';
        $model->admin_group_id = 1;
        $model->email = 'admin@fossbilling.org';
        $model->name = 'Admin';
        $model->signature = 'Sincerely';
        $model->status = 'active';
        $model->created_at = '2014-01-01';
        $model->updated_at = '2014-01-01';

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setIdentity($model);
        $adminApi->setService($service);
        $result = $adminApi->get();
        $expected = [
            'id' => $model->id,
            'role' => $model->role,
            'admin_group_id' => $model->admin_group_id,
            'email' => $model->email,
            'name' => $model->name,
            'signature' => $model->signature,
            'status' => $model->status,
            'api_token' => null,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
        ];
        $this->assertEquals($expected, $result);
    }

    public function testLogout(): void
    {
        $sessionMock = $this->getMockBuilder('\\' . \FOSSBilling\Session::class)
            ->disableOriginalConstructor()
            ->getMock();

        $di = new \Pimple\Container();
        $di['session'] = $sessionMock;
        $di['logger'] = new \Box_Log();

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setDi($di);
        $result = $adminApi->logout();
        $this->assertTrue($result);
    }

    public function testUpdate(): void
    {
        $model = new \Model_Admin();

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Profile\Service::class)
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('updateAdmin')
            ->willReturn(true);

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setIdentity($model);
        $adminApi->setService($serviceMock);
        $result = $adminApi->update(['name' => 'Root']);
        $this->assertTrue($result);
    }

    public function testGenerateApiKey(): void
    {
        $model = new \Model_Admin();

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Profile\Service::class)
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('generateNewApiKey')
            ->willReturn(true);

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setIdentity($model);
        $adminApi->setService($serviceMock);
        $result = $adminApi->generate_api_key([]);
        $this->assertTrue($result);
    }

    public function testChangePasswordExceptions(): never
    {
        $di = new \Pimple\Container();
        $di['validator'] = new \FOSSBilling\Validate();

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $adminApi->change_password([]);
        $this->fail('password should be passed');

        $this->expectException(\Exception::class);
        $adminApi->change_password(['password' => 'new_pass']);
        $this->fail('password confirmation should be passed');
    }

    public function testChangePassword(): void
    {
        $di = new \Pimple\Container();
        $di['validator'] = new \FOSSBilling\Validate();
        $di['password'] = new \FOSSBilling\PasswordManager();

        $model = new \Model_Admin();
        $model->loadBean(new \DummyBean());
        $model->pass = $di['password']->hashIt('oldpw');

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Profile\Service::class)
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('changeAdminPassword')
            ->willReturn(true);

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setDi($di);
        $adminApi->setIdentity($model);
        $adminApi->setService($serviceMock);
        $result = $adminApi->change_password(['current_password' => 'oldpw', 'new_password' => '84asasd221AS', 'confirm_password' => '84asasd221AS']);
        $this->assertTrue($result);
    }
}
