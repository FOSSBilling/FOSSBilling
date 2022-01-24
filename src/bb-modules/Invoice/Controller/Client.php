<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


namespace Box\Mod\Invoice\Controller;

class Client implements \Box\InjectionAwareInterface
{
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public function register(\Box_App &$app)
    {
        $app->get('/invoice', 'get_invoices', array(), get_class($this));
        $app->post('/invoice', 'get_invoices', array(), get_class($this));
        $app->get('/invoice/:hash', 'get_invoice', array('hash'=>'[a-z0-9]+'), get_class($this));
        $app->post('/invoice/:hash', 'get_invoice', array('hash'=>'[a-z0-9]+'), get_class($this));
        $app->get('/invoice/print/:hash', 'get_invoice_print', array('hash'=>'[a-z0-9]+'), get_class($this));
        $app->post('/invoice/print/:hash', 'get_invoice_print', array('hash'=>'[a-z0-9]+'), get_class($this));
        $app->get('/invoice/banklink/:hash/:id', 'get_banklink', array('id'=>'[0-9]+', 'hash'=>'[a-z0-9]+'), get_class($this));
        $app->get('/invoice/thank-you/:hash', 'get_thankyoupage', array('hash'=>'[a-z0-9]+'), get_class($this));
        $app->get('/invoice/pdf/:hash', 'get_pdf', array('hash'=>'[a-z0-9]+'), get_class($this));
    }

    public function get_invoices(\Box_App $app)
    {
        $this->di['is_client_logged'];
        return $app->render('mod_invoice_index');
    }

    public function get_invoice(\Box_App $app, $hash)
    {
        $api = $this->di['api_guest'];
        $data = array(
            'hash' => $hash,
        );
        $invoice = $api->invoice_get($data);
        return $app->render('mod_invoice_invoice', array('invoice'=>$invoice));
    }

    public function get_invoice_print(\Box_App $app, $hash)
    {
        $api = $this->di['api_guest'];
        $data = array(
            'hash' => $hash,
        );
        $invoice = $api->invoice_get($data);
        return $app->render('mod_invoice_print', array('invoice'=>$invoice));
    }

    public function get_thankyoupage(\Box_App $app, $hash)
    {
        $api = $this->di['api_guest'];
        $data = array(
            'hash' => $hash,
        );
        $invoice = $api->invoice_get($data);
        return $app->render('mod_invoice_thankyou', array('invoice'=>$invoice));
    }
    
    public function get_banklink(\Box_App $app, $hash, $id)
    {
        $api = $this->di['api_guest'];
        $data = array(
            'subscription'  =>  $this->di['request']->getQuery('subscription'),
            'hash'          =>  $hash,
            'gateway_id'    =>  $id,
            'auto_redirect' =>  true,
        );

        $invoice = $api->invoice_get($data);
        $result = $api->invoice_payment($data);
        return $app->render('mod_invoice_banklink', array('payment'=>$result, 'invoice'=>$invoice));
    }

    public function get_pdf (\Box_App $app, $hash)
    {
        $api = $this->di['api_guest'];
        $data = array(
            'hash' => $hash,
        );
        $invoice = $api->invoice_pdf($data);
        return $app->render('mod_invoice_pdf', array('invoice'=>$invoice));
    }
}