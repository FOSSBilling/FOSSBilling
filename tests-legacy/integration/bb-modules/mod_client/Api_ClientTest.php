<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_ClientTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'initial.xml';

    public function testClient(): void
    {
        $array = $this->api_client->client_get();
        $this->assertIsArray($array);

        $data = [
            'email' => 'newEmail@fossbilling.org',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'birthday' => '1987.10.11',
            'company' => 'FOSSBilling',
            'address_1' => 'Wall Street 45',
            'phone_cc' => 'FOSSBilling',
            'country' => 'USA',
            'currency' => 'USD',
            'postcode' => 123456,
            'api_token' => 'phpunit_token',
        ];
        $bool = $this->api_client->client_update($data);
        $this->assertTrue($bool);

        $key = $this->api_client->client_api_key_get([]);
        $this->assertIsString($key);

        $newKey = $this->api_client->client_api_key_reset([]);
        $this->assertIsString($newKey);
        $this->assertNotEquals($key, $newKey);

        $data = [
            'password' => 'newPa55word',
            'password_confirm' => 'newPa55word',
        ];
        $bool = $this->api_client->client_change_password($data);
        $this->assertTrue($bool);

        $array = $this->api_client->client_balance_get_list($data);
        $this->assertIsArray($array);
    }

    public function testClientBalanceGetList(): void
    {
        $array = $this->api_client->client_balance_get_list([]);
        $this->assertIsArray($array);
        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);
        if (count($list)) {
            $item = $list[0];
            $this->assertIsArray($item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('description', $item);
            $this->assertArrayHasKey('amount', $item);
            $this->assertArrayHasKey('currency', $item);
            $this->assertArrayHasKey('created_at', $item);
        }
    }
}
