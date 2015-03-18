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

class Payment_Adapter_Payza extends Payment_AdapterAbstract
{
    private $config = array();

    public function __construct($config)
    {
        $this->config = $config;

        if(!function_exists('curl_exec')) {
            throw new Payment_Exception('PHP Curl extension must be enabled in order to use Payza gateway');
        }

        if(!$this->config['email']) {
            throw new Payment_Exception('Payment gateway "Payza" is not configured properly. Please update configuration parameter "Payza Email address" at "Configuration -> Payments".');
        }
    }

    public function init()
    {
        if(!$this->getParam('email')) {
            throw new Payment_Exception('Payment gateway "Payza" is not configured properly. Please update configuration parameter "Payza Email address" at "Configuration -> Payments".');
        }
        
        if (!$this->getParam('securityCode')) {
        	throw new Payment_Exception('Payment gateway "Payza" is not configured properly. Please update configuration parameter "IPN Security Code" at "Configuration -> Payments".');
        }
    }
    
    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  false,
            'description'     =>  'Clients will be redirected to payza.com to make payment. Note that Payza supports credit card payments.<br />' .
        						  'In your alertpay account go to <i>Business Tools &gt; IPN Setup</i> and enable: IPN Status',
            'form'  => array(
                'email' => array('text', array(
                            'label' => 'Payza Email address', 
                            'description' => 'Your business account email at Payza',
                            'validators'=>array('EmailAddress'),
                    ),
                 ),
                 'securityCode' => array('password', array(
                 			'label' => 'IPN Security Code',
                 			'description' => 'To setup your "IPN Security Code" login to Payza account. Go to "Business Tools" ->  "IPN setup". Enter your PIN and click on "Access". Copy "IPN Security Code" and paste it to this field.',
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
            return 'https://sandbox.payza.com/sandbox/payprocess.aspx';
        }
		return 'https://www.payza.com/PayProcess.aspx';
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
			$params['ap_amount_' . $i]		= $item->getTotalWithTax();
			$params['ap_quantity_' . $i] 	= 1;
			$i++;
		}
		
		return $params;
	}

	public function recurrentPayment(Payment_Invoice $invoice) 
	{
		throw new Payment_Exception('Not implemented yet');	
	}

