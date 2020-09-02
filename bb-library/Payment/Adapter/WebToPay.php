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

class Payment_Adapter_WebToPay extends Payment_AdapterAbstract
{
    public function init()
    {
        if(!$this->getParam('projectid')) {
            throw new Payment_Exception('Payment gateway "WebToPay" is not configured properly. Please update configuration parameter "Project ID" at "Configuration -> Payments".');
        }

        if(!$this->getParam('sign_password')) {
            throw new Payment_Exception('Payment gateway "WebToPay" is not configured properly. Please update configuration parameter "Sign Password" at "Configuration -> Payments".');
        }
    }

	/**
	 * Return gateway type
	 *
	 * @return string
	*/
    public function getType()
    {
        return Payment_AdapterAbstract::TYPE_FORM;
    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  false,
            'description'     =>  'WebToPay gateway. More information at www.webtopay.com',
            'form'  => array(
                'projectid' => array('text', array(
                            'label' => 'Project ID',
                            'value' => '',
                    ),
                 ),
                'sign_password' => array('password', array(
                            'label' => 'Sign Password',
                            'value' => '',
                    ),
                 ),
            ),
        );
    }

	/**
	 * Return service call url
	 *
	 * @return string
	*/
    public function getServiceUrl()
    {
        return WebToPay::PAY_URL;
    }

	/**
	 * Init single payment call to webservice
	 * Invoice id is passed via notify_url
     *
	 * @return string
	*/
    public function singlePayment(Payment_Invoice $invoice)
    {
        $buyer = $invoice->getBuyer();
        return WebToPay::buildRequest(array(
                'projectid'     => $this->getParam('projectid'),
                'sign_password' => $this->getParam('sign_password'),
                'orderid'       => $invoice->getNumber(),
                'amount'        => $this->moneyFormat($invoice->getTotalWithTax()),
                'currency'      => $invoice->getCurrency(),
                'accepturl'     => $this->getParam('return_url'),
                'cancelurl'     => $this->getParam('cancel_url'),
                'callbackurl'   => $this->getParam('notify_url'),
                'paytext'       => $invoice->getTitle(),

                'p_firstname'   => $buyer->getFirstName(),
                'p_lastname'    => $buyer->getLastName(),
                'p_email'       => $buyer->getEmail(),
                'p_street'      => $buyer->getAddress(),
                'p_city'        => $buyer->getCity(),
                'p_state'       => $buyer->getState(),
                'p_zip'         => $buyer->getZip(),
                'p_countrycode' => $buyer->getCountry(),

                'lang'          => 'ENG',
                'test'          => $this->testMode,
            ));
    }

	/**
	 * Init recurrent payment call to webservice
	 *
	 * @return mixed
	*/
    public function recurrentPayment(Payment_Invoice $invoice)
    {
        throw new Payment_Exception('WebToPay payment gateway do not support recurrent payments');
    }

    /**
     * Handle IPN and return response object
     * @return Payment_Transaction
     */
    public function getTransaction($data, Payment_Invoice $invoice)
    {
        $ipn = $data['get'];

        $tx = new Payment_Transaction();
        $tx->setId($ipn['wp_requestid']);
        $tx->setAmount($ipn['wp_payamount'] / 100);
        $tx->setCurrency($ipn['wp_paycurrency']);
        $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);

        if($ipn['wp_status'] == 1) {
            $tx->setStatus(Payment_Transaction::STATUS_COMPLETE);
        }

        if($ipn['wp_status'] == 2) {
            $tx->setStatus(Payment_Transaction::STATUS_PENDING);
        }

        return $tx;
    }

    public function isIpnValid($data, Payment_Invoice $invoice)
    {
        $ipn = $data['get'];
        try{
            WebToPay::checkResponse($ipn, array(
                'projectid'     => $this->getParam('projectid'),
                'sign_password' => $this->getParam('sign_password'),
            ));
            $this->setOutput('OK');
            return true;
        } catch (WebToPayException $e) {
            error_log($e->getMessage());
            $this->setOutput('ERR');
            return false;
        }
    }

    /**
     * @param double $amount
     */
    public function moneyFormat($amount, $currency = null)
    {
        return $amount * 100;
    }
}

/**
 * PHP Library for WebToPay provided services.
 * Copyright (C) 2010  http://www.webtopay.com/
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Lesser General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    WebToPay
 * @author     EVP International
 * @license    http://www.gnu.org/licenses/lgpl.html
 * @version    1.5
 * @link       http://www.webtopay.com/
 */
class WebToPay {

    /**
     * WebToPay Library version.
     */
    const VERSION = '1.5';

    /**
     * Server URL where all requests should go.
     */
    const PAY_URL = 'https://www.mokejimai.lt/pay/';

    /**
     * Server URL where we can get XML with payment method data.
     */
    const XML_URL = 'https://www.mokejimai.lt/new/lt/lib_web_to_pays/api/';

