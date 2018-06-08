<?php
class Payment_Adapter_PayGol implements \Box\InjectionAwareInterface
{
    private $config = array();
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }
    
    public function __construct($config)
    {
        if(!$config['service_id']) {
            throw new Payment_Exception('Payment gateway "Paygol" is not configured properly. Please update configuration parameter "Service ID" at "Configuration -> Payments gateways".');
        }
		if(!$config['secret_key']) {
            throw new Payment_Exception('Payment gateway "Paygol" is not configured properly. Please update configuration parameter "Secret Key" at "Configuration -> Payments gateways".');
        }
        $this->config = $config;
    }
   
    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'    =>  true,
            'test_mode'                     =>  false,
            'description'     =>  'Paygol is an online payment service provider that offers a wide variety of both worldwide and local payment methods.',
            'form'  => array(
                'service_id' => array('text', array(
                    'label' => 'Service ID', 
                    'description' => 'Enter the ID of your Paygol service (can be found at the "My Services" section of your panel, at Paygol website).',
                    ),
                ),	
                'secret_key' => array('text', array(
                    'label' => 'Secret Key', 
                    'description' => 'Enter the secret key of your Paygol service (can be found at the "My Services" section of your panel, at Paygol website).',
                    ),	
                ),
            ),
        );
    }
    
    ////////////////////////////////////////////
    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoice = $api_admin->invoice_get(array('id'=>$invoice_id));
		
	if($subscription) 
	{
            $data = $this->_recurrentPayment($invoice);
        } else 
	{
            $data = $this->_singlePayment($invoice);
        } 
     	$url = 'https://www.paygol.com/pay'; // url paygol 
        return $this->_generateForm($url, $data);
    }
   
    ////////////////////////////////////////////////////////////////////////////
    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
        if(!$this->isIpnValid($data)) 
        {
            $delete 	 = $this->di['db']->getAll('DELETE FROM transaction WHERE id = :invoice_id', array(':invoice_id' => $id));
            $delete2 	 = $this->di['db']->getAll('DELETE FROM transaction WHERE invoice_id IS NULL');	
            throw new Payment_Exception('Error: IPN is not valid - Paygol ');
        }
		
		
        if ($this->isIpnDuplicate($data))
        {
            $delete 	 = $this->di['db']->getAll('DELETE FROM transaction WHERE id = :invoice_id', array(':invoice_id' => $id));
            $delete2 	 = $this->di['db']->getAll('DELETE FROM transaction WHERE invoice_id IS NULL');	
            throw new Payment_Exception('Error: Record is duplicate - Paygol');
        }
		
        $ipn 		 = array_merge($data['get'], $data['post']);	
        $tx 		 = $api_admin->invoice_transaction_get(array('id'=>$id));
        $custom		 = $ipn['custom'];
        $custom_data 	 = explode(";", $custom);
        $clienteid    	 = $custom_data[0];
        $bbinvoice_id 	 = $custom_data[1];
        $bb_total	 = $custom_data[2];
        $invoicehash	 = $custom_data[3];
        
	$api_admin->invoice_transaction_update(array('id' => $id, 'type' => 'ORDER CREATED'));

	$invoice_id = null;
        if(isset($tx['invoice_id'])) {
            $invoice_id = $bbinvoice_id;
        } elseif(isset($bbinvoice_id)) {
            $invoice_id = $bbinvoice_id;
            $api_admin->invoice_transaction_update(array('id'=>$id, 'invoice_id'=>$invoice_id));
        }
			
        if(!$invoice_id) 
        {
            throw new Payment_Exception('Invoice id could not be determined for this transaction');
        }
        
        $invoice    = $api_admin->invoice_get(array('id'=>$invoice_id));
        $client_id  = $invoice['client']['id'];
        
        $tx_data = array(
            'id'        =>  $id,
            'status'    =>  'pending',
        );
        
        if(empty($tx['txn_id']) && isset($bbinvoice_id)) 
        {
            $tx_data['txn_id'] = $bbinvoice_id;
        }
			
        if(empty($tx['amount']) && isset($ipn['frmprice'])) 
        {
            $tx_data['amount'] = $ipn['frmprice'];
        } 

        if(empty($tx['currency'])) 
        {
            $tx_data['currency'] = $ipn['frmcurrency'];
        }

        $tx_data['txn_status']  = 'complete';
        $tx_data['status']      = 'complete';
        $tx_data['type']      	= 'payment';

        $api_admin->invoice_transaction_update($tx_data);
        $date_now = date('Y-m-d H:i:s');
        $api_admin->invoice_update(array('id' => $invoice_id, 'status' => 'paid', 'paid_at' => $date_now));
				
    }
   
    ///////////////////////////////////////////////
    public function isIpnValid($data)
    {
        $data_get 	 = $data['get'];
        $custom		 = $data_get['custom'];
        $custom_data 	 = explode(";", $custom);
        $clienteid    	 = $custom_data[0]; 
        $bbinvoice_id 	 = $custom_data[1]; 
        $bb_total	 = $custom_data[2]; 
        $invoicehash	 = $custom_data[3]; 
        $gateway_id	 = $data_get['bb_gateway_id'];


        if($data_get['service_id'] != $this->config['service_id'])
        {
        //error_log('Paygol Service ID does not match');
        return false;
        }
		
		
        $key_module = trim($this->config['secret_key']);
        if($data_get['key'] != $key_module)
        {
        //error_log('Paygol secret key does not match');
        return false;
        }

		
        $sql3 = 'SELECT id FROM invoice WHERE id = :invoice_id  AND gateway_id = :gateway_id AND hash = :hash'; 
        $bindings3 = array(
                ':invoice_id' 	  => $bbinvoice_id,
                ':gateway_id' 	  => $gateway_id,
                ':hash' 	  => $invoicehash,
        );
        $rows3 = $this->di['db']->getAll($sql3, $bindings3);

        if (count($rows3)==0)
        {
                //error_log('Paygol IPN Error');
        return false;
        }

        return true;
    }
	
    ////////////////////////////////////////////
    private function _singlePayment($invoice)
    {
        $b = $invoice['buyer'];
        $data = array();

        foreach($invoice['lines'] as $i=>$item) 
        {
           $data['pg_serviceid']    = $this->config['service_id'];
           $data['pg_currency']     = $invoice['currency'];
           $data['pg_name']         = $item['title'];
           $data['pg_custom']       = $invoice['client']['id'].";".$invoice['id'].";".$invoice['total'].";".$invoice['hash'];
           $data['pg_price']        = $invoice['total'];
           $data['pg_return_url']   = $this->config['return_url']; // OK
           $data['pg_cancel_url']   = $this->config['cancel_url']; // Cancel
        }
        return $data;
    }
    
    //////////////////////////////////////////////////
    public function _recurrentPayment($invoice) 
    {
        throw new Payment_Exception('Not implemented yet');	
    }
    
    //////////////////////////////////////////////////
    private function _generateForm($url, $data, $method = 'post')
    {
        $form  = '';
        $form .= '<form name="pg_frm"" action="'.$url.'" method="'.$method.'">' . PHP_EOL;
        foreach($data as $key => $value) {
            $form .= sprintf('<input type="hidden" name="%s" value="%s" />', $key, $value) . PHP_EOL;
	}
        $form .=  '<input class="bb-button bb-button-submit" type="submit" value="Paygol" id="payment_button"/>'. PHP_EOL;
        $form .=  '</form>' . PHP_EOL . PHP_EOL;
		
        if(isset($this->config['auto_redirect']) && $this->config['auto_redirect']) {
            $form .= sprintf('<h2>%s</h2>', __('Redirecting to Paygol.com'));
            $form .=  "<script type='text/javascript'>$(document).ready(function(){    document.getElementById('payment_button').style.display = 'none';    document.forms['pg_frm'].submit();});</script>";
        }
        return $form;
    } 
    //////////////////////////////////////////////////
    public function isIpnDuplicate(array $data)
    {
        $data_get       = $data['get'];
        $custom         = $data_get['custom'];
        $custom_data    = explode(";", $custom);
        $clienteid      = $custom_data[0]; 
        $bbinvoice_id   = $custom_data[1]; 
        $bb_total       = $custom_data[2]; 
        $invoicehash    = $custom_data[3]; 
        $gatewayid      = $data_get['bb_gateway_id'];

        $sql      = 'SELECT id FROM transaction WHERE txn_id = :transaction_id  AND amount = :transaction_amount LIMIT 1'; 
        $bindings = array(
                ':transaction_id'       => $bbinvoice_id,
                ':transaction_amount'   => $bb_total,
        );
        $rows = $this->di['db']->getAll($sql, $bindings);
        
        if (count($rows) >=1 ) // invoice 1
        {
                return true;
        }
        //////////////////////////////////////////////////
        $sql2      = 'SELECT id FROM invoice WHERE id = :invoice_id  AND gateway_id = :gateway_id'; 
        $bindings2 = array(
                ':invoice_id' 	  => $bbinvoice_id,
                ':gateway_id' 	  => $gatewayid,
        );
        $rows2 = $this->di['db']->getAll($sql2, $bindings2);
        
        if (count($rows2) ==0 ) // invoice 0
        {
            return true;
        }
        
        return false;
        
    }
}

