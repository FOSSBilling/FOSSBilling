<?php

class Box_Mod_Paidsupport_ServiceTest extends ApiTestCase
{

    public function setup(): void
    {
        parent::setUp();
        $data = array(
            'id'    =>  'paidsupport',
            'type'  =>  'mod',
        );
        $this->api_admin->extension_activate($data);

        $hookService = $this->di['mod_service']('hook');
        $hookService->batchConnect('paidsupport');


    }

    public function testCreatePaidSupportTicket()
    {
        $ticketPrice = 5.5;

        $balance = $this->api_client->client_balance_get_total();

        if ($balance < $ticketPrice){
            $this->api_admin->client_balance_add_funds(array('id' => 1, 'amount' => $ticketPrice, 'description' => 'Added from PHPUnit'));
        }

        $helpdeskId = 1;
        $beforeBalance = $this->api_client->client_balance_get_total();
        $data = array(
            'ext'           =>  'mod_paidsupport',
            'ticket_price'  =>  $ticketPrice,
            'error_msg'     =>  'Insufficient amount in balance',
            'helpdesk'      => array($helpdeskId => 1),
        );

        $this->api_admin->extension_config_save($data);
        $data = array(
            'subject'               =>  'Subject',
            'content'               =>  'content',
            'support_helpdesk_id'   =>  $helpdeskId,

            'order_id'              =>  '1',
            'task'                  =>  'cancel',
        );
        $id = $this->api_client->support_ticket_create($data);

        $supportTicket = $this->di['db']->load('SupportTicket', $id);
        $this->assertInstanceOf('\Model_SupportTicket', $supportTicket);

        $balance = $this->api_client->client_balance_get_total();
        $this->assertEquals($beforeBalance - $ticketPrice, $balance);

        $clientBalanceModel = $this->di['db']->findOne('ClientBalance', 'ORDER BY id desc');
        $this->assertEquals(-$ticketPrice, $clientBalanceModel->amount);
    }

    public function testCreatePaidSupportTicket_HelpdeskhasPaidSupport()
    {
        $ticketPrice = 5.5;

        $balance = $this->api_client->client_balance_get_total();

        if ($balance < $ticketPrice){
            $this->api_admin->client_balance_add_funds(array('id' => 1, 'amount' => $ticketPrice, 'description' => 'Added from PHPUnit'));
        }

        $beforeBalance = $this->api_client->client_balance_get_total();

        $helpdeskId = 1;

        $data = array(
            'ext'           =>  'mod_paidsupport',
            'ticket_price'  =>  $ticketPrice,
            'error_msg'     =>  'Insufficient amount in balance',
            'helpdesk'      => array($helpdeskId => 1),
        );

        $this->api_admin->extension_config_save($data);
        $data = array(
            'subject'               =>  'Subject',
            'content'               =>  'content',
            'support_helpdesk_id'   =>  $helpdeskId,

            'order_id'              =>  '1',
            'task'                  =>  'cancel',
        );
        $id = $this->api_client->support_ticket_create($data);

        $supportTicket = $this->di['db']->load('SupportTicket', $id);
        $this->assertInstanceOf('\Model_SupportTicket', $supportTicket);

        $balance = $this->api_client->client_balance_get_total();
        $this->assertEquals($beforeBalance - $ticketPrice, $balance);

        $clientBalanceModel = $this->di['db']->findOne('ClientBalance', 'ORDER BY id desc');
        $this->assertEquals(-$ticketPrice, $clientBalanceModel->amount);
    }

    public function testCreatePaidSupportTicket_HelpdesHasNotPaidSupport()
    {
        $ticketPrice = 5.5;

        $balance = $this->api_client->client_balance_get_total();

        if ($balance <= $ticketPrice){
            $this->api_admin->client_balance_add_funds(array('id' => 1, 'amount' => $ticketPrice, 'description' => 'Added from PHPUnit'));
        }

        $beforeBalance = $this->api_client->client_balance_get_total();

        $helpdeskId = 1;

        $data = array(
            'ext'           =>  'mod_paidsupport',
            'ticket_price'  =>  $ticketPrice,
            'error_msg'     =>  'Insufficient amount in balance',
            'helpdesk'      => array($helpdeskId => 0),
        );

        $this->api_admin->extension_config_save($data);
        $data = array(
            'subject'               =>  'Subject',
            'content'               =>  'content',
            'support_helpdesk_id'   =>  $helpdeskId,

            'order_id'              =>  '1',
            'task'                  =>  'cancel',
        );
        $id = $this->api_client->support_ticket_create($data);

        $supportTicket = $this->di['db']->load('SupportTicket', $id);
        $this->assertInstanceOf('\Model_SupportTicket', $supportTicket);

        $balance = $this->api_client->client_balance_get_total();
        $this->assertEquals($beforeBalance, $balance);
    }

    public function testCreatePaidSupportTicket_insufficientFunds()
    {
        $balance = $this->api_client->client_balance_get_total();

        $ticketPrice = 10 + $balance;

        $errorMessage = 'Insufficient amount in balance';

        $helpdeskId = 1;

        $data = array(
            'ext'           =>  'mod_paidsupport',
            'ticket_price'  =>  $ticketPrice,
            'error_msg'     =>  $errorMessage,
            'helpdesk'      => array($helpdeskId => 1),
        );

        $this->api_admin->extension_config_save($data);
        $data = array(
            'subject'               =>  'Subject',
            'content'               =>  'content',
            'support_helpdesk_id'   =>  '1',

            'order_id'              =>  '1',
            'task'                  =>  'cancel',
        );

        $supportTickets = $this->di['db']->find('SupportTicket');

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage($errorMessage);
        $this->api_client->support_ticket_create($data);

        $supportTicketsAfterCreate = $this->di['db']->find('SupportTicket');
        $this->assertEquals(count($supportTickets), count($supportTicketsAfterCreate));
    }

}