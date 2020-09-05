<?php
/**
 * @group Core
 */
class Box_Mod_Client_ServiceTest extends ApiTestCase
{
    public function testEvents()
    {
        $service = new \Box\Mod\Client\Service();
        $service->setDi($this->di);
        $params = array(
            'id' => 1,
            'password' => 'qwerty123',
        );
        $event = new Box_Event(null, 'name', $params, $this->api_admin, $this->api_guest);
        $event->setDi($this->di);
        $bool = $service->onAfterClientSignUp($event);
        $this->assertTrue($bool);
    }

 public function testGenerateEmailConfirmationLink()
    {
        $service = new \Box\Mod\Client\Service();
        $service->setDi($this->di);
        $link = $service->generateEmailConfirmationLink(1);
        $this->assertIsString($link);
        $this->assertEquals(strpos($link, 'http://'), 0);
    }

    public function testRemove()
    {
        //We have a client
        $data = array(
            'email'    =>  'tester@gmail.com',
            'first_name'    =>  'Client',
            'password'    =>  'password',
        );

        $id = $this->api_admin->client_create($data);
        $this->assertTrue($id > 1);

        //Client has license orders
        $data['client_id']      = $id;
        $data['product_id']     = 6;
        $data['period']         = '1M';
        $data['invoice_option'] = 'issue-invoice';
        $data['activate'] = 1;
        $data['config'] = array();

        $orderId = $this->api_admin->order_create($data);
        $this->assertIsInt($orderId);

        //Client has invoice with items for order
        $invoiceModel = $this->di['db']->findOne('Invoice', 'client_id = ?', array($id));
        $this->assertInstanceOf('Model_Invoice', $invoiceModel);

        $invoiceItemModel = $this->di['db']->findOne('InvoiceItem', 'invoice_id = ?', array($invoiceModel->id));
        $this->assertInstanceOf('Model_InvoiceItem', $invoiceItemModel );

        //Client has amount in balance
        $bool = $this->api_admin->client_balance_add_funds(array('id' => 1, 'amount' => 100, 'description' => 'Added from PHPUnit'));
        $this->assertTrue($bool);

        //Client has activity history
        $log = $this->di['db']->dispense('ActivityClientHistory');
        $log->client_id       = $id;
        $log->ip              = '10.0.0.1';
        $log->created_at      = date('Y-m-d H:i:s');
        $log->updated_at      = date('Y-m-d H:i:s');
        $this->di['db']->store($log);

        //Client has email activity
        $entry = $this->di['db']->dispense('ActivityClientEmail');
        $entry->client_id    = $id;
        $entry->created_at   = date('Y-m-d H:i:s');
        $entry->updated_at   = date('Y-m-d H:i:s');
        $this->di['db']->store($entry);

        //Client has system activity
        $systemEntry = $this->di['db']->dispense('ActivitySystem');
        $systemEntry->client_id       = $id;
        $systemEntry->message         = 'PHP UNIT TEST';
        $systemEntry->created_at      = date('Y-m-d H:i:s');
        $systemEntry->updated_at      = date('Y-m-d H:i:s');
        $this->di['db']->store($systemEntry);

        //Client has forum topic message
        $msg                 = $this->di['db']->dispense('ForumTopicMessage');
        $msg->client_id      = $id;
        $msg->message        = 'PHP UNIT MESSAGE';
        $msg->ip             = '10.0.0.1';
        $msg->created_at     = date('Y-m-d H:i:s');
        $msg->updated_at     = date('Y-m-d H:i:s');
        $this->di['db']->store($msg);

        //Client has passwordReset record
        $r = $this->di['db']->dispense('ClientPasswordReset');
        $r->client_id   = $id;
        $r->ip          = '10.0.0.1';
        $r->hash        = sha1(rand(50, rand(10, 99)));
        $r->created_at  = date('Y-m-d H:i:s');
        $r->updated_at  = date('Y-m-d H:i:s');
        $this->di['db']->store($r);

        $clientModel = $this->di['db']->load('Client', $id);
        $this->assertInstanceOf('Model_Client', $clientModel);

        $service = new \Box\Mod\Client\Service();
        $service->setDi($this->di);
        $service->remove($clientModel);

        //Removed items from Db
        $model = $this->di['db']->load('ClientOrder', $orderId);
        $this->assertNull($model);
        $model = $this->di['db']->load('Invoice', $invoiceModel->id);
        $this->assertNull($model);
        $model = $this->di['db']->load('InvoiceItem', $invoiceItemModel->id);
        $this->assertNull($model);
        $model = $this->di['db']->load('ActivityClientHistory', $log->id);
        $this->assertNull($model);
        $model = $this->di['db']->load('ActivityClientEmail', $entry->id);
        $this->assertNull($model);
        $model = $this->di['db']->load('ActivitySystem', $systemEntry->id);
        $this->assertNull($model);
        $model = $this->di['db']->find('ForumTopicMessage', 'client_id = ?', array($msg->client_id));
        $this->assertEmpty($model);
        $model = $this->di['db']->load('ClientPasswordReset', $r->id);
        $this->assertNull($model);
        $model = $this->di['db']->find('ClientBalance', 'client_id = ?', array($clientModel->id));
        $this->assertEmpty($model);
        $model = $this->di['db']->findOne('Client', $clientModel->id);
        $this->assertNull($model);

    }


}