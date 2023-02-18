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

abstract class Payment_AdapterAbstract
{
    const TYPE_HTML         	= 'html';
    const TYPE_FORM         	= 'form';
    const TYPE_API          	= 'api';
    const MODE_TEST = 'test', MODE_PROD = 'prod';
    const VERSION_500 = '5.0.0', VERSION_510 = '5.1.0';
	const SIGNMETHOD_RSA = '01', SIGNMETHOD_SHA256 = '11', SIGNMETHOD_SM3 = '12';
	const CHANNELTYPE_PC = '07', CHANNELTYPE_MOBILE = '08';
	const
		TXNTYPE_QUERY   = '00', //查询交易
		TXNTYPE_CONSUME = '01', //消费
		TXNTYPE_PREAUTH = '02', //预授权
		TXNTYPE_PREAUTHFINISH = '03', //预授权完成
		TXNTYPE_REFUND  = '04', //退货
		TXNTYPE_LOAD    = '05', //圈存
		TXNTYPE_DIRECTDEBIT = '11', //代收
		TXNTYPE_DIRECTDEPOSIT = '12', //代付
		TXNTYPE_PAYBILL = '13', //账单支付
		TXNTYPE_TRANSFER = '14', //转账
		TXNTYPE_BATCHDEBIT = '21', //批量交易
		TXNTYPE_QUERYBATCHDEBIT = '22', //批量查询
		TXNTYPE_CONSUMEUNDO = '31', //消费撤销
		TXNTYPE_PREAUTHUNDO = '32', //预授权撤销
		TXNTYPE_PREAUTHFINISHUNDO = '33', //预授权完成撤销
		TXNTYPE_QUERYBALANCE = '71',//余额查询
		TXNTYPE_AUTHORIZE = '72', //实名认证-建立绑定关系
		TXNTYPE_QUERYBILL = '73', //账单查询
		TXNTYPE_UNAUTHORIZE = '74', //解除绑定关系
		TXNTYPE_QUERYBIND = '75', //查询绑定关系
		TXNTYPE_FILEDOWNLOAD = '76',
		TXNTYPE_AUTHENTICATE = '77', //发送短信验证码交易
		TXNTYPE_DIRECTOPEN = '79',
		TXNTYPE_QUERYOPEN = '78', //开通查询交易
		TXNTYPE_APPLYTOKEN = '79', //开通交易
		TXNTYPE_DELETETOKEN = '74',
		TXNTYPE_UPDATETOKEN = '79',
		TXNTYPE_ICCARD = '94', //IC卡脚本通知
		TXNTYPE_UPDATEPUBLICKEY = '95', //查询更新加密公钥证书
		TXNTYPE_REVERSE = '99';//冲正
	const
		BIZTYPE_B2C     = '000201', //B2C网关支付
		BIZTYPE_B2B     = '000202', //B2B
		BIZTYPE_DIRECT  = '000301', //认证支付（无跳转标准版）
		BIZTYPE_GRADE   = '000302', //评级支付
		BIZTYPE_DIRECTDEPOSIT   = '000401', //代付
		BIZTYPE_DIRECTDEBIT     = '000501', //代收
		BIZTYPE_CHARGE  = '000601', //账单支付
		BIZTYPE_AQUIRE  = '000801', //跨行收单
		BIZTYPE_APPLEPAY  = '000802', //ApplePay
		BIZTYPE_BIND    = '000901', //绑定支付
		BIZTYPE_TOKEN   = '000902', //Token支付（无跳转token版）
		BIZTYPE_ORDER   = '001001', //订购
		BIZTYPE_DEFAULT = '000000'; //默认值
	const
		ACCESSTYPE_MERCHANT = '0', //商户直连接入
		ACCESSTYPE_ACQUIRER = '1', //收单机构接入
		ACCESSTYPE_PLATFORM = '2'; //平台商户接入
	const RESPCODE_SUCCESS = '00', RESPCODE_SIGNATURE_VERIFICATION_FAIL = '11';
	const SMSTYPE_OPEN = '00', SMSTYPE_PAY = '02', SMSTYPE_PREAUTH = '04', SMSTYPE_OTHER = '05';

    /**
     * Adapter settings
     *
     * @var array
     */
    protected $_config = array();
    