    /**
     * SMS answer url.
     */
    const SMS_ANSWER_URL = 'https://www.mokejimai.lt/psms/respond/';

    /**
     * Prefix for callback data.
     */
    const PREFIX = 'wp_';

    /**
     * Cache file.
     */
    const CACHE_URL = 'cache.php';


    /**
     * Identifies what verification method was used.
     *
     * Values can be:
     *  - false     not verified
     *  - RESPONSE  only response parameters are verified
     *  - SS1v2     SS1 v2 verification
     *  - SS2       SS2 verification
     */
    public static $verified = false;


    /**
     * If true, check SS2 if false, skip to SS1
     *
     * @deprecated
     */
    private static $SS2 = true;

    /**
     * Toggle SS2 checking. Usualy you don't need to use this method, because
     * by default first SS2 support are checked and if it doesn't work,
     * fallback to SS1.
     *
     * Use this method if your server supports SS2, but you want to use SS1.
     *
     * @deprecated
     */
    public static function toggleSS2($value) {
        self::$SS2 = (bool) $value;
    }


    /**
     * Throw exception.
     *
     * @param  string $code
     * @return void
     */
    public static function throwResponseError($code) {
        $errors = array(
            '0x1'   => self::_('Payment amount is too small.'),
            '0x2'   => self::_('Payment amount is too big.'),
            '0x3'   => self::_('Selected currency is not available.'),
            '0x4'   => self::_('Amount or currency is missing.'),
            '0x6'   => self::_('projectId is missing or such ID does not exist.'),
            '0x7'   => self::_('Testing mode is turned off, but you have still tried to make a test payment.'),
            '0x8'   => self::_('You have banned this way of payment.'),
            '0x9'   => self::_('Coding of variable "paytext" is not suitable (has to be utf-8).'),
            '0x10'  => self::_('Empty or not correctly filled "orderID".'),
            '0x11'  => self::_('Project has not been checked by our administrator.'),
            '0x13'  => self::_('Accepturl, cancellurl, callbacurl or referer base address differs from the addresses confirmed in the project.'),
            '0x14'  => self::_('Invalid "sign" parameter.'),
            '0x15x0'  => self::_('At least one of these parameters is incorrect: cancelurl, accepturl, callbackurl.'),
            '0x15x1'  => self::_('Parameter time_limit is not valid (wrong format or not valid value)'),
        );

        if (isset($errors[$code])) {
            $msg = $errors[$code];
        }
        else {
            $msg = self::_('Unknown error');
        }

        throw new WebToPayException($msg);
    }


    /**
     * Returns specification array for request.
     *
     * @return array
     */
    public static function getRequestSpec() {
        // Array structure:
        //  * name      – request item name.
        //  * maxlen    – max allowed value for item.
        //  * required  – is this item is required.
        //  * user      – if true, user can set value of this item, if false
        //                item value is generated.
        //  * isrequest – if true, item will be included in request array, if
        //                false, item only be used internaly and will not be
        //                included in outgoing request array.
        //  * regexp    – regexp to test item value.
        return array(
            array('projectid',      11,     true,   true,   true,   '/^\d+$/'),
            array('orderid',        40,     true,   true,   true,   ''),
            array('lang',           3,      false,  true,   true,   '/^[a-z]{3}$/i'),
            array('amount',         11,     false,  true,   true,   '/^\d+$/'),
            array('currency',       3,      false,  true,   true,   '/^[a-z]{3}$/i'),
            array('accepturl',      255,    true,   true,   true,   ''),
            array('cancelurl',      255,    true,   true,   true,   ''),
            array('callbackurl',    255,    true,   true,   true,   ''),
            array('payment',        20,     false,  true,   true,   ''),
            array('country',        2,      false,  true,   true,   '/^[a-z_]{2}$/i'),
            array('paytext',        255,    false,  true,   true,   ''),
            array('p_firstname',    255,    false,  true,   true,   ''),
            array('p_lastname',     255,    false,  true,   true,   ''),
            array('p_email',        255,    false,  true,   true,   ''),
            array('p_street',       255,    false,  true,   true,   ''),
            array('p_city',         255,    false,  true,   true,   ''),
            array('p_state',        20,     false,  true,   true,   ''),
            array('p_zip',          20,     false,  true,   true,   ''),
            array('p_countrycode',  2,      false,  true,   true,   '/^[a-z]{2}$/i'),
            array('sign',           255,    true,   false,  true,   ''),
            array('sign_password',  255,    true,   true,   false,  ''),
            array('only_payments',  0,      false,  true,   true,   ''),
            array('disalow_payments', 0,    false,  true,   true,   ''),
            array('repeat_request', 1,      false,  false,  true,   '/^[01]$/'),
            array('test',           1,      false,  true,   true,   '/^[01]$/'),
            array('version',        9,      true,   false,  true,   '/^\d+\.\d+$/'),
            array('time_limit',     19,     false,  true,   true,   '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/'),
        );
    }