    /**
     * @see https://dev.payza.com/resources/references/ipn-variables
     * @param type $data
     * @param Payment_Invoice $invoice
     * @return Payment_Transaction 
     */
	public function getTransaction($data, Payment_Invoice $invoice) 
	{
		$ipn = $data['post'];

		$tx = new Payment_Transaction();
        $tx->setId($ipn['ap_referencenumber']);
		$tx->setAmount($ipn['ap_totalamount']);
		$tx->setCurrency($ipn['ap_currency']);
        
        if($ipn['ap_purchasetype'] == 'subscription') {
            switch ($ipn['ap_status']) {
                case 'Subscription-Canceled':
                case 'Subscription-Expired':
                    $tx->setType(Payment_Transaction::TXTYPE_SUBSCR_CANCEL);
                    $tx->setStatus(Payment_Transaction::STATUS_COMPLETE);
                break;
            
                case 'Subscription-Payment-Success':
                    $tx->setType(Payment_Transaction::TXTYPE_SUBSCR_CREATE);
                    $tx->setStatus(Payment_Transaction::STATUS_COMPLETE);
                    $tx->setSubscriptionId($ipn['ap_subscriptionreferencenumber']);
                break;

                case 'Subscription-Payment-Failed':
                case 'Subscription-Payment-Rescheduled':
                default:
                    
                break;
            }
        } else {
            $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);
            if($ipn['ap_status'] == 'Success') {
                $tx->setStatus(Payment_Transaction::STATUS_COMPLETE);
            }
        }
		return $tx;
	}

    public function isIpnValid($data, Payment_Invoice $invoice)
    {
        $ipn = $data['post'];
        
        if($ipn['ap_merchant'] != $this->getParam('email')) {
            error_log('Payza email merchant does not match');
            return false;
        }
        
        if($ipn['ap_securitycode'] != $this->getParam('securityCode')) {
            error_log('Payza security code does not match');
            return false;
        }
        
		return true;
    }

    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoice = $api_admin->invoice_get(array('id'=>$invoice_id));
        $buyer = $invoice['buyer'];

        $p = array(
            ':id'=>sprintf('%05s', $invoice['nr']),
            ':serie'=>$invoice['serie'],
            ':title'=>$invoice['lines'][0]['title']
        );
        $title = __('Payment for invoice :serie:id [:title]', $p);
        $number = $invoice['nr'];

        if($subscription) {

            $subs = $invoice['subscription'];

            $data = array();
            $data['item_name']          = $title;
            $data['item_number']        = $number;
            $data['no_shipping']        = '1';
            $data['no_note']            = '1'; // Do not prompt payers to include a note with their payments. Allowable values for Subscribe buttons:
            $data['currency_code']      = $invoice['currency'];
            $data['return']             = $this->config['return_url'];
            $data['cancel_return']      = $this->config['cancel_url'];
            $data['notify_url']         = $this->config['notify_url'];
            $data['business']           = $this->config['email'];

            $data['cmd']                = '_xclick-subscriptions';
            $data['rm']                 = '2';

            $data['invoice_id']         = $invoice['id'];


            // Recurrence info
            $data['a3']                 = $this->moneyFormat($invoice['total'], $invoice['currency']); // Regular subscription price.
            $data['p3']                 = $subs['cycle']; //Subscription duration. Specify an integer value in the allowable range for the units of duration that you specify with t3.

            /**
             * t3: Regular subscription units of duration. Allowable values:
             *  D – for days; allowable range for p3 is 1 to 90
             *  W – for weeks; allowable range for p3 is 1 to 52
             *  M – for months; allowable range for p3 is 1 to 24
             *  Y – for years; allowable range for p3 is 1 to 5
             */
            $data['t3']                 = $subs['unit'];

            $data['src']                = 1; //Recurring payments. Subscription payments recur unless subscribers cancel their subscriptions before the end of the current billing cycle or you limit the number of times that payments recur with the value that you specify for srt.
            $data['sra']                = 1; //Reattempt on failure. If a recurring payment fails, PayPal attempts to collect the payment two more times before canceling the subscription.
            $data['charset']			= 'UTF-8'; //Sets the character encoding for the billing information/log-in page, for the information you send to PayPal in your HTML button code, and for the information that PayPal returns to you as a result of checkout processes initiated by the payment button. The default is based on the character encoding settings in your account profile.

            //client data
            $data['address1']			= $buyer['address'];
            $data['city']				= $buyer['city'];
            $data['email']				= $buyer['email'];
            $data['first_name']			= $buyer['first_name'];
            $data['last_name']			= $buyer['last_name'];
            $data['zip']				= $buyer['zip'];
            $data['state']				= $buyer['state'];
            $data['charset']            = "utf-8";

        } else {
            $data = array();
            $data['ap_itemname']        = $title;
            $data['ap_quantity']        = 1;
            $data['ap_currency']        = $invoice['currency'];
            $data['ap_merchant']        = $this->config['email'];
            $data['ap_amount']          = $this->moneyFormat($invoice['total'], $invoice['currency']);
            $data['ap_taxamount']       = $this->moneyFormat($invoice['tax'], $invoice['currency']);
            $data['ap_purchasetype']    = "item";
        }

        if($this->config['test_mode']) {
            $url = 'https://secure.payza.com/checkout';
        } else {
            $url = 'https://secure.payza.com/checkout';
        }

        return $this->_generateForm($url, $data);
    }

    /**
     * @param string $url
     */
    private function _generateForm($url, $data, $method = 'post')
    {
        $form  = '';
        $form .= '<form name="payment_form" action="'.$url.'" method="'.$method.'">' . PHP_EOL;
        foreach($data as $key => $value) {
            $form .= sprintf('<input type="hidden" name="%s" value="%s" />', $key, $value) . PHP_EOL;
        }
        $form .=  '<input class="bb-button bb-button-submit" type="submit" value="Pay with Payza" id="payment_button"/>'. PHP_EOL;
        $form .=  '</form>' . PHP_EOL . PHP_EOL;

        if(isset($this->config['auto_redirect']) && $this->config['auto_redirect']) {
            $form .= sprintf('<h2>%s</h2>', __('Redirecting to Payza.com'));
            $form .= "<script type='text/javascript'>$(document).ready(function(){    document.getElementById('payment_button').style.display = 'none';    document.forms['payment_form'].submit();});</script>";
        }

        return $form;
    }
}