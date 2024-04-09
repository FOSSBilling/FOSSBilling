<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_Admin_ClientTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'initial.xml';

    public function testClient(): void
    {
        $data = [
            'id' => 1,
        ];
        $array = $this->api_admin->client_get($data);
        $this->assertIsArray($array);

        $array = $this->api_admin->client_login($data);
        $this->assertIsArray($array);

        $data['email'] = 'new@gmail.com';
        $data['first_name'] = 'phpunit';
        $data['last_name'] = 'same';
        $data['status'] = 'active';
        $bool = $this->api_admin->client_update($data);
        $this->assertTrue($bool);

        $data['password'] = 'new';
        $data['password_confirm'] = 'new';
        $bool = $this->api_admin->client_change_password($data);
        $this->assertTrue($bool);

        $array = $this->api_admin->client_get_statuses($data);
        $this->assertIsArray($array);

        $bool = $this->api_admin->client_balance_add_funds(['id' => 1, 'amount' => 100, 'description' => 'Added from PHPUnit']);
        $this->assertTrue($bool);

        $array = $this->api_admin->client_balance_get_list($data);
        $this->assertIsArray($array);
        $this->assertEquals(count($array['list']), 1);

        $bool = $this->api_admin->client_balance_delete(['id' => 1]);
        $this->assertTrue($bool);

        $array = $this->api_admin->client_balance_get_list($data);
        $this->assertIsArray($array);
        $this->assertEquals(count($array['list']), 0);

        $data = [
            'id' => 1,
        ];
        $bool = $this->api_admin->client_login_history_delete($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->client_batch_expire_password_reminders();
        $this->assertTrue($bool);
    }

    public function testGetPairs(): void
    {
        $data = [
            'search' => 'de',
        ];

        $array = $this->api_admin->client_get_pairs($data);
        $this->assertIsArray($array);
    }

    public function testGroups(): void
    {
        $data = [
            'title' => 'testers',
        ];

        $id = $this->api_admin->client_group_create($data);
        $this->assertTrue(is_numeric($id));

        $data['id'] = $id;
        $bool = $this->api_admin->client_group_update($data);
        $this->assertTrue($bool);

        $array = $this->api_admin->client_group_get($data);
        $this->assertIsArray($array);

        $array = $this->api_admin->client_group_get_pairs($data);
        $this->assertIsArray($array);

        $bool = $this->api_admin->client_group_delete($data);
        $this->assertTrue($bool);
    }

    public function testGet(): void
    {
        $data = [
            'id' => 1,
        ];
        $array = $this->api_admin->client_get($data);
        $this->assertIsArray($array);
        $this->assertNull($array['auth_type']);
    }

    public function testCreate(): void
    {
        $data = [
            'email' => 'tester@gmail.com',
            'first_name' => 'Client',
            'password' => 'password',
        ];

        $id = $this->api_admin->client_create($data);
        $this->assertTrue($id > 1);

        $bool = $this->api_admin->client_delete(['id' => $id]);
        $this->assertTrue($bool);
    }

    public function testLoginHistoryGetList(): void
    {
        $array = $this->api_admin->client_login_history_get_list([]);
        $this->assertIsArray($array);
        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);

        if (count($list)) {
            $item = $list[0];
            $this->assertIsArray($item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('ip', $item);
            $this->assertArrayHasKey('created_at', $item);

            $this->assertArrayHasKey('client', $item);
            $staff = $item['client'];
            $this->assertIsArray($staff);
            $this->assertArrayHasKey('id', $staff);
            $this->assertArrayHasKey('first_name', $staff);
            $this->assertArrayHasKey('last_name', $staff);
            $this->assertArrayHasKey('email', $staff);
        }
    }

    public function testClientGetList(): void
    {
        $array = $this->api_admin->client_get_list([]);
        $this->assertIsArray($array);
        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);

        if (count($list)) {
            $item = $list[0];
            $this->assertIsArray($item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('aid', $item);
            $this->assertArrayHasKey('email', $item);
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('group_id', $item);
            $this->assertArrayHasKey('company', $item);
            $this->assertArrayHasKey('company_vat', $item);
            $this->assertArrayHasKey('company_number', $item);
            $this->assertArrayHasKey('first_name', $item);
            $this->assertArrayHasKey('last_name', $item);
            $this->assertArrayHasKey('gender', $item);
            $this->assertArrayHasKey('birthday', $item);
            $this->assertArrayHasKey('phone_cc', $item);
            $this->assertArrayHasKey('phone', $item);
            $this->assertArrayHasKey('address_1', $item);
            $this->assertArrayHasKey('address_2', $item);
            $this->assertArrayHasKey('city', $item);
            $this->assertArrayHasKey('state', $item);
            $this->assertArrayHasKey('postcode', $item);
            $this->assertArrayHasKey('country', $item);
            $this->assertArrayHasKey('currency', $item);
            $this->assertArrayHasKey('notes', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('balance', $item);
            $this->assertArrayHasKey('auth_type', $item);
            $this->assertArrayHasKey('api_token', $item);
            $this->assertArrayHasKey('ip', $item);
            $this->assertArrayHasKey('status', $item);
            $this->assertArrayHasKey('tax_exempt', $item);
            $this->assertArrayHasKey('group', $item);
            $this->assertArrayHasKey('updated_at', $item);
        }
    }

    public function testClientBalanceGetList(): void
    {
        $bool = $this->api_admin->client_balance_add_funds(['id' => 1, 'amount' => 100, 'description' => 'Added from PHPUnit']);
        $this->assertTrue($bool);

        $array = $this->api_admin->client_balance_get_list([]);
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

    public function testClientBatchDelete(): void
    {
        $id = $this->api_admin->client_create(
            [
                'email' => 'tester@gmail.com',
                'first_name' => 'Client',
                'password' => 'password',
            ]);

        $id2 = $this->api_admin->client_create(
            [
                'email' => 'tester2@gmail.com',
                'first_name' => 'Client',
                'password' => 'password',
            ]);
        $id3 = $this->api_admin->client_create(
            [
                'email' => 'tester3@gmail.com',
                'first_name' => 'Client',
                'password' => 'password',
            ]);

        $array = $this->api_admin->client_get_list([]);
        $count = count($array['list']);
        $result = $this->api_admin->client_batch_delete(['ids' => [$id, $id2, $id3]]);
        $array = $this->api_admin->client_get_list([]);

        $this->assertEquals($count - 3, count($array['list']));
        $this->assertTrue($result);
    }

    public function testClientBatchDeleteLog(): void
    {
        $this->api_admin->client_login(['id' => 1]);
        $array = $this->api_admin->client_login_history_get_list([]);

        $result = $this->api_admin->client_batch_delete_log(['ids' => [$array['list'][0]]]);
        $array = $this->api_admin->client_login_history_get_list([]);

        $this->assertEquals(0, count($array['list']));
        $this->assertTrue($result);
    }
}