    /**
     * Returns specification array for repeat request.
     *
     * @return array
     */
    public static function getRepeatRequestSpec() {
        // Array structure:
        //  * name      – request item name.
        //  * maxlen    – max allowed value for item.
        //  * required  – is this item is required.
        //  * user      – if true, user can set value of this item, if false
        //                item value is generated.
        //  * isrequest – if true, item will be included in request array, if
        //                false, item only be used internaly and will not be
        //                included in outgoing request array.
        //  * regexp    – regexp to test item value.
        return array(
            array('projectid',      11,     true,   true,   true,   '/^\d+$/'),
            array('requestid',      40,     true,   true,   true,   ''),
            array('sign',           255,    true,   false,  true,   ''),
            array('sign_password',  255,    true,   true,   false,  ''),
            array('repeat_request', 1,      true,   false,  true,   '/^1$/'),
            array('version',        9,      true,   false,  true,   '/^\d+\.\d+$/'),
        );
    }


    /**
     * Returns specification array for makro response.
     *
     * @return array
     */
    public static function getMakroResponseSpec() {
        // Array structure:
        //  * name       – request item name.
        //  * maxlen     – max allowed value for item.
        //  * required   – is this item is required in response.
        //  * mustcheck  – this item must be checked by user.
        //  * isresponse – if false, item must not be included in response array.
        //  * regexp     – regexp to test item value.
        return array(
            'projectid'     => array(11,     true,   true,   true,  '/^\d+$/'),
            'orderid'       => array(40,     false,  false,  true,  ''),
            'lang'          => array(3,      false,  false,  true,  '/^[a-z]{3}$/i'),
            'amount'        => array(11,     false,  false,  true,  '/^\d+$/'),
            'currency'      => array(3,      false,  false,  true,  '/^[a-z]{3}$/i'),
            'payment'       => array(20,     false,  false,  true,  ''),
            'country'       => array(2,      false,  false,  true,  '/^[a-z_]{2}$/i'),
            'paytext'       => array(0,      false,  false,  true,  ''),
            '_ss2'          => array(0,      true,   false,  true,  ''),
            '_ss1v2'        => array(0,      false,  false,  true,  ''),
            'name'          => array(255,    false,  false,  true,  ''),
            'surename'      => array(255,    false,  false,  true,  ''),
            'status'        => array(255,    false,  false,  true,  ''),
            'error'         => array(20,     false,  false,  true,  ''),
            'test'          => array(1,      false,  false,  true,  '/^[01]$/'),

            'p_email'       => array(0,      false,  false,  true,  ''),
            'requestid'     => array(40,     false,  false,  true,  ''),
            'payamount'     => array(0,      false,  false,  true,  ''),
            'paycurrency'   => array(0,      false,  false,  true,  ''),

            'version'       => array(9,      true,   false,  true,  '/^\d+\.\d+$/'),

            'sign_password' => array(255,    false,  true,   false, ''),
        );
    }



    /**
     * Returns specification array for mikro response.
     *
     * @return array
     */
    public static function getMikroResponseSpec() {
        // Array structure:
        //  * name       – request item name.
        //  * maxlen     – max allowed value for item.
        //  * required   – is this item is required in response.
        //  * mustcheck  – this item must be checked by user.
        //  * isresponse – if false, item must not be included in response array.
        //  * regexp     – regexp to test item value.
        return array(
            'to'            => array(0,      true,   false,  true,  ''),
            'sms'           => array(0,      true,   false,  true,  ''),
            'from'          => array(0,      true,   false,  true,  ''),
            'operator'      => array(0,      true,   false,  true,  ''),
            'amount'        => array(0,      true,   false,  true,  ''),
            'currency'      => array(0,      true,   false,  true,  ''),
            'country'       => array(0,      true,   false,  true,  ''),
            'id'            => array(0,      true,   false,  true,  ''),
            '_ss2'          => array(0,      true,   false,  true,  ''),
            '_ss1v2'        => array(0,      true,   false,  true,  ''),
            'test'          => array(0,      true,   false,  true,  ''),
            'key'           => array(0,      true,   false,  true,  ''),
            //'version'       => array(9,      true,   false,  true,  '/^\d+\.\d+$/'),
        );
    }

