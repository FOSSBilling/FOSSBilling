<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */
 class Payment_Adapter_ChinaUnionpay extends Payment_AdapterAbstract2
 {
     public function init()
     {
         if(!$this->getParam('partner')) {
             throw new Payment_Exception('Payment gateway "ChinaUnionpay" is not configured properly. Please update configuration parameter "Partner ID" at "Configuration -> Payment gateways".');
         }

         if (!$this->getParam('security_code')) {
         	throw new Payment_Exception('Payment gateway "ChinaUnionpay" is not configured properly. Please update configuration parameter "Security Code" at "Configuration -> Payment gateways".');
         }

         if (!$this->getParam('seller_email')) {
         	throw new Payment_Exception('Payment gateway "ChinaUnionpay" is not configured properly. Please update configuration parameter "Seller email" at "Configuration -> Payment gateways".');
         }

         $this->_config['charset']       = 'utf-8';
     }

class Payment_Adapter_ChinaUnionpay
{
    private $config = array();
    protected $di;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * @param Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    public static function getConfig()
    {
        return array(
            'can_load_in_iframe'   =>  true,
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'       =>  false,
            'description'     =>  'China UnionPay Corporation is a joint-stock financial services company headquartered in Shanghai, China. It mainly provides inter-bank payment and settlement services. Its online interbank transaction clearing system was unique and monopolistic in Mainland China.',
            'form'  => array(
                'single' => array('textarea', array(
                            'label' => 'Enter your text for single payment information',
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
            return 'https://gateway.test.95516.com/gateway/api/frontTransReq.do';
        }
    return 'https://gateway.95516.com/gateway/api/frontTransReq.do';
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

        if(!isset($service)){
            throw new Payment_Exception("Error getting service for contract");
        }

        $parameter = array(
            'version'           => 5.1.0
            'encoding'          =>  UTF-8
            'service'           => $service,
            'defaultPayType'    => '0001',
            'merId'             => $this->getParam('partner'),
            '_input_charset'    => $this->getParam('charset'),
            'backUrl'           => $this->getParam('notify_url'),
            'return_url'        => $this->getParam('thankyou_url'),
            'subject'           => $invoice->getTitle(),
            'orderId'           => $invoice->getId(),
            'txnAmt'            => $invoice->getTotalWithTax(),
            'txnType'           => UnionPay::TXNTYPE_CONSUME,
	     'txnSubType'        => '01',
	     'currencyCode'      => $this->config['currencyCode'],
            'quantity'          => 1,
            'txnType'           => 1,
            'txnTime'           => date('YmdHis'),$ext,
        );

        ksort($params);
        reset($params);
        $data = $params;
        $params['signature'] = $this->sign($params);
        $data['sign_type'] = 'MD5';

        return $data;
  }

  public function recurrentPayment(Payment_Invoice $invoice)
  {
    throw new Payment_Exception('ChinaUnionpay does not support recurrent payments');
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

    /**
     * Generate payment text
     *
     * @param Api_Admin $api_admin
     * @param int $invoice_id
     * @param bool $subscription
     *
     * @since FOSSBilling v2.9.15
     *
     * @return string - html form with auto submit javascript
     */
    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoiceModel = $this->di['db']->load('Invoice', $invoice_id);
        $invoiceService = $this->di['mod_service']("Invoice");
        $invoice = $invoiceService->toApiArray($invoiceModel, true);

        $vars = array(
            '_client_id'    => $invoice['client']['id'],
            'invoice'   =>  $invoice,
            '_tpl'      =>  $subscription ? (isset($this->config['recurrent']) ? $this->config['recurrent'] : '"Custom" payment adapter is not fully configured.') : (isset($this->config['single']) ? $this->config['single'] : '"Custom" payment adapter is not fully configured.'),
        );
        $systemService = $this->di['mod_service']('System');
        return $systemService->renderString($vars['_tpl'], true, $vars);
    }

    public function process($tx)
    {
        //do processing
        return true;
    }
}

/**
	 * Refund Functioon
	 * @param $orderId
	 * @param $origQryId
	 * @param $refundAmt
	 * @param array $ext
	 * @return mixed
	 */
	public function refund($orderId, $origQryId, $refundAmt, $ext = []) {
		$params = array_merge($this->commonParams(),[
			'txnType' => UnionPay::TXNTYPE_REFUND,
			'txnSubType' => '00',
			'orderId' => $orderId,
			'origQryId' => $origQryId,
			'txnAmt' => $refundAmt,
			'txnTime' => date('YmdHis'),
		],$ext);
		$params['signature'] = $this->sign($params);
		return $this->post($this->backTransUrl, $params);
	}