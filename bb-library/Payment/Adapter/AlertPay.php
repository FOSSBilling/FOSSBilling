<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

class Payment_Adapter_AlertPay extends Payment_AdapterAbstract
{
    public function init()
    {
        if(!$this->getParam('email')) {
            throw new Payment_Exception('Payment gateway "AlertPay" is not configured properly. Please update configuration parameter "AlertPay Email address" at "Configuration -> Payments".');
        }
        
        if (!$this->getParam('securityCode')) {
        	throw new Payment_Exception('Payment gateway "AlertPay" is not configured properly. Please update configuration parameter "IPN Security Code" at "Configuration -> Payments".');
        }
    }
    
    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  false,
            'description'     =>  'Clients will be redirected to alertpay.com to make payment. Note that alertpay.com supports credit card payments.<br />' .
        						  'In your alertpay account go to <i>Business Tools &gt; IPN Setup</i> and enable: IPN Status',
            'form'  => array(
                'email' => array('text', array(
                            'label' => 'AlertPay Email address', 
                            'description' => 'Your business account email at AlertPay',
                            'validators'=>array('EmailAddress'),
                    ),
                 ),
                 'securityCode' => array('password', array(
                 			'label' => 'IPN Security Code',
                 			'description' => 'To setup your "IPN Security Code" login to AlertPay account. Go to "Business Tools" ->  "IPN setup". Enter your PIN and click on "Access". Copy "IPN Security Code" and paste it to this field.',
                 			'validators' => array('notempty'),
                 	),
                 ),
            ),
        );
    }
    
    /**
     * Return payment gateway type
     * @return string
     */
    public function getType()
    {
        return Payment_AdapterAbstract::TYPE_FORM;
    }
    
    /**
     * Return payment gateway type
     * @return string
     */
    public function getServiceUrl()
    {
        if($this->testMode) {
            return 'https://sandbox.alertpay.com/sandbox/payprocess.aspx';
        }
		return 'https://www.alertpay.com/PayProcess.aspx';
    }

    public function getInvoiceId($data)
    {
        $id = parent::getInvoiceId($data);
        if(!is_null($id)) {
            return $id;
        }
        
        return isset($data['post']['apc_1']) ? (int)$data['post']['apc_1'] : NULL;
    }

	public function singlePayment(Payment_Invoice $invoice) 
	{
		$c = $invoice->getBuyer();
		$params = array(
			'ap_merchant'		=>	$this->getParam('email'),
			'ap_purchasetype'	=>	'service',
			'ap_currency'		=>	$invoice->getCurrency(),
			'ap_alerturl'		=>	$this->getParam('notify_url'),
			'ap_returnurl'		=>	$this->getParam('return_url'),
			'ap_cancelurl'		=>	$this->getParam('cancel_url'),
			'ap_fname'			=>	$c->getFirstName(),
			'ap_lname'			=>	$c->getLastName(),
			'ap_contactemail'	=>	$c->getEmail(),
			'ap_contactphone'	=>	$c->getPhone(),
			'ap_addressline1'	=>	$c->getAddress(),
			'ap_city'			=>	$c->getCity(),
			'ap_stateprovince'	=>	$c->getState(),
			'ap_zippostalcode'	=>	$c->getZip(),
			'ap_country'		=>	$c->getCountry(),
			'apc_1'				=>	$invoice->getId(),
			'apc_2'				=>	$invoice->getNumber(),
		);
		
		$i = 1;
		foreach ($invoice->getItems() as $item) {
			$params['ap_itemcode_' . $i]	= $item->getId();
			$params['ap_itemname_' . $i] 	= $item->getTitle();
			$params['ap_description_' . $i]	= $item->getDescription();
			$params['ap_amount_' . $i]		= $item->getPrice() + $item->getTax();
			$params['ap_quantity_' . $i] 	= $item->getQuantity();
			$i++;
		}
		
		return $params;
	}

	public function recurrentPayment(Payment_Invoice $invoice) 
	{
		throw new Payment_Exception('Not implemented yet');	
	}


	public function getTransaction($data, Payment_Invoice $invoice) 
	{
		$ipn = $data['post'];

		$tx = new Payment_Transaction();
		$tx->setAmount($ipn['ap_totalamount']);
		$tx->setCurrency($ipn['ap_currency']);

        if($ipn['ap_transactiontype'] == 'purchase') {
            $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);
        }
        
        if($ipn['ap_status'] == 'Success') {
			$tx->setStatus(Payment_Transaction::STATUS_COMPLETE);
        }
        
		return $tx;
	}

    public function isIpnValid($data, Payment_Invoice $invoice)
    {
        $ipn = $data['post'];
		return ($ipn['ap_securitycode'] == $this->getParam('securityCode'));
    }
}