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


class Box_Curl {

    protected $_useragent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1';
    protected $_url;
    protected $_followlocation;
    protected $_timeout;
    protected $_maxRedirects;
    protected $_cookieFileLocation = './cookie.txt';
    protected $_post;
    protected $_postFields;
    protected $_referer = "http://www.google.com";
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
     * @param string $url
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

        $this->_cookieFileLocation = BB_PATH_CACHE . '/c.txt';
    }

    public function useAuth($use) {
        $this->authentication = 0;
        if ($use == true)
            $this->authentication = 1;
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