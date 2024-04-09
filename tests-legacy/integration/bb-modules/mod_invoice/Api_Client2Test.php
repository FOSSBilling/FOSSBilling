<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_Client_InvoiceTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'transactions.xml';

    public function testProformaInvoices(): void
    {
        // prepare expiring order
        $data = [
            'id' => 5,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ];
        $bool = $this->api_admin->order_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->invoice_batch_generate();
        $this->assertTrue($bool);

        $array = $this->api_client->invoice_get_list();
        $this->assertIsArray($array);

        $data = [
            'hash' => $array['list'][0]['hash'],
        ];
        $array = $this->api_client->invoice_get($data);
        $this->assertIsArray($array);
    }

    public function testRenewal(): void
    {
        $data = [
            'order_id' => 3,
        ];
        $hash = $this->api_client->invoice_renewal_invoice($data);
        $this->assertIsString($hash);
    }

    public function testFunds(): void
    {
        $data = [
            'amount' => 30,
        ];
        $hash = $this->api_client->invoice_funds_invoice($data);
        $this->assertIsString($hash);
    }

    public function testDelete(): void
    {
        $invoices = $this->di['db']->find('Invoice', '1');
        $pf = $invoices[1];
        $hash = $pf->hash;

        $data = [
            'hash' => $hash,
        ];
        $bool = $this->api_client->invoice_delete($data);
        $this->assertTrue($bool);
    }

    public function testTransactions(): void
    {
        $array = $this->api_client->invoice_transaction_get_list();
        $this->assertIsArray($array);
    }

    public function testInvoiceGetList(): void
    {
        $array = $this->api_client->invoice_get_list();
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

    public function testTransactionGetList(): void
    {
        $array = $this->api_client->invoice_transaction_get_list();
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);

        if (count($list)) {
            $item = $list[0];
            $this->arrayHasKey('id');
            $this->arrayHasKey('invoice_id');
            $this->arrayHasKey('txn_id');
            $this->arrayHasKey('txn_status');
            $this->arrayHasKey('gateway_id');
            $this->arrayHasKey('gateway');
            $this->arrayHasKey('amount');
            $this->arrayHasKey('currency');
            $this->arrayHasKey('type');
            $this->arrayHasKey('status');
            $this->arrayHasKey('ip');
            $this->arrayHasKey('validate_ipn');
            $this->arrayHasKey('error');
            $this->arrayHasKey('error_code');
            $this->arrayHasKey('note');
            $this->arrayHasKey('created_at');
            $this->arrayHasKey('updated_at');
        }
    }
}