    /**
     * Checks user given request data array.
     *
     * If any errors occurs, WebToPayException will be raised.
     *
     * This method returns validated request array. Returned array contains
     * only those items from $data, that are needed.
     *
     * @param  array $data
     * @return array
     */
    public static function checkRequestData($data, $specs) {
        $request = array();
        foreach ($specs as $spec) {
            list($name, $maxlen, $required, $user, $isrequest, $regexp) = $spec;
            if (!$user) continue;
            if ($required && !isset($data[$name])) {
                $e = new WebToPayException(
                    self::_("'%s' is required but missing.", $name),
                    WebToPayException::E_MISSING);
                $e->setField($name);
                throw $e;
            }

            if (!empty($data[$name])) {
                if ($maxlen && strlen($data[$name]) > $maxlen) {
                    $e = new WebToPayException(
                        self::_("'%s' value '%s' is too long, %d characters allowed.",
                                $name, $data[$name], $maxlen),
                        WebToPayException::E_MAXLEN);
                    $e->setField($name);
                    throw $e;
                }

                if ('' != $regexp && !preg_match($regexp, $data[$name])) {
                    $e = new WebToPayException(
                        self::_("'%s' value '%s' is invalid.", $name, $data[$name]),
                        WebToPayException::E_REGEXP);
                    $e->setField($name);
                    throw $e;
                }
            }

            if ($isrequest && isset($data[$name])) {
                $request[$name] = $data[$name];
            }
        }

        return $request;
    }


    /**
     * Puts signature on request data array.
     *
     * @param  array   $specification
     * @param  string  $request
     * @param  string  $password
     * @return string
     */
    public static function signRequest($specification, $request, $password) {
        $fields = array();
        foreach ($specification as $field) {
            if ($field[4] && $field[0] != 'sign') {
                $fields[] = $field[0];
            }
        }

        $data = '';
        foreach ($fields as $key) {
            if (isset($request[$key]) && trim($request[$key]) != '') {
                $data .= $request[$key];
            }
        }
        $request['sign'] = md5($data . $password);

        return $request;
    }


    /**
     * Builds request data array.
     *
     * This method checks all given data and generates correct request data
     * array or raises WebToPayException on failure.
     *
     * Method accepts single parameter $data of array type. All possible array
     * keys are described here:
     * https://www.mokejimai.lt/makro_specifikacija.html
     *
     * @param  array $data Information about current payment request.
     * @return string
     */
    public static function buildRequest($data) {
        $specs = self::getRequestSpec();
        $request = self::checkRequestData($data, $specs);
        $version = explode('.', self::VERSION);
        $request['version'] = $version[0].'.'.$version[1];
        $request = self::signRequest($specs, $request, $data['sign_password']);
        return $request;
    }


    /**
     * Builds repeat request data array.
     *
     * This method checks all given data and generates correct request data
     * array or raises WebToPayException on failure.
     *
     * Method accepts single parameter $data of array type. All possible array
     * keys are described here:
     * https://www.mokejimai.lt/makro_specifikacija.html
     *
     * @param  array $data Information about current payment request.
     * @return string
     */
    public static function buildRepeatRequest($data) {
        $specs = self::getRepeatRequestSpec();
        $request = self::checkRequestData($data, $specs);
        $request['repeat_request'] = '1';
        $version = explode('.', self::VERSION);
        $request['version'] = $version[0].'.'.$version[1];
        $request = self::signRequest($specs, $request, $data['sign_password']);
        return $request;
    }

    /**
     * Download certificate from webtopay.com.
     *
     * @param  string $cert
     * @return string
     */
    public static function getCert($cert) {
        return self::getUrlContent('http://downloads.webtopay.com/download/'.$cert);
    }

    /**
     * Check is response certificate is valid
     *
     * @param  array $response
     * @param  string $cert
     * @return bool
     */
    public static function checkResponseCert($response, $cert='public.key') {
        $pKeyP = self::getCert($cert);
        if (!$pKeyP) {
            throw new WebToPayException(
                self::_('Can\'t get openssl public key for %s', $cert),
                WebToPayException::E_INVALID);
        }

        $_SS2 = '';
        foreach ($response as $key => $value) {
            if (in_array($key, array('_ss1v2', '_ss2'))) {
                continue;
            }
            $_SS2 .= "{$value}|";
        }
        $ok = openssl_verify($_SS2, base64_decode($response['_ss2']), $pKeyP);

        if ($ok !== 1) {
            throw new WebToPayException(
                self::_('Can\'t verify SS2 for %s', $cert),
                WebToPayException::E_INVALID);
        }

        return true;
    }

