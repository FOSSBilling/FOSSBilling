<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_Client_ProfileTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'mod_client.xml';

    public function testProfile(): void
    {
        $array = $this->api_client->profile_get();
        $this->assertIsArray($array);

        $data = [
            'email' => 'email@test.com',
            'first_name' => 'First name',
            'last_name' => 'Last Name',
            'address_1' => 'address 1',
            'address_2' => 'address 2',
            'country' => 'country',
            'city' => 'city',
            'state' => 'n/a',
            'postcode' => '123456',
            'phone_cc' => 1234,
            'phone' => 1_212_121_212_121,
        ];
        $bool = $this->api_client->profile_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_client->profile_update($data);
        $this->assertTrue($bool);
    }

    public function testPassword(): void
    {
        $data = [
            'password' => 'demo11AA1111',
            'password_confirm' => 'demo11AA1111',
        ];
        $bool = $this->api_client->profile_change_password($data);
        $this->assertTrue($bool);
    }

    public function testApi(): void
    {
        $string = $this->api_client->profile_api_key_reset();
        $this->assertIsString($string);

        $string = $this->api_client->profile_api_key_get();
        $this->assertIsString($string);
    }

    public function testBalance(): void
    {
        $array = $this->api_client->client_balance_get_list();
        $this->assertIsArray($array);
    }

    public function testLogout(): void
    {
        $bool = $this->api_client->profile_logout();
        $this->assertTrue($bool);
    }

    public function testEmailChange(): void
    {
        // enable email change
        $config = [
            'ext' => 'mod_client',
            'disable_change_email' => false,
        ];
        $bool = $this->api_admin->extension_config_save($config);
        $this->assertTrue($bool);

        $bool = $this->api_client->profile_update(['email' => 'test@email.com']);
        $this->assertTrue($bool);

        // disable email change
        $config = [
            'ext' => 'mod_client',
            'disable_change_email' => true,
        ];
        $bool = $this->api_admin->extension_config_save($config);
        $this->assertTrue($bool);

        try {
            $this->api_client->profile_update(['email' => 'new@email.com']);
            $this->fail('Email should not changed due to setting');
        } catch (Exception) {
            // ok
        }
    }
}
