<?php

class Box_Mod_Paidsupport_ServiceTest extends ApiTestCase
{
    public function setup(): void
    {
        parent::setUp();
        $data = [
            'id' => 'paidsupport',
            'type' => 'mod',
        ];
        $this->api_admin->extension_activate($data);

        $hookService = $this->di['mod_service']('hook');
        $hookService->batchConnect('paidsupport');
    }

    public function testCreatePaidSupportTicket(): void
    {
        $ticketPrice = 5.5;

        $balance = $this->api_client->client_balance_get_total();

        if ($balance < $ticketPrice) {
            $this->api_admin->client_balance_add_funds(['id' => 1, 'amount' => $ticketPrice, 'description' => 'Added from PHPUnit']);
        }

        $helpdeskId = 1;
        $beforeBalance = $this->api_client->client_balance_get_total();
        $data = [
            'ext' => 'mod_paidsupport',
            'ticket_price' => $ticketPrice,
            'error_msg' => 'Insufficient amount in balance',
            'helpdesk' => [$helpdeskId => 1],
        ];

        $this->api_admin->extension_config_save($data);
        $data = [
            'subject' => 'Subject',
            'content' => 'content',
            'support_helpdesk_id' => $helpdeskId,

            'order_id' => '1',
            'task' => 'cancel',
        ];
        $id = $this->api_client->support_ticket_create($data);

        $supportTicket = $this->di['db']->load('SupportTicket', $id);
        $this->assertInstanceOf('\Model_SupportTicket', $supportTicket);

        $balance = $this->api_client->client_balance_get_total();
        $this->assertEquals($beforeBalance - $ticketPrice, $balance);

        $clientBalanceModel = $this->di['db']->findOne('ClientBalance', 'ORDER BY id desc');
        $this->assertEquals(-$ticketPrice, $clientBalanceModel->amount);
    }

    public function testCreatePaidSupportTicketHelpdeskhasPaidSupport(): void
    {
        $ticketPrice = 5.5;

        $balance = $this->api_client->client_balance_get_total();

        if ($balance < $ticketPrice) {
            $this->api_admin->client_balance_add_funds(['id' => 1, 'amount' => $ticketPrice, 'description' => 'Added from PHPUnit']);
        }

        $beforeBalance = $this->api_client->client_balance_get_total();

        $helpdeskId = 1;

        $data = [
            'ext' => 'mod_paidsupport',
            'ticket_price' => $ticketPrice,
            'error_msg' => 'Insufficient amount in balance',
            'helpdesk' => [$helpdeskId => 1],
        ];

        $this->api_admin->extension_config_save($data);
        $data = [
            'subject' => 'Subject',
            'content' => 'content',
            'support_helpdesk_id' => $helpdeskId,

            'order_id' => '1',
            'task' => 'cancel',
        ];
        $id = $this->api_client->support_ticket_create($data);

        $supportTicket = $this->di['db']->load('SupportTicket', $id);
        $this->assertInstanceOf('\Model_SupportTicket', $supportTicket);

        $balance = $this->api_client->client_balance_get_total();
        $this->assertEquals($beforeBalance - $ticketPrice, $balance);

        $clientBalanceModel = $this->di['db']->findOne('ClientBalance', 'ORDER BY id desc');
        $this->assertEquals(-$ticketPrice, $clientBalanceModel->amount);
    }

    public function testCreatePaidSupportTicketHelpdesHasNotPaidSupport(): void
    {
        $ticketPrice = 5.5;

        $balance = $this->api_client->client_balance_get_total();

        if ($balance <= $ticketPrice) {
            $this->api_admin->client_balance_add_funds(['id' => 1, 'amount' => $ticketPrice, 'description' => 'Added from PHPUnit']);
        }

        $beforeBalance = $this->api_client->client_balance_get_total();

        $helpdeskId = 1;

        $data = [
            'ext' => 'mod_paidsupport',
            'ticket_price' => $ticketPrice,
            'error_msg' => 'Insufficient amount in balance',
            'helpdesk' => [$helpdeskId => 0],
        ];

        $this->api_admin->extension_config_save($data);
        $data = [
            'subject' => 'Subject',
            'content' => 'content',
            'support_helpdesk_id' => $helpdeskId,

            'order_id' => '1',
            'task' => 'cancel',
        ];
        $id = $this->api_client->support_ticket_create($data);

        $supportTicket = $this->di['db']->load('SupportTicket', $id);
        $this->assertInstanceOf('\Model_SupportTicket', $supportTicket);

        $balance = $this->api_client->client_balance_get_total();
        $this->assertEquals($beforeBalance, $balance);
    }

    public function testCreatePaidSupportTicketInsufficientFunds(): void
    {
        $balance = $this->api_client->client_balance_get_total();

        $ticketPrice = 10 + $balance;

        $errorMessage = 'Insufficient amount in balance';

        $helpdeskId = 1;

        $data = [
            'ext' => 'mod_paidsupport',
            'ticket_price' => $ticketPrice,
            'error_msg' => $errorMessage,
            'helpdesk' => [$helpdeskId => 1],
        ];

        $this->api_admin->extension_config_save($data);
        $data = [
            'subject' => 'Subject',
            'content' => 'content',
            'support_helpdesk_id' => '1',

            'order_id' => '1',
            'task' => 'cancel',
        ];

        $supportTickets = $this->di['db']->find('SupportTicket');

        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionMessage($errorMessage);
        $this->api_client->support_ticket_create($data);

        $supportTicketsAfterCreate = $this->di['db']->find('SupportTicket');
        $this->assertEquals(count($supportTickets), count($supportTicketsAfterCreate));
    }
}