    public static function checkResponseData($response, $mustcheck_data, $specs) {
        $resp_keys = array();
        foreach ($specs as $name => $spec) {
            list($maxlen, $required, $mustcheck, $is_response, $regexp) = $spec;
            if ($required && !isset($response[$name])) {
                $e = new WebToPayException(
                    self::_("'%s' is required but missing.", $name),
                    WebToPayException::E_MISSING);
                $e->setField($name);
                throw $e;
            }

            if ($mustcheck) {
                if (!isset($mustcheck_data[$name])) {
                    $e = new WebToPayException(
                        self::_("'%s' must exists in array of second parameter ".
                                "of checkResponse() method.", $name),
                        WebToPayException::E_USER_PARAMS);
                    $e->setField($name);
                    throw $e;
                }

                if ($is_response) {
                    if ($response[$name] != $mustcheck_data[$name]) {
                        $e = new WebToPayException(
                            self::_("'%s' yours and requested value is not ".
                                    "equal ('%s' != '%s') ",
                                    $name, $mustcheck_data[$name], $response[$name]),
                            WebToPayException::E_INVALID);
                        $e->setField($name);
                        throw $e;
                    }
                }
            }

            if (!empty($response[$name])) {
                if ($maxlen && strlen($response[$name]) > $maxlen) {
                    $e = new WebToPayException(
                        self::_("'%s' value '%s' is too long, %d characters allowed.",
                                $name, $response[$name], $maxlen),
                        WebToPayException::E_MAXLEN);
                    $e->setField($name);
                    throw $e;
                }

                if ('' != $regexp && !preg_match($regexp, $response[$name])) {
                    $e = new WebToPayException(
                        self::_("'%s' value '%s' is invalid.", $name, $response[$name]),
                        WebToPayException::E_REGEXP);
                    $e->setField($name);
                    throw $e;
                }
            }

            if (isset($response[$name])) {
                $resp_keys[] = $name;
            }
        }

        // Filter only parameters passed from webtopay
        $_response = array();
        foreach (array_keys($response) as $key) {
            if (in_array($key, $resp_keys)) {
                $_response[$key] = $response[$key];
            }
        }

        return $_response;
    }


    /**
     * Check if SS2 checking is available and enabled.
     *
     * @return bool
     */
    public static function useSS2() {
        return function_exists('openssl_pkey_get_public');
    }


    /**
     * Check for SS1, which is not depend on openssl functions.
     *
     * @param  array  $response
     * @param  string $password
     * @return bool
     */
    public static function checkSS1v2($response, $password) {
        if (32 != strlen($password)) {
            $password = md5($password);
        }

        $buffer = array($password);
        foreach ($response as $key => $value) {
            if (in_array($key, array('_ss1v2', '_ss2'))) {
                continue;
            }
            $buffer[] = $value;
        }

        $ss1v2 = md5(implode('|', $buffer));

        if ($response['_ss1v2'] != $ss1v2) {
            throw new WebToPayException(
                self::_('Can\'t verify SS1 v2'),
                WebToPayException::E_INVALID);
        }

        return true;
    }

    /**
     * Filters saved payment method cache by e-shop's order sum and language
     *
     * @param string    $payCurrency
     * @param int       $sum
     * @param array     $currency           array ( '0' => array ('iso' => 'USD', 'rate' => 0.417391, ),);
     * @param string    $lang
     * @param int       $projectID
     * @return array    $filtered
     */
    public static function getPaymentMethods($payCurrency, $sum, $currency, $lang, $projectID) {

        $filtered    = array();
        $data        = self::loadXML();
        $lang        = strtolower($lang);

        //jei xml senesnis nei para
        if((($data['ts']+3600*24) - time()) < 0 || $data == null) {
            self::getXML($projectID); //siunciam nauja
            $data = self::loadXML();  //vel uzloadinam
        }

        $filtered = self::filterPayMethods($data['data'], $payCurrency, $sum, $currency, $lang);

        return $filtered;
    }


    /**
     * Downloads xml data from webtopay.com
     *
     * @param int       $projectID
     * @return boolean
     */
    public static function getXML($projectID) {
        $response = self::getUrlContent(self::XML_URL.$projectID.'/');
        $feed     = simplexml_load_string($response);

        $feed = simplexml_load_string($response);
        if($feed === false){
            return false;
        } else {
            self::parseXML($feed);
            return true;
        }
    }


    /**
     * Returns payment url
     *
     * @param  string $language
     * @return string $url
     */
    public static function getPaymentUrl($language) {
        $url = self::PAY_URL;
        if($language != 'LT') {
           $url = str_replace('mokejimai.lt', 'webtopay.com', $url);
        }
        return $url;
    }

    /**
     * Loads and unserializes xml data from file
     *
     * @return array $data
     */
    private static function loadXML() {

        $data   = array();

        if (file_exists(self::CACHE_URL)) {
            $fh     = fopen(self::CACHE_URL, 'r');
            $data   = unserialize(fread($fh,filesize(self::CACHE_URL)));
            fclose($fh);
            return $data;
        } else {
            return null;
        }
    }

