<?php
/**
 * @group Core
 */
class Api_Client_SupportTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'mod_support.xml';

    public function testAutoresponder()
    {
        //enable autoresponder
        $config = array(
            'ext'                  =>  'mod_support',
            'autorespond_enable'   =>  true,
            'autorespond_message_id'  =>  1,
        );
        $bool = $this->api_admin->extension_config_save($config);
        $this->assertTrue($bool);
        
        $data = array(
            'subject'               =>  'Subject',
            'content'               =>  'content',
            'support_helpdesk_id'   =>  '1',
        );
        $id = $this->api_client->support_ticket_create($data);
        $this->assertInternalType('int', $id);
        
        $data = array(
            'id'    => $id,
        );
        $array = $this->api_client->support_ticket_get($data);
        $this->assertInternalType('array', $array);
        $this->assertEquals(2, count($array['messages']));
    }
    
    public function testSupport()
    {
        $array = $this->api_client->support_ticket_get_list();
        $this->assertInternalType('array', $array);

        $array = $this->api_client->support_helpdesk_get_pairs();
        $this->assertInternalType('array', $array);

        $data = array(
            'subject'               =>  'Subject',
            'content'               =>  'content',
            'support_helpdesk_id'   =>  '1',
        );
        $id = $this->api_client->support_ticket_create($data);
        $this->assertInternalType('int', $id);

        $data = array(
            'id'    => $id,
        );
        $array = $this->api_client->support_ticket_get($data);
        $this->assertInternalType('array', $array);
        
        $data = array(
            'id'        =>  $id,
            'content'   =>  'This is reply message',
        );
        $bool = $this->api_client->support_ticket_reply($data);
        $this->assertTrue($bool);

        $data = array(
            'id'                    =>  $id,
        );
        $bool = $this->api_client->support_ticket_close($data);
        $this->assertTrue($bool);
    }

    public function testTicketTask()
    {
        $data = array(
            'subject'               =>  'Subject',
            'content'               =>  'content',
            'support_helpdesk_id'   =>  '1',

            'order_id'              =>  '1',
            'task'                  =>  'cancel',
        );
        $id = $this->api_client->support_ticket_create($data);
        $this->assertInternalType('int', $id);
    }
}