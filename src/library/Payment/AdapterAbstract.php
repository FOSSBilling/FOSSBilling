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

abstract class Payment_AdapterAbstract
{
    const TYPE_HTML         	= 'html';
    const TYPE_FORM         	= 'form';
    const TYPE_API          	= 'api';

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
     * Are we in test mode ?
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
     *
     * @param array $config
     */
    public function __construct($config)
    {
        $this->_config = $config;

        /**
         * Redirect client after successfull payment, usually to invoice
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
         * to inform BoxBilling about payment
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
     * Return payment gateway type
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
     * @param string $param
     */
    public function getParam($param)
    {
        return isset($this->_config[$param]) ? $this->_config[$param] : NULL;
    }

    /**
     * Convert money amount to Gateway money format
     * 
     * @param float
     * @param string
     * @return string
     */
    public function moneyFormat($amount, $currency = null)
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Set test mode
     *
     * @param none
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