    /**
     * Parses xml to array, serializes it and writes it to file CACHE_URL
     *
     * @param SimpleXMLElement   $xml
     */
    public static function parseXML($xml){

        $paydata    = array();
        $parsed     = array();
        $language   = array();
        $logo       = array();
        $qouta      = array();
        $title      = array();
        $cache      = array();

        foreach($xml->country as $country){
            $countries  = strtolower(trim((string)$country->attributes()));
            foreach($country->payment_group as $group){
                $groups = strtolower(trim((string)$group->attributes()));
                foreach($group->title as $tit => $v){
                    $language[strtolower(trim((string)$v->attributes()))] = trim((string)$v);
                }
                $parsed[$countries][$groups]['translate'] = $language;
                foreach($group->payment_type as $type) {
                    $types = strtolower(trim((string)$type->attributes()));
                    foreach($type as $key => $value) {
                        if($key === 'logo_url'){
                            $logo[trim((string)$value->attributes())] = trim((string)$value);
                        }
                        if($key === 'title'){
                            $title[trim((string)$value->attributes())] = trim((string)$value);
                        }
                        if($key === 'max' || $key === 'min') {
                            foreach($value->attributes() as $k => $v){
                                $qouta[$key.'_amount'] = trim((string)$value->attributes());
                                $qouta[$key.'_amount_currency'] = trim((string)$v);
                            }
                        }
                        $paydata['logo']    = $logo;
                        $paydata['title']   = $title;
                        $paydata['amount']  = $qouta;
                        $parsed[$countries][$groups][$types] = $paydata;
                    }
                    //unset($qouta);
                    $qouta = null;
                }
            }
        }

        $cache['ts']    = time();
        $cache['data']  = $parsed;

        if (!is_writable(dirname(__FILE__))) {
            throw new WebToPayException(self::_('Directory '.dirname(__FILE__).' is not writable.',WebToPayException::E_INVALID));
        } else {
            $file   = serialize($cache);
            $fp     = fopen(self::CACHE_URL, 'w+') or die('error writing cache');
            fwrite($fp, $file);
            fclose($fp);
        };
    }

    /**
     * Converts money amount to e-shops base currency
     *
     * @param int       $sum
     * @param string    $payCurrency
     * @param string    $convertCurrency
     * @param array     $currency           array ( '0' => array ('iso' => USD, 'rate' => 0.417391, ),);
     * @return int      $amount
     */
    private static function toBaseCurrency($sum, $payCurrency, $convertCurrency, $currency){
        $amount = 0;
        foreach($currency as $entry) {
            if($payCurrency == $entry['iso']) {
                $amount = $sum/$entry['rate']; //turim viska BASE valiuta
                foreach($currency as $entry) {
                    if($convertCurrency == $entry['iso']) {
                        $amount *= $entry['rate'];
                        return $amount;
                        break;
                    }
                }
                break;
            }
        }
    }


    /**
     * Checks minimum amount of payment method
     *
     * @param array     $data
     * @param int       $sum
     * @param string    $payCurrency
     * @param array     $currency           array ( '0' => array ('iso' => USD, 'rate' => 0.417391, ),);
     * @return bool
     */
    private static function checkMinAmount($data, $sum, $payCurrency, $currency){
        //jei apribotas min_amount
        if (array_key_exists('min_amount', $data) && $data['min_amount'] != null) {
            if ($payCurrency == $data['min_amount_currency']) {//kai sutampa valiutos
                return ($sum >= $data['min_amount']);
            } else {
                //konvertuojam i base
                $amount = self::toBaseCurrency($sum, $payCurrency, $data['min_amount_currency'], $currency);
                return ($amount >= $data['min_amount']);
            }
        }
        return true;
    }

    /**
     * Checks maximum amount of payment method
     *
     * @param array     $data
     * @param int       $sum
     * @param string    $payCurrency
     * @param array     $currency           array ( '0' => array ('iso' => USD, 'rate' => 0.417391, ),);
     * @return bool
     */
    private static function checkMaxAmount($data, $sum, $payCurrency, $currency){
        //jei apribotas max_amount
        if (array_key_exists('max_amount', $data) && $data['max_amount'] != null) {
            if ($payCurrency == $data['max_amount_currency']) {//kai sutampa valiutos
                return ($data['max_amount'] >= $sum);
            } else {
                //konvertuojam i base
                $amount = self::toBaseCurrency($sum, $payCurrency, $data['max_amount_currency'], $currency);
                return ($data['max_amount'] >= $amount);
            }
        }
        return true;
    }

