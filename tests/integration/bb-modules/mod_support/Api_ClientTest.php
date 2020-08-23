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
        $this->assertIsInt($id);
        
        $data = array(
            'id'    => $id,
        );
        $array = $this->api_client->support_ticket_get($data);
        $this->assertIsArray($array);
        $this->assertEquals(2, count($array['messages']));
    }
    
    public function testSupport()
    {
        $array = $this->api_client->support_ticket_get_list();
        $this->assertIsArray($array);

        $array = $this->api_client->support_helpdesk_get_pairs();
        $this->assertIsArray($array);

        $data = array(
            'subject'               =>  'Subject',
            'content'               =>  'content',
            'support_helpdesk_id'   =>  '1',
        );
        $id = $this->api_client->support_ticket_create($data);
        $this->assertIsInt($id);

        $data = array(
            'id'    => $id,
        );
        $array = $this->api_client->support_ticket_get($data);
        $this->assertIsArray($array);
        
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
        $this->assertIsInt($id);
    }

    /**
     * @expectedException \Box_Exception
     */
    public function testCanSubmitTicketException()
    {
        $this->api_admin->extension_config_save(
            array(
                'ext'      => 'mod_support',
                'wait_hours' => 24,
            ));
        $data = array(
            'client_id'           => 1,
            'support_helpdesk_id' => 1,
            'subject'             => 'this is subject',
            'content'             => 'this is content',
        );
        $this->api_client->support_ticket_create($data);
        $this->api_client->support_ticket_create($data); //should throw an exception because 1 ticket per 24 hours is allowed
    }

    public function testCanSubmitTicket()
    {
        $this->api_admin->extension_config_save(
            array(
                'ext'      => 'mod_support',
                'wait_hours' => '',
            ));
        $data = array(
            'client_id'           => 1,
            'support_helpdesk_id' => 1,
            'subject'             => 'this is subject',
            'content'             => 'this is content',
        );
        $this->api_client->support_ticket_create($data);
        $this->api_client->support_ticket_create($data); //should not throw an exception as delay time is not set
    }
}