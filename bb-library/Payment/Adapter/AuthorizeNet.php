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

class Payment_Adapter_AuthorizeNet extends Payment_AdapterAbstract
{
    public function init()
    {
        if (!extension_loaded('curl')) {
            throw new Payment_Exception('cURL extension is not enabled');
        }

        if (!extension_loaded('simplexml')) {
        	throw new Payment_Exception('SimpleXml extension is not enabled');
        }

        if(!$this->getParam('apiLoginId')) {
            throw new Payment_Exception('Payment gateway "Authorize.net" is not configured properly. Please update configuration parameter "API Login ID" at "Configuration -> Payments".');
        }
        
        if (!$this->getParam('transactionKey')) {
        	throw new Payment_Exception('Payment gateway "Authorize.net" is not configured properly. Please update configuration parameter "Transaction Key" at "Configuration -> Payments".');
        }
        
        if (!$this->getParam('md5Hash')) {
        	throw new Payment_Exception('Payment gateway "Authorize.net" is not configured properly. Please update configuration parameter "MD5 Hash Value" at "Configuration -> Payments".');
        }
    }
    
    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  false,
            'description'     =>  'To setup Authorize.net merchant account in BoxBilling you need to go to <i>Account &gt; Settinggs &gt; MD5-Hash</i> and enter your own secret hash values. To receive instant payment notifications, copy "IPN callback URL" to Authorize.net "Account->Silent Post URL"',
            'form'  => array(
                'apiLoginId' => array('text', array(
                            'label' 		=> 'API Login ID', 
                            'description' 	=> 'Authorize.net API Login ID', 
                    ),
                 ),
                 'transactionKey' => array('password', array(
                 			'label'			=> 'Transaction Key',
                 			'description'	=> 'Authorize.net Transaction Key',
                 	),
                 ),
                 'md5Hash' => array('password', array(
                 			'label'			=> 'MD5 Hash Value',
                 			'description'	=> 'The MD5 Hash value is a random value configured by the merchant in the Merchant Interface. It should be stored securely separately from the merchant\'s Web server. For more information on how to configure this value, see the Merchant Integration Guide at http://www.authorize.net/support/merchant/.',
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

	public function getServiceUrl() {
		if ($this->testMode) {
			return 'https://test.authorize.net/gateway/transact.dll';
		}	
		
		return 'https://secure.authorize.net/gateway/transact.dll';
	}

    /**
     * Get invoice id from IPN
     * x_invoice_id is custom param in SIM request
     * 
     * @param array $data
     * @return int
     */
    public function getInvoiceId($data)
    {
        $ipn = $data['post'];
        return isset($ipn['x_invoice_id']) ? (int)$ipn['x_invoice_id'] : NULL;
    }

    /**
     * Authorize.net SIM integration.
     * Requires to setup ONLY "Silent Post URL" at mechants account
     * 
     * @param Payment_Invoice $invoice
     * @return array
     */
	public function singlePayment(Payment_Invoice $invoice) {
		$b = $invoice->getBuyer();
        $fp_sequence = uniqid();
		$fp_timestamp = time();
        $finger = $this->_getFingerprint($invoice->getTotalWithTax(), $invoice->getCurrency(), $fp_sequence, $fp_timestamp);

		$params = array(
			'x_fp_hash'				=>	$finger,
			'x_fp_sequence'			=>	$fp_sequence,                            //The merchant-assigned sequence number for the transaction
			'x_fp_timestamp'		=>	$fp_timestamp,						//The timestamp at the time of fingerprint generation
			'x_login'				=>	$this->getParam('apiLoginId'),		//The merchant's unique API Login ID
            'x_version'             =>	'3.1',
            'x_show_form'           =>	'PAYMENT_FORM',
            'x_method'              =>	'CC',
			'x_amount'				=>	$invoice->getTotalWithTax(),
			'x_currency_code'		=>	$invoice->getCurrency(),
    		'x_invoice_id'			=>	$invoice->getId(),				
    		'x_invoice_num'			=>	$invoice->getNumber(),				//The merchant-assigned invoice number for the transaction
			'x_test_request'		=>	$this->testMode ? 'TRUE' : 'FALSE',
			'x_address'				=>	$b->getAddress(),					//The customer's billing address
    		'x_city'				=>	$b->getCity(),						//The city of the customer's billing address
    		'x_country'				=>	$b->getCountry(),					//The country of the customer's billing address
    		'x_email'				=>	$b->getEmail(),						//The customer's valid email address
    		'x_first_name'			=>	$b->getFirstName(),					//The first name associated with the customer's billing address
			'x_last_name'			=>	$b->getLastName(),					//The last name associated with the customer's billing address									
    		'x_phone'				=>	$b->getPhone(),						//The phone number associated with the customer's billing address
    		'x_company'				=>	$b->getCompany(),					
			'x_state'				=>	$b->getState(),						//The state of the customer's billing address
			'x_zip'					=>	$b->getZip(),						//The ZIP code of the customer's billing address
			'x_delim_data'			=>	'FALSE',							//This field is used for AIM. Set it to FALSE if you are using SIM.

            // do not use this, use silent IPN instead
			'x_relay_response'		=> 'FALSE',

			'x_receipt_link_method'	=> 	'GET',
			'x_receipt_link_text'	=> 	'Go back merchant',
            'x_receipt_link_url'    =>  $this->getParam('return_url'),
		);
		return $params;
	}

	public function recurrentPayment(Payment_Invoice $invoice) {
		throw new Payment_Exception('Not implemented yet');
	}

	public function getTransaction($data, Payment_Invoice $invoice) {
		$ipn = $data['post'];

		$tx = new Payment_Transaction();
		$tx->setId($ipn['x_trans_id']);
		$tx->setType(Payment_Transaction::TXTYPE_PAYMENT);
		$tx->setAmount($ipn['x_amount']);
		$tx->setCurrency($invoice->getCurrency());

		if (isset($ipn['x_response_code'])) {
			switch ($ipn['x_response_code']) {
                //Approved
				case '1':
					$tx->setStatus(Payment_Transaction::STATUS_COMPLETE);
                    break;

                case '2': // Declined
                case '3': // Error
				case '4': // Held for Review
					$tx->setStatus(Payment_Transaction::STATUS_PENDING);
                    break;

				default:
                    throw new Payment_Exception('Authorize.net unknown x_response_code');
			}
		}
		
		return $tx;
	}

    public function isIpnValid($data, Payment_Invoice $invoice)
    {
		$ipn = $data['post'];
		if ($this->testMode && isset($ipn['x_test_request']) && $ipn['x_test_request'] == 'true') {
            return true;
        }
        $hash = $this->_getHash($ipn['x_trans_id'], $invoice->getTotalWithTax());
        return ($ipn['x_MD5_Hash'] == $hash);
    }

    /**
     * @param double $amount
     * @param string $currency
     * @param string $fp_sequence
     * @param integer $fp_timestamp
     */
    private function _getFingerprint($amount, $currency, $fp_sequence, $fp_timestamp)
    {
        if (function_exists('hash_hmac')) {
            return hash_hmac("md5", $this->getParam('apiLoginId') . "^" . $fp_sequence . "^" . 
            				 $fp_timestamp . "^" . $amount . "^" . $currency, $this->getParam('transactionKey')); 
        } else if (function_exists('mhash')) {
        	return bin2hex(mhash(MHASH_MD5, $this->getParam('apiLoginId') . "^" . $fp_sequence . "^" . 
        					 $fp_timestamp . "^" . $amount . "^" . $currency, $this->getParam('transactionKey')));
        } else {
        	return MD5($this->getParam('apiLoginId') . "^" . $fp_sequence . "^" . 
        					 $fp_timestamp . "^" . $amount . "^" . $currency, $this->getParam('transactionKey'));
        }
    }
    
    /**
     * @param double $amount
     */
    private function _getHash($transaction_id, $amount)
    {
    	$hash = MD5($this->getParam('md5Hash') . $this->getParam('apiLoginId') . $transaction_id . $amount);
    	return strtoupper($hash);
    }
}
