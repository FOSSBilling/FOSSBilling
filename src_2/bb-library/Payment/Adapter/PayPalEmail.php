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

class Payment_Adapter_PayPalEmail implements \Box\InjectionAwareInterface
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
        $this->config = $config;
        
        if(!function_exists('curl_exec')) {
            throw new Payment_Exception('PHP Curl extension must be enabled in order to use PayPal gateway');
        }
        
        if(!isset($this->config['email'])) {
            throw new Payment_Exception('Payment gateway "PayPal" is not configured properly. Please update configuration parameter "PayPal Email address" at "Configuration -> Payments".');
        }
    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  true,
            'description'     =>  'Enter your PayPal email to start accepting payments by PayPal.',
            'form'  => array(
                'email' => array('text', array(
                            'label' => 'PayPal email address for payments',
                            'validators'=>array('EmailAddress'),
                    ),
                 ),
            ),
        );
    }

    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoice = $api_admin->invoice_get(array('id'=>$invoice_id));

        $data = array();
        if($subscription) {
            $data = $this->getSubscriptionFields($invoice);
        } else {
            $data = $this->getOneTimePaymentFields($invoice);
        }
        
        $url = $this->serviceUrl();
        return $this->_generateForm($url, $data);
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
        if(APPLICATION_ENV != 'testing' && !$this->_isIpnValid($data)) {
            throw new Payment_Exception('IPN is not valid');
        }
        
        $ipn = $data['post'];
        
        $tx = $api_admin->invoice_transaction_get(array('id'=>$id));
        
        if(!$tx['invoice_id']) {
            $api_admin->invoice_transaction_update(array('id'=>$id, 'invoice_id'=>$data['get']['bb_invoice_id']));
        }
        
        if(!$tx['type'] && isset($ipn['txn_type'])) {
            $api_admin->invoice_transaction_update(array('id'=>$id, 'type'=>$ipn['txn_type']));
        }
        
        if(!$tx['txn_id'] && isset($ipn['txn_id'])) {
            $api_admin->invoice_transaction_update(array('id'=>$id, 'txn_id'=>$ipn['txn_id']));
        }
        
        if(!$tx['txn_status'] && isset($ipn['payment_status'])) {
            $api_admin->invoice_transaction_update(array('id'=>$id, 'txn_status'=>$ipn['payment_status']));
        }
        
        if(!$tx['amount'] && isset($ipn['mc_gross'])) {
            $api_admin->invoice_transaction_update(array('id'=>$id, 'amount'=>$ipn['mc_gross']));
        }
        
        if(!$tx['currency'] && isset($ipn['mc_currency'])) {
            $api_admin->invoice_transaction_update(array('id'=>$id, 'currency'=>$ipn['mc_currency']));
        }

        $invoice = $api_admin->invoice_get(array('id'=>$data['get']['bb_invoice_id']));
        $client_id = $invoice['client']['id'];

        switch ($ipn['txn_type']) {
            
            case 'web_accept':
            case 'subscr_payment':
                
                if($ipn['payment_status'] == 'Completed') {
                    $bd = array(
                        'id'            =>  $client_id,
                        'amount'        =>  $ipn['mc_gross'],
                        'description'   =>  'PayPal transaction '.$ipn['txn_id'],
                        'type'          =>  'PayPal',
                        'rel_id'        =>  $ipn['txn_id'],
                    );
                    if ($this->isIpnDuplicate($ipn)){
                        throw new Payment_Exception('IPN is duplicate');
                    }
                    $api_admin->client_balance_add_funds($bd);
                    if($tx['invoice_id']) {
                        $api_admin->invoice_pay_with_credits(array('id'=>$tx['invoice_id']));
                    }
                    $api_admin->invoice_batch_pay_with_credits(array('client_id'=>$client_id));
                }
                
                break;
            
            case 'subscr_signup':
                $sd = array(
                    'client_id'     =>  $client_id,
                    'gateway_id'    =>  $gateway_id,
                    'currency'      =>  $ipn['mc_currency'],
                    'sid'           =>  $ipn['subscr_id'],
                    'status'        =>  'active',
                    'period'        =>  str_replace(' ', '', $ipn['period3']),
                    'amount'        =>  $ipn['amount3'],
                    'rel_type'      =>  'invoice',
                    'rel_id'        =>  $invoice['id'],
                );
                $api_admin->invoice_subscription_create($sd);
                
                $t = array(
                    'id'            => $id, 
                    's_id'          => $sd['sid'],
                    's_period'      => $sd['period'],
                );
                $api_admin->invoice_transaction_update($t);
                break;

            case 'recurring_payment_suspended_due_to_max_failed_payment':
            case 'subscr_failed':
            case 'subscr_eot':
            case 'subscr_cancel':
                $s = $api_admin->invoice_subscription_get(array('sid'=>$ipn['subscr_id']));
                $api_admin->invoice_subscription_update(array('id'=>$s['id'], 'status'=>'canceled'));
                break;

            default:
                error_log('Unknown paypal transaction '.$id);
                break;
        }
        
        if(isset($ipn['payment_status']) && $ipn['payment_status'] == 'Refunded') {
            $refd = array(
                'id'    => $invoice['id'],
                'note'  => 'PayPal refund '.$ipn['parent_txn_id'],
            );
            $api_admin->invoice_refund($refd);
        }
        
        $d = array(
            'id'        => $id, 
            'error'     => '',
            'error_code'=> '',
            'status'    => 'processed',
            'updated_at'=> date('Y-m-d H:i:s'),
        );
        $api_admin->invoice_transaction_update($d);
    }

    private function serviceUrl()
    {
        if($this->config['test_mode']) {
            return 'https://www.sandbox.paypal.com/cgi-bin/webscr';
        } else {
            return 'https://www.paypal.com/cgi-bin/webscr';
        }
    }

    private function _isIpnValid($data)
    {
        // use http_raw_post_data instead of post due to encoding
        parse_str($data['http_raw_post_data'], $post);
		$req = 'cmd=_notify-validate';
		foreach ((array) $post as $key => $value) {
			$value = urlencode(stripslashes($value));
			$req .= "&$key=$value";
		}
		$url = $this->serviceUrl();
		$ret = $this->download($url, $req);
		return $ret == 'VERIFIED';
    }

    private function moneyFormat($amount, $currency)
    {
        //HUF currency do not accept decimal values
        if($currency == 'HUF') {
            return number_format($amount, 0);
        }
        return number_format($amount, 2, '.', '');
    }

	/**
	 * @param string $url
	 */
	private function download($url, $post_vars = false, $phd = array(), $contentType = 'application/x-www-form-urlencoded')
    {
		$post_contents = '';
		if ($post_vars) {
			if (is_array($post_vars)) {
				foreach($post_vars as $key => $val) {
					$post_contents .= ($post_contents ? '&' : '').urlencode($key).'='.urlencode($val);
				}
			} else {
				$post_contents = $post_vars;
			}
		}

		$uinf = parse_url($url);
		$host = $uinf['host'];
		$path = $uinf['path'];
		$path .= (isset($uinf['query']) && $uinf['query']) ? ('?'.$uinf['query']) : '';

		$headers = Array(
			($post_contents ? 'POST' : 'GET')." $path HTTP/1.1",
			"Host: $host",
            'Connection: Close',
            'User-Agent: BoxBilling'
		);
		if (!empty($phd)) {
			if (!is_array($phd)) {
				$headers[count($headers)] = $phd;
			} else {
				$next = count($headers);
				$count = count($phd);
				for ($i = 0; $i < $count; $i++) { $headers[$next + $i] = $phd[$i]; }
			}
		}
		if ($post_contents) {
			$headers[] = "Content-Type: $contentType";
			$headers[] = 'Content-Length: '.strlen($post_contents);
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 600);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		// Apply the XML to our curl call
		if ($post_contents) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_contents);
		}

		$data = curl_exec($ch);
		if (curl_errno($ch)) return false;
		curl_close($ch);

		return $data;
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
        $form .=  '<input class="bb-button bb-button-submit" type="submit" value="Pay with PayPal" id="payment_button"/>'. PHP_EOL;
        $form .=  '</form>' . PHP_EOL . PHP_EOL;

        if(isset($this->config['auto_redirect']) && $this->config['auto_redirect']) {
            $form .= sprintf('<h2>%s</h2>', __('Redirecting to PayPal.com'));
            $form .= "<script type='text/javascript'>$(document).ready(function(){    document.getElementById('payment_button').style.display = 'none';    document.forms['payment_form'].submit();});</script>";
        }

        return $form;
    }

    /**
     * @param string $txn_id
     */
    public function isIpnDuplicate(array $ipn)
    {
        $sql = 'SELECT id
                FROM transaction
                WHERE txn_id = :transaction_id
                  AND txn_status = :transaction_status
                  AND type = :transaction_type
                  AND amount = :transaction_amount
                LIMIT 2';

        $bindings = array(
            ':transaction_id' => $ipn['txn_id'],
            ':transaction_status' => $ipn['payment_status'],
            ':transaction_type' => $ipn['txn_type'],
            ':transaction_amount' => $ipn['mc_gross'],
        );

        $rows = $this->di['db']->getAll($sql, $bindings);
        if (count($rows) > 1){
            return true;
        }


        return false;
    }

    public function getInvoiceTitle(array $invoice)
    {
        $p = array(
            ':id'=>sprintf('%05s', $invoice['nr']),
            ':serie'=>$invoice['serie'],
            ':title'=>$invoice['lines'][0]['title']
        );
        return __('Payment for invoice :serie:id [:title]', $p);
    }

    public function getSubscriptionFields(array $invoice)
    {
        $data = array();
        $subs = $invoice['subscription'];

        $data['item_name']          = $this->getInvoiceTitle($invoice);
        $data['item_number']        = $invoice['nr'];
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
        $buyer = $invoice['buyer'];
        $data['address1']			= $buyer['address'];
        $data['city']				= $buyer['city'];
        $data['email']				= $buyer['email'];
        $data['first_name']			= $buyer['first_name'];
        $data['last_name']			= $buyer['last_name'];
        $data['zip']				= $buyer['zip'];
        $data['state']				= $buyer['state'];
        $data['bn']                             = "BoxBilling_SP";
        return $data;
    }

    public function getOneTimePaymentFields(array $invoice)
    {
        $data = array();
        $data['item_name']          = $this->getInvoiceTitle($invoice);
        $data['item_number']        = $invoice['nr'];
        $data['no_shipping']        = '1';
        $data['no_note']            = '1';
        $data['currency_code']      = $invoice['currency'];
        $data['rm']                 = '2';
        $data['return']             = $this->config['return_url'];
        $data['cancel_return']      = $this->config['cancel_url'];
        $data['notify_url']         = $this->config['notify_url'];
        $data['business']           = $this->config['email'];
        $data['cmd']                = '_xclick';
        $data['amount']             = $this->moneyFormat($invoice['subtotal'], $invoice['currency']);
        $data['tax']                = $this->moneyFormat($invoice['tax'], $invoice['currency']);
        $data['bn']                 = "BoxBilling_SP";
        $data['charset']            = "utf-8";
        return $data;
    }
}
