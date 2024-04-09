<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Box_Mod_Massmailer_Api_AdminTest extends BBModTestCase
{
    protected $_mod = 'massmailer';
    protected $_initialSeedFile = 'mod_massmailer.xml';

    public function testActions(): void
    {
        $int = $this->api_admin->massmailer_create(['subject' => 'Subject', 'content' => 'content']);
        $this->assertIsInt($int);

        $array = $this->api_admin->massmailer_get(['id' => $int]);
        $this->assertIsArray($array);

        $data = [
            'id' => $int,
            'subject' => 'New subject',
            'content' => 'New content',
            'status' => 'draft',
            'from_email' => 'test@mail.com',
            'from_name' => 'John Doe',
        ];
        $bool = $this->api_admin->massmailer_update($data);
        $this->assertTrue($bool);

        $array = $this->api_admin->massmailer_preview($data);
        $this->assertIsArray($array);
        $this->assertTrue(isset($array['subject']));
        $this->assertTrue(isset($array['content']));

        $array = $this->api_admin->massmailer_receivers($data);
        $this->assertIsArray($array);

        $array = $this->api_admin->massmailer_get_list();
        $this->assertIsArray($array);
        $this->assertEquals(2, $array['total']);

        $new_id = $this->api_admin->massmailer_copy(['id' => $int]);
        $this->assertIsInt($new_id);

        $bool = $this->api_admin->massmailer_send_test(['id' => $new_id]);
        $this->assertTrue($bool);

        $bool = $this->api_admin->massmailer_send(['id' => $int]);
        $this->assertTrue($bool);

        $bool = $this->api_admin->massmailer_delete(['id' => $array['list'][0]['id']]);
        $this->assertTrue($bool);
    }

    public function testFilter(): void
    {
        $filter = [
            'client_status' => ['suspended', 'canceled'],
            'client_groups' => ['1'],
            'has_order' => ['1', '2'],
            'has_order_with_status' => ['active'],
        ];
        $this->api_admin->massmailer_update(['id' => 1, 'filter' => $filter]);
        $this->api_admin->massmailer_send(['id' => 1]);

        $this->assertTrue(is_array($filter));
    }

    public function testMassmailerGetList(): void
    {
        $array = $this->api_admin->massmailer_get_list();
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);

        if (count($list)) {
            $item = $list[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('from_email', $item);
            $this->assertArrayHasKey('from_name', $item);
            $this->assertArrayHasKey('subject', $item);
            $this->assertArrayHasKey('content', $item);
            $this->assertArrayHasKey('filter', $item);
            $this->assertIsArray($item['filter']);
            $this->assertArrayHasKey('status', $item);
            $this->assertArrayHasKey('sent_at', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('updated_at', $item);
        }
    }
}