    /**
     * Checks maximum amount of payment method
     *
     * @param array     $payMethods     - unserialized array with pay method data
     * @param string    $payCurrency    -
     * @param int       $sum
     * @param string    $payCurrency
     * @param array     $currency           array ( '0' => array ('iso' => USD, 'rate' => 0.417391, ),);
     * @param string    $lang
     * @return array
     */
    public static function filterPayMethods($payMethods, $payCurrency, $sum, $currency, $lang) {

        $filtered   = array();
        $groupName  = array();
        $logo       = null;
        $name       = null;

        foreach($payMethods as $key1 => $value1) {
            foreach($value1 as $key2 => $value2){
                foreach($value2 as $key3 => $value3){
                    if($key3 === 'translate') {
                        foreach($value3 as $loc => $text) {
                            if($loc === 'en'){
                                $groupName = $text;
                            }
                            if($loc === $lang) {
                                $groupName = $text;
                                break;
                            }
                        }
                    } else {
                        foreach($value3 as $key4 => $value4) {
                            if($key4 === 'logo'){
                                foreach($value4 as $k => $v) {
                                    if($k === 'en'){ //statom anglu kalba default jei nerasta vertimu
                                        $logo = $v;
                                    }
                                    if($k === $lang) {
                                        $logo = $v;
                                        break;
                                    }
                                }
                            }
                            if($key4 === 'title'){
                                foreach($value4 as $k => $v) {
                                    if($k === 'en'){
                                        $name = $v;
                                    }
                                    if($k === $lang) {
                                        $name = $v;
                                        break;
                                    }
                                }
                            }
                            if($key4 === 'amount'){
                                $min = self::checkMinAmount($value4, $sum, $payCurrency, $currency);
                                $max = self::checkMaxAmount($value4, $sum, $payCurrency, $currency);
                                if($min && $max) { //jei praeina pagal min ir max irasom
                                    $filtered[$key1][$groupName][$name] = array('name' => $key3,'logo' => $logo);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $filtered;
    }


    /**
     * Return type and specification of given response array.
     *
     * @param array     $response
     * @return array($type, $specs)
     */
    public static function getSpecsForResponse($response) {
        if (
                isset($response['to']) &&
                isset($response['from']) &&
                isset($response['sms']) &&
                !isset($response['projectid'])
            )
        {
            $type = 'mikro';
            $specs = self::getMikroResponseSpec();
        }
        else {
            $type = 'makro';
            $specs = self::getMakroResponseSpec();
        }

        return array($type, $specs);
    }


    /**
     * @param string $prefix
     */
    public static function getPrefixed($data, $prefix) {
        if (empty($prefix)) return $data;
        $ret = array();
        foreach ($data as $key => $val) {
            if (strpos($key, $prefix) === 0 && strlen($key) > 3) {
                $ret[substr($key, 3)] = $val;
            }
        }
        return $ret;
    }

    /**
     * @param string $URL
     */
    private static function getUrlContent($URL){
        $url = parse_url($URL);
        if ('https' == $url['scheme']) {
            $host = 'ssl://'.$url['host'];
            $port = 443;
        } else {
            $host = $url['host'];
            $port = 80;
        }

        try {
            $fp = fsockopen($host, $port, $errno, $errstr, 30);
            if (!$fp) {
                throw new WebToPayException(
                    self::_('Can\'t connect to %s', $URL),
                    WebToPayException::E_INVALID);
            }

            if(isset($url['query'])) {
                $data = $url['path'].'?'.$url['query'];
            } else {
                $data = $url['path'];
            }

            $out = "GET " . $data . " HTTP/1.0\r\n";
            $out .= "Host: ".$url['host']."\r\n";
            $out .= "Connection: Close\r\n\r\n";

            $content = '';

            fwrite($fp, $out);
            while (!feof($fp)) $content .= fgets($fp, 8192);
            fclose($fp);

            list($header, $content) = explode("\r\n\r\n", $content, 2);

            return trim($content);

        } catch (WebToPayException $e) {
            throw new WebToPayException(self::_('fsockopen fail!', WebToPayException::E_INVALID));
        }
    }


    /**
     * Checks and validates response from WebToPay server.
     *
     * This function accepts both mikro and makro responses.
     *
     * First parameter usualy should by $_GET array.
     *
     * Description about response can be found here:
     * makro: https://www.mokejimai.lt/makro_specifikacija.html
     * mikro: https://www.mokejimai.lt/mikro_mokejimu_specifikacija_SMS.html
     *
     * If response is not correct, WebToPayException will be raised.
     *
     * @param array     $response       Response array.
     * @param array     $user_data
     * @return array
     */
    public static function checkResponse($response, $user_data=array()) {
        self::$verified = false;

        $response = self::getPrefixed($response, self::PREFIX);

        // *get* response type (makro|mikro)
        list($type, $specs) = self::getSpecsForResponse($response);

        try {
            // *check* response
            $version = explode('.', self::VERSION);
            $version = $version[0].'.'.$version[1];
            if ('makro' == $type && $response['version'] != $version) {
                throw new WebToPayException(
                    self::_('Incompatible library and response versions: ' .
                            'libwebtopay %s, response %s', self::VERSION, $response['version']),
                    WebToPayException::E_INVALID);
            }

            if ('makro' == $type && $response['projectid'] != $user_data['projectid']) {
                throw new WebToPayException(
                    self::_('Bad projectid: ' .
                            'libwebtopay %s, response %s', self::VERSION, $response['version']),
                    WebToPayException::E_INVALID);
            }

            if ('makro' == $type) {
                self::$verified = 'RESPONSE VERSION '.$response['version'].' OK';
            }

            $orderid = 'makro' == $type ? $response['orderid'] : $response['id'];
            $password = $user_data['sign_password'];

            // *check* SS2
            if (self::useSS2()) {
                $cert = 'public.key';
                if (self::checkResponseCert($response, $cert)) {
                    self::$verified = 'SS2 public.key';
                }
            }

            // *check* SS1 v2
            else if (self::checkSS1v2($response, $password)) {
                self::$verified = 'SS1v2';
            }

            // *check* status
            if ('makro' == $type && $response['status'] != '1') {
                throw new WebToPayException(
                    self::_('Returned transaction status is %d, successful status '.
                            'should be 1.', $response['status']),
                    WebToPayException::E_STATUS);
            }

        }

        catch (WebToPayException $e) {
            if (isset($user_data['log'])) {
                self::log('ERR',
                    self::responseToLog($type, $response) .
                    ' ('. get_class($e).': '. $e->getMessage().')',
                    $user_data['log']);
            }
            throw $e;
        }

        if (isset($user_data['log'])) {
            self::log('OK', self::responseToLog($type, $response), $user_data['log']);
        }

        return $response;
    }

    /**
     * @param string $type
     */
    public static function responseToLog($type, $req) {
        if ('mikro' == $type) {
            return self::mikroResponseToLog($req);
        }
        else {
            return self::makroResponseToLog($req);
        }
    }

    public static function mikroResponseToLog($req) {
        $ret = array();
        foreach (array('to', 'from', 'id', 'sms') as $key) {
            $ret[] = $key.':"'.$req[$key].'"';
        }

        return 'MIKRO '.implode(', ', $ret);
    }

    public static function makroResponseToLog($req) {
        $ret = array();
        foreach (array('projectid', 'orderid', 'payment') as $key) {
            $ret[] = $key.':"'.$req[$key].'"';
        }

        return 'MAKRO '.implode(', ', $ret);
    }

    public static function mikroAnswerToLog($answer) {
        $ret = array();
        foreach (array('id', 'msg') as $key) {
            $ret[] = $key.':"'.$answer[$key].'"';
        }

        return 'MIKRO [answer] '.implode(', ', $ret);
    }

    /**
     * @param string $type
     * @param string $msg
     */
    public static function log($type, $msg, $logfile=null) {
        if (!isset($logfile)) {
            return false;
        }

        $fp = @fopen($logfile, 'a');
        if (!$fp) {
            throw new WebToPayException(
                self::_('Can\'t write to logfile: %s', $logfile), WebToPayException::E_LOG);
        }

        $logline = array();

        // *log* type
        $logline[] = $type;

        // *log* REMOTE_ADDR
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $logline[] = $_SERVER['REMOTE_ADDR'];
        }
        else {
            $logline[] = '-';
        }

        // *log* datetime
        $logline[] = date('[Y-m-d H:i:s O]');

        // *log* version
        $logline[] = 'v'.self::VERSION.':';

        // *log* message
        $logline[] = $msg;

        $logline = implode(' ', $logline)."\n";
        fwrite($fp, $logline);
        fclose($fp);

        // clear big log file
        if (filesize($logfile) > 1024 * 1024 * pi()) {
            copy($logfile, $logfile.'.old');
            unlink($logfile);
        }
    }


    /**
     * Sends SMS answer.
     *
     * @param array     $answer
     * @return void
     */
    public static function smsAnswer($answer) {

        $data = array(
                'id'            => $answer['id'],
                'msg'           => $answer['msg'],
                'transaction'   => md5($answer['sign_password'].'|'.$answer['id']),
            );

        $url = self::SMS_ANSWER_URL.'?'.http_build_query($data);
        try {
            $content = self::getUrlContent($url);
            if (strpos($content, 'OK') !== 0) {
                throw new WebToPayException(
                    self::_('Error: %s', $content),
                    WebToPayException::E_SMS_ANSWER);
            }
        } catch (WebToPayException $e) {
            if (isset($answer['log'])) {
                self::log('ERR',
                    self::mikroAnswerToLog($answer).
                    ' ('. get_class($e).': '. $e->getMessage().')',
                    $answer['log']);
            }
            throw $e;
        }

        if (isset($answer['log'])) {
            self::log('OK', self::mikroAnswerToLog($answer), $answer['log']);
        }

    }


    /**
     * I18n support.
     */
    public static function _() {
        $args = func_get_args();
        if (sizeof($args) > 1) {
            return call_user_func_array('sprintf', $args);
        }
        else {
            return $args[0];
        }
    }

}



class WebToPayException extends Exception {

    /**
     * Missing field.
     */
    const E_MISSING = 1;

    /**
     * Invalid field value.
     */
    const E_INVALID = 2;

    /**
     * Max length exceeded.
     */
    const E_MAXLEN = 3;

    /**
     * Regexp for field value doesn't match.
     */
    const E_REGEXP = 4;

    /**
     * Missing or invalid user given parameters.
     */
    const E_USER_PARAMS = 5;

    /**
     * Logging errors
     */
    const E_LOG = 6;

    /**
     * SMS answer errors
     */
    const E_SMS_ANSWER = 7;

    /**
     * Macro answer errors
     */
    const E_STATUS = 8;

    protected $field_name = false;

    public function setField($field_name) {
        $this->field_name = $field_name;
    }

    public function getField() {
        return $this->field_name;
    }
}
