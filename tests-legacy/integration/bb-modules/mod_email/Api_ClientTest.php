<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_Client_EmailTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'emails.xml';

    public function testEmails(): void
    {
        $array = $this->api_client->email_get_list();
        $this->assertIsArray($array);

        $data = [
            'id' => 1,
        ];
        $array = $this->api_client->email_get($data);
        $this->assertIsArray($array);
    }

    public function testResend(): void
    {
        $data = [
            'id' => 1,
        ];
        $bool = $this->api_client->email_resend($data);
        $this->assertTrue($bool);

        $array = $this->api_client->email_get_list();
        $this->assertEquals(2, $array['total']);
    }

    public function testDelete(): void
    {
        $data = [
            'id' => 1,
        ];

        $bool = $this->api_client->email_delete($data);
        $this->assertTrue($bool);

        $array = $this->api_client->email_get_list();
        $this->assertEquals(0, $array['total']);
    }

    public function testEmailEmailGetList(): void
    {
        $array = $this->api_client->email_get_list();
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);
        if (count($list)) {
            $item = $list[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('client_id', $item);
            $this->assertArrayHasKey('sender', $item);
            $this->assertArrayHasKey('recipients', $item);
            $this->assertArrayHasKey('subject', $item);
            $this->assertArrayHasKey('content_html', $item);
            $this->assertArrayHasKey('content_text', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('updated_at', $item);
        }
    }
}
