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


class Box_Curl {

    protected $_useragent = 'Mozilla/5.0 (Windows NT 10.0; rv:91.0) Gecko/20100101 Firefox/91.0';
    protected $_url;
    protected $_followlocation;
    protected $_timeout;
    protected $_maxRedirects;
    protected $_cookieFileLocation = './cookie.txt';
    protected $_post;
    protected $_postFields;
    protected $_referer = "https://www.google.com";
    protected $_session;
    protected $_webpage;
    protected $_includeHeader;
    protected $_noBody;
    protected $_status;
    protected $_binaryTransfer;
    public $authentication = 0;
    public $auth_name = '';
    public $auth_pass = '';

    /**
     * @var \Box_Di
     */
    protected $di = null;

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    /**
    * Constructs a new Curl object.
    *
    * @param string $url The URL to send the request to.
    * @param int $timeOut [optional] The maximum time (in seconds) that the connection should remain open. Default is 30 seconds.
    * @param bool $followlocation [optional] Whether to follow redirects. Default is true.
    * @param int $maxRedirecs [optional] The maximum number of redirects to follow. Default is 4.
    * @param bool $binaryTransfer [optional] Whether to receive the response as a binary string. Default is false.
    * @param bool $includeHeader [optional] Whether to include the response header in the output. Default is false.
    * @param bool $noBody [optional] Whether to exclude the response body from the output. Default is false.
    *
    * @throws \Box_Exception If the CURL extension is not enabled.
    */
    public function __construct($url, $timeOut = 30, $followlocation = true, $maxRedirecs = 4, $binaryTransfer = false, $includeHeader = false, $noBody = false) {
        if (!extension_loaded('curl')) {
            throw new \Box_Exception('CURL extension is not enabled');
        }

        $this->_url = $url;
        $this->_followlocation = (bool)$followlocation;
        $this->_timeout = $timeOut;
        $this->_maxRedirects = $maxRedirecs;
        $this->_noBody = $noBody;
        $this->_includeHeader = $includeHeader;
        $this->_binaryTransfer = $binaryTransfer;

        $this->_cookieFileLocation = PATH_CACHE . '/c.txt';
    }

    public function useAuth($use) {
        $this->authentication = 0;
        if ($use){
            $this->authentication = 1;
        }
    }

    public function setName($name) {
        $this->auth_name = $name;
    }

    public function setPass($pass) {
        $this->auth_pass = $pass;
    }

    public function setReferer($referer) {
        $this->_referer = $referer;
    }

    public function setCookiFileLocation($path) {
        $this->_cookieFileLocation = $path;
    }

    public function setPost($postFields) {
        $this->_post = true;
        $this->_postFields = $postFields;
    }

    public function setUserAgent($userAgent) {
        $this->_useragent = $userAgent;
    }

    public function request($url = null) {
        if (null !== $url) {
            $this->_url = $url;
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->_timeout);
        curl_setopt($ch, CURLOPT_MAXREDIRS, $this->_maxRedirects);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->_cookieFileLocation);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->_cookieFileLocation);
        
        if (ini_get('open_basedir') == '' && ini_get('safe_mode') == 'Off'){
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->_followlocation);
        }
        
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        
        if ($this->authentication == 1) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->auth_name . ':' . $this->auth_pass);
        }
        if ($this->_post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_postFields);
        }

        if ($this->_includeHeader) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }

        if ($this->_noBody) {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }
        curl_setopt($ch, CURLOPT_USERAGENT, $this->_useragent);
        curl_setopt($ch, CURLOPT_REFERER, $this->_referer);

        $this->_webpage = curl_exec($ch);

        $this->_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $this;
    }
    
    public function getBody()
    {
        return $this->_webpage;
    }

    public function getHttpStatus() {
        return $this->_status;
    }
    
    public function downloadTo($path)
    {
        if(!file_exists($path)) {
            touch($path);
        }
        
        set_time_limit(0); // unlimited max execution time
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->_timeout);
        curl_setopt($ch, CURLOPT_FILE, fopen($path, 'w'));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_exec($ch);
        if(curl_errno($ch)) {
            throw new Exception(sprintf("curl Error %s: %s", curl_errno($ch), curl_error($ch)));
        }
    }

    public function __tostring() {
        return $this->_webpage;
    }
}
