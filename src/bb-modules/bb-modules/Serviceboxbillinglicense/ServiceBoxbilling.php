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

namespace Box\Mod\Serviceboxbillinglicense;

class ServiceBoxbilling implements \Box\InjectionAwareInterface
{

    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }
    /**
     * Api URL
     *
     * @example https://www.boxbilling.com/api
     * @var string
     */
    protected $_api_url = 'http://www.boxbilling.com/api';

    /**
     * Api key is found in BoxBilling profile page
     *
     * @example e4yny7yjy5u3yhyhepumuqaquva3y4as
     * @var string
     */
    protected $_api_key = NULL;

    /**
     * Same service can be used to control BoxBilling as client and guest
     *
     * @example guest
     * @example admin
     * @example client
     * @var string
     */
    protected $_api_role = 'client';

    /**
     * Path to cookie to save session for requests.
     *
     * @var string - path to cookie. Must be writable
     */
    protected $_cookie = NULL;

    public function __construct($options)
    {
        if (!extension_loaded('curl')) {
            throw new \Exception('cURL extension is not enabled');
        }

        if (isset($options['api_key'])) {
            $this->_api_key = $options['api_key'];
        }

        if (isset($options['api_url'])) {
            $this->_api_url = $options['api_url'];
        }

        if (isset($options['api_role'])) {
            $this->_api_role = $options['api_role'];
        }

        $this->_cookie = sys_get_temp_dir() . '/bbcookie.txt';
    }

    public function __call($method, $arguments)
    {
        $data = array();
        if (isset($arguments[0]) && is_array($arguments[0])) {
            $data = $arguments[0];
        }

        $module = substr($method, 0, strpos($method, '_'));
        $m      = substr($method, strpos($method, '_') + 1);

        $url = $this->_api_url . '/' . $this->_api_role . '/' . $module . '/' . $m;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->_api_role . ":" . $this->_api_key);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->_cookie);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->_cookie);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $result = curl_exec($ch);

        if ($result === false) {
            $e = new \Exception(sprintf('Curl Error: "%s"', curl_error($ch)));
            curl_close($ch);
            throw $e;
        }

        curl_close($ch);

        $json = json_decode($result, 1);

        if (!is_array($json)) {
            throw new \Exception(sprintf('BoxBilling API: Invalid Response "%s"', $result));
        }

        if (isset($json['error']) && !empty($json['error'])) {
            throw new \Exception($json['error']['message'], $json['error']['code']);
        }

        if (!isset($json['result'])) {
            throw new \Exception(sprintf('BoxBilling API: Invalid Response "%s"', $result));
        }

        return $json['result'];
    }

}