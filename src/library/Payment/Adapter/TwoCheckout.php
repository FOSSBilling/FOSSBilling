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

class Payment_Adapter_TwoCheckout implements \Box\InjectionAwareInterface
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
        if(!$config['vendor_nr']) {
            throw new Payment_Exception('Payment gateway "2Checkout" is not configured properly. Please update configuration parameter "Vendor account number" at "Configuration -> Payments".');
        }

        if(!$config['secret']) {
            throw new Payment_Exception('Payment gateway "2Checkout" is not configured properly. Please update configuration parameter "Secret word" at "Configuration -> Payments".');
        }
        
        $this->config = $config;
    }
    
    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  true,
            'description'     =>  'Allows to start accepting payments by 2Checkout. Redirect option must be configured propertly. Login to your 2checkout account and navigate to <i>Account -> Site Management -> Direct Return</i> section. Select <i>Header Redirect (Your URL)</i> and click "Save changes".',
            'form'  => array(
                'vendor_nr' => array('text', array(
                            'label' => '2CO Account #', 
                            'description' => '2Checkout account number is number with which you login to 2CO account',
                    ),
                 ),
                'secret' => array('password', array(
                            'label' => 'Secret word', 
                            'description' => 'To set up the secret word please log in to your 2CO account, click on the “Account” tab, then click on “Site Management” subcategory. On the “Site Management” page you will enter the Secret Word in the field provided under Direct Return. After you have entered your Secret Word click the blue “Save Changes” button at the bottom of the page.',
                    ),
                 ),
                'single_page' => array('radio', array(
                            'multiOptions' => array('1'=>'Single page', '0'=>'Multi-page'),
                            'label' => 'Checkout page type',
                    ),
                 ),
            ),
        );
    }
    
    /**
     * Generate payment text
     * 
     * @param Api_Admin $api_admin
     * @param int $invoice_id
     * @param bool $subscription
     * 
     * @since BoxBilling v2.9.11
     * 
     * @return string - html form with auto submit javascript
     */
    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoice = $api_admin->invoice_get(array('id'=>$invoice_id));
        
        if($subscription) {
            $data = $this->_recurrentPayment($invoice);
        } else {
            $data = $this->_singlePayment($invoice);
        }
        
        if($this->config['test_mode']) {
            $data['demo'] = 'Y';
        }
        
        if((bool)$this->config['single_page'] === false) {
            $url = 'https://www.2checkout.com/checkout/purchase';
        } else {
            $url = 'https://www.2checkout.com/checkout/spurchase';
        }
        
        return $this->_generateForm($url, $data);
    }
    
    /**
     * Process transaction received from payment gateway
     * 
     * @since BoxBilling v2.9.11
     * 
     * @param Api_Admin $api_admin
     * @param int $id - transaction id to process
     * @param int $gateway_id - payment gateway id on BoxBilling
     * 
     * @return mixed
     */
    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
        $tx = $api_admin->invoice_transaction_get(array('id'=>$id));
        $ipn = array_merge($data['get'], $data['post']);
        
        if(APPLICATION_ENV != 'testing' && !$this->_isIpnValid($ipn)) {
            throw new Payment_Exception('2Checkout IPN is not valid');
        }

        $api_admin->invoice_transaction_update(array('id' => $id, 'type' => 'ORDER CREATED'));

        $invoice_id = null;
        if(isset($tx['invoice_id'])) {
            $invoice_id = $tx['invoice_id'];
        } elseif(isset($ipn['bb_invoice_id'])) {
            $invoice_id = $ipn['bb_invoice_id'];
            $api_admin->invoice_transaction_update(array('id'=>$id, 'invoice_id'=>$invoice_id));
        }
        
        if(!$invoice_id) {
            throw new Payment_Exception('Invoice id could not be determined for this transaction');
        }
        
        $invoice = $api_admin->invoice_get(array('id'=>$invoice_id));
        $client_id = $invoice['client']['id'];
        
        $tx_data = array(
            'id'            =>  $id,
            'status'        =>  'pending',
        );
        
        if(empty($tx['txn_id']) && isset($ipn['order_number'])) {
            $tx_data['txn_id'] = $ipn['order_number'];
        }
        
        if(empty($tx['amount']) && isset($ipn['total'])) {
            $tx_data['amount'] = $ipn['total'];
        }
        
        if(empty($tx['currency'])) {
            $tx_data['currency'] = $invoice['currency'];
        }
        
        $api_admin->invoice_transaction_update($tx_data);
        
        if(isset($ipn['credit_card_processed']) && $ipn['credit_card_processed'] == 'Y') {
            $bd = array(
                'id'            =>  $client_id,
                'amount'        =>  $ipn['total'],
                'description'   =>  '2Checkout sale id: '.$ipn['order_number'],
                'type'          =>  '2Checkout',
                'rel_id'        =>  $ipn['order_number'],
            );

            if ($this->isIpnDuplicate($ipn)){
                throw new Payment_Exception('IPN is duplicate');
            }

            $api_admin->client_balance_add_funds($bd);
            
            $tx_data['txn_status']  = 'complete';
            $tx_data['status']      = 'complete';
            $api_admin->invoice_transaction_update($tx_data);
            
            $api_admin->invoice_batch_pay_with_credits(array('client_id'=>$client_id));
        }
        
        if (isset($ipn['subscription']) && $ipn['subscription'] == 1) {
            
            $recurrence = '1M';
            if(isset($ipn['li_0_recurrence'])) {
                switch ($ipn['li_0_recurrence']) {
                    case '3 Year':
                        $recurrence = '3Y';
                        break;
                    
                    case '2 Year':
                        $recurrence = '2Y';
                        break;
                    
                    case '1 Year':
                        $recurrence = '1Y';
                        break;
                    
                    case '6 Month':
                        $recurrence = '6M';
                        break;
                    
                    case '3 Month':
                        $recurrence = '3M';
                        break;
                    
                    case '2 Month':
                        $recurrence = '2M';
                        break;
                    
                    case '3 Week':
                        $recurrence = '3W';
                        break;
                    
                    case '2 Week':
                        $recurrence = '2W';
                        break;
                    
                    case '1 Week':
                        $recurrence = '1W';
                        break;
                    
                    case '1 Month':
                    default:
                        $recurrence = '1M';
                        break;
                }
            }
            
            $sd = array(
                'client_id'     =>  $client_id,
                'gateway_id'    =>  $gateway_id,
                'currency'      =>  $invoice['currency'],
                'sid'           =>  $ipn["order_number"],
                'status'        =>  'active',
                'period'        =>  $recurrence,
                'amount'        =>  $ipn['total'],
                'rel_type'      =>  'invoice',
                'rel_id'        =>  $invoice['id'],
            );
            $api_admin->invoice_subscription_create($sd);
        }
    }
    
    private function _isIpnValid($ipn)
    {
        $md5_hash       = null;
        $check_key      = ' ';
        $secret         = $this->config['secret'];
        $vendorNumber   = $this->config['vendor_nr'];
        
        // The following code would be applicable to orders placed using our Plug and Play cart and our proprietary third party set of parameters.
        if(isset($ipn['order_number']) && isset($ipn['total']) && isset($ipn['key'])) {
            $md5_hash  = $ipn["key"];
            $check_key = strtoupper(md5($secret . $vendorNumber . $ipn["order_number"] . $ipn["total"]));

        // The following code would then be applicable to orders placed using the Authorize.net parameter set.
        } elseif(isset($ipn['order_number']) && isset($ipn['total']) && isset($ipn['key'])) {
            $md5_hash  = $ipn["key"];
            $check_key = strtoupper(md5($secret.$ipn['order_number'].$ipn["total"]));
        }
        
        error_log(sprintf('Returned MD5 Hash %s should be equal to %s', $md5_hash, $check_key));
        return ($md5_hash == $check_key);
    }
    
    private function _singlePayment($invoice)
    {
        $b = $invoice['buyer'];
        
        $data = array();
        $data['sid']                = $this->config['vendor_nr'];
        $data['mode']               = '2CO';
        
        foreach($invoice['lines'] as $i=>$item) {
            $data['li_'.$i.'_type']         = 'product';
            $data['li_'.$i.'_name']         = $item['title'];
            $data['li_'.$i.'_product_id']   = $item['id'];
            $data['li_'.$i.'_price']        = $item['price'];
            $data['li_'.$i.'_quantity']     = $item['quantity'];
        }
        
        $data['card_holder_name']   = $b['first_name'].' '.$b['last_name'];
        $data['phone']              = $b['phone_cc'].$b['phone'];
        $data['email']              = $b['email'];
        $data['street_address']     = $b['address'];
        $data['city']               = $b['city'];
        $data['state']              = $b['state'];
        $data['zip']                = $b['zip'];
        $data['country']            = $b['country'];
       
        $data['merchant_order_id']  = $invoice['id'];
        
        $data['x_receipt_link_url'] = $this->config['redirect_url'];

        return $data;
    }

    private function _recurrentPayment($invoice)
    {
        $b    = $invoice['buyer'];
    	$subs = $invoice['subscription'];
        
        $data = array();
        $data['sid']                = $this->config['vendor_nr'];
        $data['mode']               = '2CO';

        switch ($subs['unit']) {
            case 'W':
                $unit = 'Week';
                break;
            
            case 'Y':
                $unit = 'Year';
                break;

            case 'M':
            default:
                $unit = 'Month';
                break;
        }
        
        foreach($invoice['lines'] as $i => $item) {
        	$data['li_' . $i . '_type']			= 'product';
        	$data['li_' . $i . '_name'] 		= $item['title'];
        	$data['li_' . $i . '_quantity']		= $item['quantity'];
        	$data['li_' . $i . '_tangible']		= 'N';
        	$data['li_' . $i . '_description']	= '';
        	$data['li_' . $i . '_recurrence']	= $subs['cycle'] . ' ' . $unit;
            $data['li_' . $i . '_price']		= $invoice['total'];
        }

        $data['card_holder_name']   = $b['first_name'].' '.$b['last_name'];
        $data['phone']              = $b['phone_cc'].$b['phone'];
        $data['email']              = $b['email'];
        $data['street_address']     = $b['address'];
        $data['city']               = $b['city'];
        $data['state']              = $b['state'];
        $data['zip']                = $b['zip'];
        $data['country']            = $b['country'];
       
        $data['merchant_order_id']  = $invoice['id'];
        
        $data['x_receipt_link_url'] = $this->config['redirect_url'];

        return $data;
    }
    
    /**
     * @param string $url
     */
    private function _generateForm($url, $data, $method = 'post')
    {
        $form = '';
        $form .= '<form name="payment_form" action="'.$url.'" method="'.$method.'">' . PHP_EOL;
        foreach($data as $key => $value) {
            $form .= sprintf('<input type="hidden" name="%s" value="%s" />', $key, $value) . PHP_EOL;
        }
        $form .=  '<input class="bb-button bb-button-submit" type="submit" value="Please click here to continue if this page does not redirect automatically in 5 seconds" id="payment_button"/>'. PHP_EOL;
        $form .=  '</form>' . PHP_EOL . PHP_EOL;

        if(isset($this->config['auto_redirect']) && $this->config['auto_redirect']) {
            $form .= sprintf('<h2>%s</h2>', __('Redirecting to 2Checkout.com'));
            $form .=  "<script type='text/javascript'>$(document).ready(function(){    document.getElementById('payment_button').style.display = 'none';    document.forms['payment_form'].submit();});</script>";
        }

        return $form;
    }

    public function isIpnDuplicate(array $ipn)
    {
        $sql = 'SELECT id
                FROM transaction
                WHERE txn_id = :transaction_id
                  AND type = :transaction_type
                  AND amount = :transaction_amount
                LIMIT 2';

        $bindings = array(
            ':transaction_id' => $ipn['order_number'],
            ':transaction_type' => $ipn['message_type'],
            ':transaction_amount' => $ipn['total'],
        );

        $rows = $this->di['db']->getAll($sql, $bindings);
        if (count($rows) > 1){
            return true;
        }

        return false;
    }
}