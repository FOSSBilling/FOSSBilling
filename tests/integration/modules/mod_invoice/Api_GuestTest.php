<?php
/**
 * @group Core
 */
class Api_Guest_InvoiceTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'transactions.xml';

    public function testGateways()
    {
        $data = array(
            'hash'    =>  'hash',
        );
        $array = $this->api_guest->invoice_get($data);
        $this->assertIsArray($array);

        $array = $this->api_guest->invoice_gateways();
        $this->assertIsArray($array);
    }


    public static function gateways(){
        return array(
            array(1, 1),
            array(2, 2),
            array(2, 3),
            array(2, 4),
            array(3, 1),
        );
    }

    /**
     * @dataProvider gateways
     */
    public function testPayment($id, $iid)
    {
        $pf = $this->di['db']->findOne('Invoice', $iid);
        $hash = $pf->hash;

        $data = array(
            'subscription'  =>  false,
            'hash'          =>  $hash,
            'gateway_id'    =>  $id,
        );
        $array = $this->api_guest->invoice_payment($data);
        $this->assertIsArray($array);
    }

    public function testNewPayment()
    {
        $gateway_id = 3;

        $pf = $this->di['db']->findOne('Invoice', 1);
        $hash = $pf->hash;

        $data = array(
            'subscription'  =>  false,
            'hash'          =>  $hash,
            'gateway_id'    =>  $gateway_id,
        );
        $form = $this->api_guest->invoice_payment($data);
        $this->assertIsArray($form);
        $this->assertEquals('html', $form['type']);
        $this->assertFalse(empty($form['result']));
        
        //subscription
        $pf = $this->di['db']->findOne('Invoice', 2);
        $hash = $pf->hash;
        $data = array(
            'subscription'  =>  true,
            'hash'          =>  $hash,
            'gateway_id'    =>  $gateway_id,
        );
        $form2 = $this->api_guest->invoice_payment($data);
        $this->assertIsArray($form2);
        $this->assertEquals('html', $form2['type']);
        $this->assertFalse(empty($form2['result']));
    }

    public function testupdate()
    {
        $data = array(
            'hash' => 'hash'
        );

        $bool = $this->api_guest->invoice_update($data);
        $this->assertTrue($bool);
    }

}