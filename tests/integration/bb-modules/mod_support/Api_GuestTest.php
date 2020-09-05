<?php
/**
 * @group Core
 */
class Api_Guest_SupportTest extends ApiTestCase
{
    public function testContact()
    {
        $data = array(
            'name'  =>  'This is me',
            'email'  =>  'email@email.com',
            'subject'  =>  'subject',
            'message'  =>  'Message',
        );
        $hash = $this->api_guest->support_ticket_create($data);
        $this->assertIsString($hash);

        $data = array(
            'hash'    => $hash,
        );
        $array = $this->api_guest->support_ticket_get($data);
        $this->assertIsArray($array);

        $data = array(
            'hash'    => $hash,
            'message' => 'Hello',
        );
        $hash = $this->api_guest->support_ticket_reply($data);
        $this->assertIsString($hash);

        $bool = $this->api_guest->support_ticket_close($data);
        $this->assertTrue($bool);
    }
}