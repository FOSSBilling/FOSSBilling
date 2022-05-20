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

class Payment_Adapter_AliPay extends Payment_AdapterAbstract
{
    public function init()
    {
        if(!$this->getParam('partner')) {
            throw new Payment_Exception('Payment gateway "AliPay" is not configured properly. Please update configuration parameter "Partner ID" at "Configuration -> Payment gateways".');
        }
        
        if (!$this->getParam('security_code')) {
        	throw new Payment_Exception('Payment gateway "AliPay" is not configured properly. Please update configuration parameter "Security Code" at "Configuration -> Payment gateways".');
        }

        if (!$this->getParam('seller_email')) {
        	throw new Payment_Exception('Payment gateway "AliPay" is not configured properly. Please update configuration parameter "Seller email" at "Configuration -> Payment gateways".');
        }
        
        $this->_config['charset']       = 'utf-8';
    }
    
    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'    =>  true,
            'supports_subscriptions'        =>  false,
            'description'                   =>  'Clients will be redirected to Alipay to make payment.',
            'form'  => array(
                'partner' => array('text', array(
                            'label' => 'AliPay Partner ID. After signing contract online successfully, Alipay provides the partner id',
                    ),
                 ),
                 'security_code' => array('text', array(
                 			'label' => 'AliPay security code. After signing contract online successfully, Alipay provides the 32bits security code',
                 	),
                 ),
                'seller_email' => array('text', array(
                            'label' => 'AliPay seller email. Your Alipay account',
                    ),
                 ),
                'type' => array('select', array(
                            'multiOptions' => array(
                                '1' => 'Type 1 - receive money at once. You need to be registered company in china',
                                '2' => 'Type 2 - receive money after buyer confirm it'
                            ),
                            'label' => 'Contract type. Type 1: is mainly for virtual goods. So seller can receive money at once, but it requires seller is an register company; Type 2 is mainly for real goods, when buyer receive goods, then buyer check the goods is right and confirm to pay! During the process, the money is held by Alipay, if buyer dont claim to get money back, whether confirm or not, the money will transfer to seller after certain days.',
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
            return 'https://www.alipay.com/cooperate/gateway.do';
        }
		return 'https://www.alipay.com/cooperate/gateway.do';
    }

    public function getInvoiceId($data)
    {
        $id = parent::getInvoiceId($data);
        if(is_null($id)) {
            $id = isset($data['post']['out_trade_no']) ? (int)$data['post']['out_trade_no'] : NULL;
        }
        return $id;
    }

	public function singlePayment(Payment_Invoice $invoice) 
	{
		$client = $invoice->getBuyer();

        $contract = $this->getParam('type');
        switch ($contract){
            case '0':
                $service = 'trade_create_by_buyer';
                break;
            case '1':
                $service = 'create_direct_pay_by_user';
                break;
            case '2':
                $service = 'create_partner_trade_by_buyer';
                break;
        }

        $parameter = array(
            'service'           => $service,
            'partner'           => $this->getParam('partner'),
            '_input_charset'    => $this->getParam('charset'),
            'notify_url'        => $this->getParam('notify_url'),
            'return_url'        => $this->getParam('thankyou_url'),

            'subject'           => $invoice->getTitle(),
            'out_trade_no'      => $invoice->getId(),
            'price'             => $invoice->getTotalWithTax(),
            'quantity'          => 1,
            'payment_type'      => 1,
            
            'logistics_type'    => 'EXPRESS',
            'logistics_fee'     => 0,
            'logistics_payment' => 'BUYER_PAY_AFTER_RECEIVE',
            
            'seller_email'      => $this->getParam('seller_email'),
        );

        ksort($parameter);
        reset($parameter);
        $data = $parameter;
        $data['sign'] = $this->_generateSignature($parameter);
        $data['sign_type'] = 'MD5';

        return $data;
	}

	public function recurrentPayment(Payment_Invoice $invoice) 
	{
		throw new Payment_Exception('AliPay does not support recurrent payments');
	}

	public function getTransaction($data, Payment_Invoice $invoice) 
	{
		$ipn = $data['post'];
        
        $uniqid = md5($ipn['trade_no'].$ipn['trade_status']);
        
		$tx = new Payment_Transaction();
		$tx->setId($uniqid);
		$tx->setAmount($ipn['total_fee']);
		$tx->setCurrency($invoice->getCurrency());
        
        $contract = $this->getParam('type');
        if($contract == '1') {
            switch ($ipn['trade_status']) {
                case 'TRADE_SUCCESS':
                case 'TRADE_FINISHED':
                    $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);
                    $tx->setStatus(Payment_Transaction::STATUS_COMPLETE);
                    break;

                default:
                    $tx->setStatus($ipn['trade_status']);
                    break;
            }
        }
        
        if($contract == '2') {
            switch ($ipn['trade_status']) {
                case 'WAIT_SELLER_SEND_GOODS':
                    $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);
                    $tx->setStatus(Payment_Transaction::STATUS_COMPLETE);
                    break;

                default:
                    $tx->setStatus($ipn['trade_status']);
                    break;
            }
        }
        
		return $tx;
	}

    public function isIpnValid($data, Payment_Invoice $invoice)
    {
        $ipn = $data['post'];

        ksort($ipn);
        reset($ipn);

        $sign = '';
        foreach ($ipn AS $key=>$val)
        {
            if ($key != 'sign' && $key != 'sign_type' && $key != 'code' && $key !='bb_gateway_id' && $key != 'bb_invoice_id')
            {
                $sign .= "$key=$val&";
            }
        }

        $sign = substr($sign, 0, -1) . $this->getParam('security_code');
        return (md5($sign) == $ipn['sign']);
    }

    private function _generateSignature(array $parameter)
    {
        $sign  = '';
        foreach ($parameter AS $key => $val)
        {
            $sign  .= "$key=$val&";
        }
        $sign  = substr($sign, 0, -1) . $this->getParam('security_code');
        return md5($sign);
    }
}