    /**
     * Response text for notify_url
     * This value is set after IPN is received and validated
     * 
     * @var string 
     */
    protected $output = NULL;

    /**
     * Are we in test mode?
     *
     * @var boolean
     */
    public $testMode = false;

    /**
     * Log object
     * 
     * @var Box_Log
     */
    private $_log = false;

    /**
     * Constructs a new Payment_Adapter object
     * 
     * @param array $config The configuration for the payment adapter as configured within the admin panel
     * 
     * @throws Payment_Exception
     */
    public function __construct($config)
    {
        $this->_config = $config;

        /**
         * Redirect client after successful payment, usually to invoice
         */
        if(!$this->getParam('return_url')) {
            throw new Payment_Exception('Return URL for payment gateway was not set', array(), 6001);
        }

        /**
         * URL to redirect client if payment process was canceled
         */
        if(!$this->getParam('cancel_url')) {
            throw new Payment_Exception('Cancel URL for payment gateway was not set', array(), 6002);
        }

        /**
         * IPN notification url. Payment gateway posts data to this URL
         * to inform FOSSBilling about payment
         */
        if(!$this->getParam('notify_url')) {
            throw new Payment_Exception('IPN Notification URL for payment gateway was not set', array(), 6003);
        }

        /**
         * If payment gateway has only one callback url, this url should be
         * used. It is equal to return_url + notify_url combined.
         * Client gets redirected to redirect_url, POST, GET data are considered
         * as IPN data, and client gets redirected to invoice page.
         */
        if(!$this->getParam('redirect_url')) {
            throw new Payment_Exception('IPN redirect URL for payment gateway was not set', array(), 6004);
        }

        $this->init();
    }

	/**
	 * Return gateway configuration options
	 *
	 * @return array
	*/
    public static function getConfig()
    {
        throw new Payment_Exception('Payment adapter class did not implement configuration options method', array(), 749);
    }
    
    /**
     * Return payment gateway type (TYPE_HTML, TYPE_FORM, TYPE_API)
     * 
     * @return string
     */
    public function getType()
    {
        return Payment_AdapterAbstract::TYPE_FORM;
    }
    
    /**
     * Payment gateway endpoint
     * 
     * @return string
     */
    public function getServiceUrl()
    {
		return '';
    }

    /**
     * Returns invoice id from callback IPN
     * 
     * This method is called before transaction processing to determine
     * invoice id from IPN.
     * 
     * @param array $data - Contains $_GET, $_POST, $HTTP_RAW_POST_DATA 
     * (or file_get_contents("php://input")) in format like: 
     * $data = array(
     *  'get'=>$_GET, 
     *  'post'=>$_POST, 
     *  'http_raw_post_data'=>$HTTP_RAW_POST_DATA
     * );
     * 
     * @return int - invoice id
     */
    public function getInvoiceId($data)
    {
        return isset($data['get']['bb_invoice_id']) ? (int)$data['get']['bb_invoice_id'] : NULL;
    }

    public function setLog(Box_Log $log)
    {
        $this->_log = $log;
    }

    public function getLog()
    {
        $log = $this->_log;
        if(!$log instanceof Box_Log) {
            $log = new Box_Log();
        }
        return $log;
    }

    /**
     * Get config parameter
     * 
     * @param string $param the parameter name to retrieve from the config
     * 
     * @return mixed|null The associated config parameter or null if it's not defined
     */
    public function getParam($param)
    {
        return isset($this->_config[$param]) ? $this->_config[$param] : NULL;
    }

    /**
     * Convert money amount to Gateway money format
     * 
     * @param float The ammount
     * 
     * @param string The currency (unused currently)
     * 
     * @return string The formatted money string
     */
    public function moneyFormat($amount, $currency = null)
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Set test mode
     *
     * @param none
     * 
     * @return Payment_AdapterAbstract
     */
    public function setTestMode($bool)
    {
        $this->testMode = (bool)$bool;
        return $this;
    }

    public function getTestMode()
    {
        return $this->testMode;
    }
    
    /**
     * Set custom response text to be printed when IPN is received
     * Used only by payment gateways who care about notify_url response
     * 
     * @param string
     * 
     * @param string $response
     */
    public function setOutput($response)
    {
        $this->output = $response;
    }

    public function getOutput()
    {
        return $this->output;
    }

}
