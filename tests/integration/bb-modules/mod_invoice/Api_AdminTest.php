<?php

/**
 * @group Core
 */
class Api_Admin_InvoiceTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'transactions.xml';

    public function testTax()
    {
        $array = $this->api_admin->invoice_tax_get_list();
        $this->assertIsArray($array);

        $data = array(
            'name'    => 'VAT in United Kingdom',
            'taxrate' => 20,
        );
        $id   = $this->api_admin->invoice_tax_create($data);
        $this->assertTrue(is_numeric($id));


        $data = array(
            'id' => $id,
        );
        $bool = $this->api_admin->invoice_tax_delete($data);
        $this->assertTrue($bool);

        $data = array(
            'name'    => 'VAT in United Kingdom',
            'taxrate' => 20,
        );
        $bool = $this->api_admin->invoice_tax_setup_eu($data);
        $this->assertTrue($bool);
    }

    public function testSubscriptions()
    {
        $array = $this->api_admin->invoice_subscription_get_list();
        $this->assertIsArray($array);

        $data  = array(
            'id' => 1,
        );
        $array = $this->api_admin->invoice_subscription_get($data);
        $this->assertIsArray($array);

        $data['status'] = 'canceled';
        $bool           = $this->api_admin->invoice_subscription_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->invoice_subscription_delete($data);
        $this->assertTrue($bool);

        $sid                = 'TRWEW_@22--2222';
        $data['sid']        = $sid;
        $data['client_id']  = 1;
        $data['gateway_id'] = 1;
        $data['status']     = 'canceled';
        $data['currency']   = 'USD';
        $id                 = $this->api_admin->invoice_subscription_create($data);
        $this->assertTrue(is_numeric($id));

        $array = $this->api_admin->invoice_subscription_get(array('sid' => $sid));
        $this->assertIsArray($array);
    }

    public function testInvoice()
    {
        $bool = $this->api_admin->invoice_batch_generate();
        $this->assertTrue($bool);

        $bool = $this->api_admin->invoice_batch_activate_paid();
        $this->assertTrue($bool);

        $bool = $this->api_admin->invoice_batch_pay_with_credits();
        $this->assertTrue($bool);

        $bool = $this->api_admin->invoice_batch_pay_with_credits(array('client_id' => 1));
        $this->assertTrue($bool);

        $array = $this->api_admin->invoice_get_statuses(array());
        $this->assertIsArray($array);

        $array = $this->api_admin->invoice_get_list();
        $this->assertIsArray($array);

        $data  = array(
            'id' => 1,
        );
        $array = $this->api_admin->invoice_get($data);
        $this->assertIsArray($array);

        $data = array(
            'client_id'  => 1,
            'gateway_id' => 1,
        );
        $id   = $this->api_admin->invoice_prepare($data);
        $this->assertTrue(is_numeric($id));

        $data['id']    = $id;
        $data['serie'] = 'new';
        $bool          = $this->api_admin->invoice_update($data);
        $this->assertTrue($bool);

        $data = array(
            'id' => $id,
        );
        $bool = $this->api_admin->invoice_approve($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->invoice_delete($data);
        $this->assertTrue($bool);
    }

    public function testItem()
    {
        $data = array(
            'client_id' => 1,
        );
        $id   = $this->api_admin->invoice_prepare($data);
        $this->assertTrue(is_numeric($id));

        $data['id']       = $id;
        $data['serie']    = 'new';
        $data['new_item'] = array(
            'title'  => 'new item',
            'price'  => 'new item',
            'taxed'  => true,
            'period' => '1W',
        );
        $bool             = $this->api_admin->invoice_update($data);
        $this->assertTrue($bool);

        $array = $this->api_admin->invoice_get($data);
        $this->assertEquals(1, count($array['lines']));

        $line_data['id'] = $array['lines'][0]['id'];
        $bool            = $this->api_admin->invoice_item_delete($line_data);
    }

    public function testGatewayInstall()
    {
        $array = $this->api_admin->invoice_gateway_get_available();
        $this->assertIsArray($array);

        $data = array(
            'code' => $array[0],
        );
        $bool = $this->api_admin->invoice_gateway_install($data);
        $this->assertTrue($bool);
    }

    public function testGateways()
    {
        $array = $this->api_admin->invoice_gateway_get_list();
        $this->assertIsArray($array);

        $array = $this->api_admin->invoice_gateway_get_pairs();
        $this->assertIsArray($array);

        $data  = array(
            'id' => 1,
        );
        $array = $this->api_admin->invoice_gateway_get($data);
        $this->assertIsArray($array);

        $data = array(
            'id'              => 1,
            'allow_single'    => 1,
            'allow_recurrent' => 1,
            'enabled'         => 1,
            'title'           => 'title',
        );
        $bool = $this->api_admin->invoice_gateway_update($data);
        $this->assertTrue($bool);

        $id = $this->api_admin->invoice_gateway_copy($data);
        $this->assertTrue(is_numeric($id));

        $data = array(
            'id' => $id,
        );
        $bool = $this->api_admin->invoice_gateway_delete($data);
        $this->assertTrue($bool);
    }

    public function testTransactionsSearch()
    {
        $txn_id = '11--aaa--p';

        $tx    = array(
            'skip_validation' => true,
            'txn_id'          => $txn_id,
        );
        $tx_id = $this->api_admin->invoice_transaction_create($tx);
        $list  = $this->api_admin->invoice_transaction_get_list(array('txn_id' => $txn_id));
        $this->assertEquals(1, count($list['list']));
    }

    public function testTransactions()
    {
        $tx    = array(
            'bb_invoice_id' => 1,
            'bb_gateway_id' => 1,
        );
        $tx_id = $this->api_admin->invoice_transaction_create($tx);

        $txu  = array(
            'id'           => $tx_id,
            'validate_ipn' => '0',
            'amount'       => 150,
            'type'         => Payment_Transaction::TXTYPE_PAYMENT,
            'note'         => 'notes',
            'gateway_id'   => 1,
            'currency'     => 'USD',
            'status'       => Model_Transaction::STATUS_APPROVED,
            'txn_id'       => uniqid(),
            'txn_status'   => 'complete',
        );
        $bool = $this->api_admin->invoice_transaction_update($txu);
        $this->assertTrue($bool);

        $array = $this->api_admin->invoice_transaction_get($txu);
        $this->assertIsArray($array);

        $bool = $this->api_admin->invoice_transaction_process($txu);
        $this->assertTrue($bool);

        $bool = $this->api_admin->invoice_transaction_delete($txu);
        $this->assertTrue($bool);

        $array = $this->api_admin->invoice_transaction_get_list();
        $this->assertIsArray($array);

        $array = $this->api_admin->invoice_transaction_statuses();
        $this->assertIsArray($array);

        $array = $this->api_admin->invoice_transaction_gateway_statuses();
        $this->assertIsArray($array);

        $array = $this->api_admin->invoice_transaction_types();
        $this->assertIsArray($array);

        $array = $this->api_admin->invoice_transaction_get_statuses();
        $this->assertIsArray($array);

        $array = $this->api_admin->invoice_transaction_get_statuses_pairs();
        $this->assertIsArray($array);
    }

    public function testUpdate()
    {
        $data = array(
            'id'     => 1,
            'status' => 'unpaid',
            'notes'  => 'note',
            'serie'  => 'NEW',
            'nr'     => 4,
            'due_at' => date('Y-m-d H:i:s'),
        );
        $bool = $this->api_admin->invoice_update($data);
        $this->assertTrue($bool);
    }

    public function testManualPayment()
    {
        $data = array(
            'id' => 1,
        );
        $bool = $this->api_admin->invoice_mark_as_paid($data);
        $this->assertTrue($bool);
    }

    public function testInvoiceDueDate()
    {
        // invoice due date for order must be the same as order expiration
        // date if order has expiation date

        $oid = 5;
        $o   = $this->api_admin->order_get(array('id' => $oid));
        $this->assertEquals('2012-10-10 00:00:00', $o['expires_at']);
        $id      = $this->api_admin->invoice_renewal_invoice(array('id' => $oid));
        $invoice = $this->api_admin->invoice_get(array('id' => $id));
        $this->assertEquals('2012-10-10 00:00:00', $invoice['due_at']);

        //test event caller
        $this->api_admin->invoice_batch_invoke_due_event();
    }

    public function testRenewalInvoice()
    {
        $oid = 4;
        $this->api_admin->order_renew(array('id' => $oid));

        $o1 = $this->api_admin->order_get(array('id' => $oid));
        $this->assertEquals('active', $o1['status']);

        $id      = $this->api_admin->invoice_renewal_invoice(array('id' => $oid));
        $invoice = $this->api_admin->invoice_get(array('id' => $id));
        $this->api_admin->invoice_mark_as_paid($invoice);

        $o2 = $this->api_admin->order_get(array('id' => $oid));
        $this->assertEquals('active', $o2['status']);

        $this->api_admin->invoice_batch_activate_paid();

        $o3 = $this->api_admin->order_get(array('id' => $oid));
        $this->assertEquals('active', $o3['status']);
        $this->assertNotEquals($o2['expires_at'], $o3['expires_at']);
    }

    public function testReminders()
    {
        $bool = $this->api_admin->invoice_batch_send_reminders();
        $this->assertTrue($bool);

        $bool = $this->api_admin->invoice_batch_send_reminders();
        $this->assertTrue($bool);
    }

    public function testSendReminder()
    {
        $data = array('id' => 1);
        $bool = $this->api_admin->invoice_send_reminder($data);
        $this->assertTrue($bool);
    }

    public function testProcess()
    {
        try {
            $bool = $this->api_admin->invoice_transaction_process_all();
            $this->assertTrue($bool);
        } catch (Exception $e) {

        }

        $bool = $this->api_admin->invoice_batch_activate_paid();
        $this->assertTrue($bool);
    }

    public function testNumbering()
    {
        $data = array(
            'invoice_series'          => 'UNIT',
            'invoice_series_paid'     => 'PAID',
            'invoice_starting_number' => 150,
        );
        $this->api_admin->system_update_params($data);

        $data = array(
            'client_id' => 1,
        );
        $id   = $this->api_admin->invoice_prepare($data);
        $this->assertTrue(is_numeric($id));

        $data = array(
            'id' => $id,
        );
        $this->api_admin->invoice_mark_as_paid($data);
        $array = $this->api_admin->invoice_get($data);
        $this->assertEquals("150", $array['nr']);

        $data = array(
            'client_id' => 1,
        );
        $id   = $this->api_admin->invoice_prepare($data);
        $data = array(
            'id' => $id,
        );
        $this->api_admin->invoice_mark_as_paid($data);
        $array = $this->api_admin->invoice_get($data);
        $this->assertEquals("151", $array['nr']);

        $next = $this->api_admin->system_param(array('key' => 'invoice_starting_number'));
        $this->assertEquals("152", $next);
    }

    public function testRefund()
    {
        $id   = 3;
        $data = array(
            'id'   => $id,
            'note' => 'For some reason',
        );

        $bool = $this->api_admin->system_update_params(array('invoice_refund_logic' => 'manual'));
        $null = $this->api_admin->invoice_refund($data);
        $this->assertNull($null);

        $bool                = $this->api_admin->system_update_params(array('invoice_refund_logic' => 'negative_invoice'));
        $refunded_invoice_id = $this->api_admin->invoice_refund($data);
        $this->assertIsInt($refunded_invoice_id);

        $bool                = $this->api_admin->system_update_params(array('invoice_refund_logic' => 'credit_note'));
        $refunded_invoice_id = $this->api_admin->invoice_refund($data);
        $this->assertIsInt($refunded_invoice_id);

        try {
            $this->api_admin->invoice_refund(array('id' => $refunded_invoice_id));
            $this->fail('Should not refund refunded invoice');
        } catch (Exception $e) {

        }
    }

    public function testItemTax()
    {
        $data = array(
            'tax_enabled' => 1,
        );
        $this->api_admin->system_update_params($data);

        $data = array(
            'client_id' => 1,
        );
        $id   = $this->api_admin->invoice_prepare($data);

        $data['id']                = $id;
        $data['new_item']['title'] = 'test';
        $data['new_item']['price'] = 40;
        $data['new_item']['taxed'] = 1;

        $this->api_admin->invoice_update($data);

        $array = $this->api_admin->invoice_get($data);
        $this->assertEquals(25, $array['taxrate']);
    }

    /**
     * Invoice should be marked as paid after adding money to balance
     */
    public function testCoverInvoice()
    {
        $id   = 2;
        $data = array(
            'id'          => 1,
            'amount'      => 100,
            'description' => 'For invoice payment',
        );

        $this->api_admin->client_balance_add_funds($data);
        $this->api_admin->invoice_pay_with_credits(array('id' => $id));

        $array = $this->api_admin->invoice_get(array('id' => $id));
        $this->assertEquals('paid', $array['status']);
    }

    /**
     * Deposit invoice should be marked as paid and do not charge for it.
     */
    public function testDepositInvoice()
    {
        $id = 5;
        $invoiceModel = $this->di['db']->load('Invoice', 5);
        $this->assertEquals(\Model_Invoice::STATUS_UNPAID, $invoiceModel->status);
        $invoiceItemModel = $this->di['db']->findOne('InvoiceItem', 'invoice_id = ?', array($invoiceModel->id));
        $this->assertEquals(Model_InvoiceItem::TYPE_DEPOSIT, $invoiceItemModel->type);

        $balanceBefore = $this->api_client->client_balance_get_total();
        $this->api_admin->invoice_pay_with_credits(array('id' => $id));
        $balanceAfter = $this->api_client->client_balance_get_total();

        $array = $this->api_admin->invoice_get(array('id' => $id));

        $this->assertEquals(\Model_Invoice::STATUS_PAID, $array['status']);
        $this->assertEquals($balanceBefore, $balanceAfter);
    }


    /**
     * After admin marks as paid deposit invoice account balance should increase.
     */
    public function testDepositInvoiceMarkAsPaid()
    {
        $id = 5;
        $invoiceModel = $this->di['db']->load('Invoice', 5);
        $this->assertEquals(\Model_Invoice::STATUS_UNPAID, $invoiceModel->status);
        $invoiceItemModel = $this->di['db']->findOne('InvoiceItem', 'invoice_id = ?', array($invoiceModel->id));
        $this->assertEquals(Model_InvoiceItem::TYPE_DEPOSIT, $invoiceItemModel->type);

        $balanceBefore = $this->api_client->client_balance_get_total();
        $this->api_admin->invoice_mark_as_paid(array('id' => $id, 'execute' => 1));
        $balanceAfter = $this->api_client->client_balance_get_total();

        $array = $this->api_admin->invoice_get(array('id' => $id));

        $this->assertEquals(\Model_Invoice::STATUS_PAID, $array['status']);
        $this->assertEquals($balanceAfter, $balanceBefore+$invoiceItemModel->price);

        $accountBalance = $this->di['db']->findOne('ClientBalance', 'order by id desc');
        $this->assertEquals($invoiceItemModel->title, $accountBalance->description);
        $this->assertEquals($invoiceItemModel->price, $accountBalance->amount);
    }

    /**
     * Invoice should be marked as paid after adding money to balance
     */
    public function testCredits()
    {
        $id = 2;
        $this->api_admin->invoice_batch_pay_with_credits();

        $array = $this->api_admin->invoice_get(array('id' => $id));
        $this->assertEquals('unpaid', $array['status']);
        $this->assertEmpty($array['credit']);

        $this->api_admin->invoice_batch_pay_with_credits();
        $array = $this->api_admin->invoice_get(array('id' => $id));
        $this->assertEquals('unpaid', $array['status']);

        $data = array(
            'id'          => 1,
            'amount'      => 100,
            'description' => 'For invoice payment',
        );

        $this->api_admin->client_balance_add_funds($data);
        $this->api_admin->invoice_batch_pay_with_credits();
        $array = $this->api_admin->invoice_get(array('id' => $id));
        $this->assertEquals('paid', $array['status']);
    }

    public function testPrepareWithoutItems()
    {
        $data  = array(
            'client_id' => 1,
        );
        $id    = $this->api_admin->invoice_prepare($data);
        $array = $this->api_admin->invoice_get(array('id' => $id));
        $this->assertEquals(0, count($array['lines']));
    }

    public function testPrepareWithItems()
    {
        $data  = array(
            'client_id' => 1,
            'items'     => array(
                array(
                    'title'    => 'first line test title',
                    'period'   => '1M',
                    'quantity' => 3,
                    'price'    => 4.65,
                ),
                array(
                    'title'  => 'second line test title',
                    'period' => '2M'
                ),
            ),
        );
        $id    = $this->api_admin->invoice_prepare($data);
        $array = $this->api_admin->invoice_get(array('id' => $id));

        $this->assertEquals(2, count($array['lines']));

        $line0 = $array['lines'][0];
        $this->assertEquals('first line test title', $line0['title']);
        $this->assertEquals('1M', $line0['period']);
        $this->assertEquals(3, $line0['quantity']);
        $this->assertEquals(4.65, $line0['price']);

        $line1 = $array['lines'][1];
        $this->assertEquals('second line test title', $line1['title']);
        $this->assertEquals('2M', $line1['period']);
        $this->assertEquals(1, $line1['quantity']);
        $this->assertEquals(0.00, $line1['price']);
    }

    public function testTaskHook()
    {
        $params = json_encode(array('param' => 'value'));
        $event  = 'onAfterClientCalledExampleModule';

        $idata      = array(
            'client_id' => 1,
            'items'     => array(
                array(
                    'title'  => 'Test custom item activation',
                    'price'  => 12.22,
                    'type'   => 'hook_call',
                    'task'   => $event,
                    'rel_id' => $params,
                ),
            ),
        );
        $invoice_id = $this->api_admin->invoice_prepare($idata);
        $this->api_admin->invoice_approve(array('id' => $invoice_id));
        $invoice = $this->api_admin->invoice_get(array('id' => $invoice_id));

        $line0 = $invoice['lines'][0];
        $this->assertEquals('hook_call', $line0['type']);
        $this->assertEquals($event, $line0['task']);
        $this->assertEquals($params, $line0['rel_id']);

        // custom hook executes on payment event
        // event hook inseerts data to database to test
        $this->api_admin->extension_activate(array('type' => 'mod', 'id' => 'example'));
        $invoice = $this->api_admin->invoice_mark_as_paid(array('id' => $invoice_id, 'execute' => true));
        //@todo
        //$b = R::findOne('extension_meta', "extension = 'mod_example' AND meta_key = 'event_params'");
        //$this->assertEquals($params, $b->meta_value);
    }

    public function testInvoiceGetList()
    {
        $array = $this->api_admin->invoice_get_list();
        $this->assertIsArray($array);


        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);

        if (count($list)) {
            $item = $list[1];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('serie', $item);
            $this->assertArrayHasKey('nr', $item);
            $this->assertArrayHasKey('serie_nr', $item);
            $this->assertArrayHasKey('hash', $item);
            $this->assertArrayHasKey('gateway_id', $item);
            $this->assertArrayHasKey('taxname', $item);
            $this->assertArrayHasKey('taxrate', $item);
            $this->assertArrayHasKey('currency', $item);
            $this->assertArrayHasKey('currency_rate', $item);
            $this->assertArrayHasKey('tax', $item);
            $this->assertArrayHasKey('subtotal', $item);
            $this->assertArrayHasKey('total', $item);
            $this->assertArrayHasKey('status', $item);
            $this->assertArrayHasKey('notes', $item);
            $this->assertArrayHasKey('text_1', $item);
            $this->assertArrayHasKey('text_2', $item);
            $this->assertArrayHasKey('due_at', $item);
            $this->assertArrayHasKey('paid_at', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('updated_at', $item);
            $this->assertArrayHasKey('lines', $item);
            $this->assertIsArray($item['lines']);
            $line = $item['lines'][0];
            $this->assertIsArray($line);
            $this->assertArrayHasKey('id', $line);
            $this->assertArrayHasKey('title', $line);
            $this->assertArrayHasKey('period', $line);
            $this->assertArrayHasKey('quantity', $line);
            $this->assertArrayHasKey('unit', $line);
            $this->assertArrayHasKey('price', $line);
            $this->assertArrayHasKey('tax', $line);
            $this->assertArrayHasKey('taxed', $line);
            $this->assertArrayHasKey('charged', $line);
            $this->assertArrayHasKey('total', $line);
            $this->assertArrayHasKey('order_id', $line);
            $this->assertArrayHasKey('type', $line);
            $this->assertArrayHasKey('rel_id', $line);
            $this->assertArrayHasKey('task', $line);
            $this->assertArrayHasKey('status', $line);
            $this->assertArrayHasKey('lines', $item);

            $this->assertIsArray($item['buyer']);
            $buyer = $item['buyer'];
            $this->assertArrayHasKey('first_name', $buyer);
            $this->assertArrayHasKey('last_name', $buyer);
            $this->assertArrayHasKey('company', $buyer);
            $this->assertArrayHasKey('company_vat', $buyer);
            $this->assertArrayHasKey('company_number', $buyer);
            $this->assertArrayHasKey('address', $buyer);
            $this->assertArrayHasKey('city', $buyer);
            $this->assertArrayHasKey('state', $buyer);
            $this->assertArrayHasKey('country', $buyer);
            $this->assertArrayHasKey('phone', $buyer);
            $this->assertArrayHasKey('phone_cc', $buyer);
            $this->assertArrayHasKey('email', $buyer);
            $this->assertArrayHasKey('zip', $buyer);

            $this->assertIsArray($item['seller']);
            $seller = $item['seller'];
            $this->assertArrayHasKey('company', $seller);
            $this->assertArrayHasKey('company_vat', $seller);
            $this->assertArrayHasKey('company_number', $seller);
            $this->assertArrayHasKey('address', $seller);
            $this->assertArrayHasKey('phone', $seller);
            $this->assertArrayHasKey('email', $seller);

            $this->assertArrayHasKey('subscribable', $item);
            $this->assertArrayHasKey('subscription', $item);
            $this->assertIsArray($item['subscription']);

            $subscription = $item['subscription'];
            $this->assertArrayHasKey('unit', $subscription);
            $this->assertArrayHasKey('cycle', $subscription);
            $this->assertArrayHasKey('period', $subscription);
        }
    }

    public function testTransactionGetList()
    {
        $array = $this->api_admin->invoice_transaction_get_list();
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);

        if (count($list)) {
            $item = $list[0];
            $this->arrayHasKey('id', $item);
            $this->arrayHasKey('invoice_id', $item);
            $this->arrayHasKey('txn_id', $item);
            $this->arrayHasKey('txn_status', $item);
            $this->arrayHasKey('gateway_id', $item);
            $this->arrayHasKey('gateway', $item);
            $this->arrayHasKey('amount', $item);
            $this->arrayHasKey('currency', $item);
            $this->arrayHasKey('type', $item);
            $this->arrayHasKey('status', $item);
            $this->arrayHasKey('ip', $item);
            $this->arrayHasKey('validate_ipn', $item);
            $this->arrayHasKey('error', $item);
            $this->arrayHasKey('error_code', $item);
            $this->arrayHasKey('note', $item);
            $this->arrayHasKey('created_at', $item);
            $this->arrayHasKey('updated_at', $item);
        }
    }

    public function testGatewayGetList()
    {
        $array = $this->api_admin->invoice_gateway_get_list();
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);

        if (count($list)) {
            $item = $list[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('code', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('allow_single', $item);
            $this->assertArrayHasKey('allow_recurrent', $item);
            $this->assertArrayHasKey('accepted_currencies', $item);
            $this->assertIsArray($item['accepted_currencies']);
        }
    }

    public function testSubscriptionGetList()
    {
        $array = $this->api_admin->invoice_subscription_get_list();
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);

        if (count($list)) {
            $item = $list[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('sid', $item);
            $this->assertArrayHasKey('period', $item);
            $this->assertArrayHasKey('amount', $item);
            $this->assertArrayHasKey('currency', $item);
            $this->assertArrayHasKey('status', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('updated_at', $item);
            $this->assertArrayHasKey('client', $item);

            $client = $item['client'];
            $this->assertIsArray($client);
            $this->assertArrayHasKey('id', $client);
            $this->assertArrayHasKey('aid', $client);
            $this->assertArrayHasKey('email', $client);
            $this->assertArrayHasKey('type', $client);
            $this->assertArrayHasKey('group_id', $client);
            $this->assertArrayHasKey('company', $client);
            $this->assertArrayHasKey('company_vat', $client);
            $this->assertArrayHasKey('company_number', $client);
            $this->assertArrayHasKey('first_name', $client);
            $this->assertArrayHasKey('last_name', $client);
            $this->assertArrayHasKey('gender', $client);
            $this->assertArrayHasKey('birthday', $client);
            $this->assertArrayHasKey('phone_cc', $client);
            $this->assertArrayHasKey('phone', $client);
            $this->assertArrayHasKey('address_1', $client);
            $this->assertArrayHasKey('address_2', $client);
            $this->assertArrayHasKey('city', $client);
            $this->assertArrayHasKey('state', $client);
            $this->assertArrayHasKey('postcode', $client);
            $this->assertArrayHasKey('country', $client);
            $this->assertArrayHasKey('currency', $client);
            $this->assertArrayHasKey('notes', $client);
            $this->assertArrayHasKey('created_at', $client);

            $this->assertArrayHasKey('gateway', $item);
            $gateway = $item['gateway'];
            $this->assertIsArray($gateway);
            $this->assertArrayHasKey('id', $gateway);
            $this->assertArrayHasKey('code', $gateway);
            $this->assertArrayHasKey('title', $gateway);
            $this->assertArrayHasKey('allow_single', $gateway);
            $this->assertArrayHasKey('allow_recurrent', $gateway);
            $this->assertArrayHasKey('accepted_currencies', $gateway);
            $this->assertIsArray($gateway['accepted_currencies']);
        }
    }

    public function testInvoiceBatchDelete()
    {
        $array = $this->api_admin->invoice_get_list(array());

        foreach ($array['list'] as $value) {
            $ids[] = $value['id'];
        }
        $result = $this->api_admin->invoice_batch_delete(array('ids' => $ids));
        $array  = $this->api_admin->invoice_get_list(array());

        $this->assertEquals(0, count($array['list']));
        $this->assertTrue($result);
    }

    public function testInvoiceBatchDeleteSubscription()
    {
        $array = $this->api_admin->invoice_subscription_get_list(array());

        foreach ($array['list'] as $value) {
            $ids[] = $value['id'];
        }
        $result = $this->api_admin->invoice_batch_delete_subscription(array('ids' => $ids));
        $array  = $this->api_admin->invoice_subscription_get_list(array());

        $this->assertEquals(0, count($array['list']));
        $this->assertTrue($result);
    }

    public function testInvoiceBatchDeleteTransaction()
    {
        $array = $this->api_admin->invoice_transaction_get_list(array());

        foreach ($array['list'] as $value) {
            $ids[] = $value['id'];
        }
        $result = $this->api_admin->invoice_batch_delete_transaction(array('ids' => $ids));
        $array  = $this->api_admin->invoice_transaction_get_list(array());

        $this->assertEquals(0, count($array['list']));
        $this->assertTrue($result);
    }

    public function testInvoiceBatchDeleteTax()
    {
        $array = $this->api_admin->invoice_tax_get_list(array());

        foreach ($array['list'] as $value) {
            $ids[] = $value['id'];
        }
        $result = $this->api_admin->invoice_batch_delete_tax(array('ids' => $ids));
        $array  = $this->api_admin->invoice_tax_get_list(array());

        $this->assertEquals(0, count($array['list']));
        $this->assertTrue($result);
    }

    public function testPrepareInvoiceDueDateProvider()
    {
        $this->assertTrue(true);

        return array(
            array(100, 100),
            array('', 1),
            array(null, 1),
        );
    }

    /**
     * @dataProvider testPrepareInvoiceDueDateProvider
     */
    public function testPrepareInvoiceDueDate($invoice_due_days, $diff)
    {
        if (!is_null($invoice_due_days)) {
            $this->api_admin->system_update_params(array('invoice_due_days' => $invoice_due_days));
        }

        $data  = array(
            'client_id' => 1,
        );
        $id    = $this->api_admin->invoice_prepare($data);
        $array = $this->api_admin->invoice_get(array('id' => $id));
        $this->assertEquals(substr($array['due_at'], 0, 10), date('Y-m-d', strtotime("+ $diff day")));
    }

    public function testUpdateTaxRule()
    {
        $id = 2;

        $data = array(
            'id' => $id,
            'name' => 'Updated Tax rule',
            'taxrate' => 99,
            'country' => 'NL'
        );
        $this->api_admin->invoice_tax_update($data);


        $tax = $this->api_admin->invoice_tax_get(array('id' => $id));

        $this->assertIsArray($tax);
        $this->assertEquals($data['name'], $tax['name']);
        $this->assertEquals($data['taxrate'], $tax['taxrate']);
        $this->assertEquals($data['country'], $tax['country']);
    }
}