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


class Box_Request implements \Box\InjectionAwareInterface
{
    protected $di;
    protected $_data = array();
    protected $_header = array();
    protected $_server = array();
    protected $_request = array();
    protected $_post = array();
    protected $_get = array();
    protected $_cookie = array();
    protected $_method = 'post';

    public function __construct()
    {
        $this->_get = $_GET;
        $this->_post = $_POST;
        $this->_request = $_REQUEST;
        $this->_server = $_SERVER;
    }

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function get($name = null, $filters = null, $defaultValue = null)
    {
        if (empty($name)) {
            return $this->_request;
        }
        $value = isset($this->_request[$name]) ? $this->_request[$name] : $defaultValue;
        return $this->_filterValue($value, $filters);
    }

    public function getPost($name = null, $filters = null, $defaultValue = null)
    {
        if (empty($name)) {
            return $this->_post;
        }
        $value = isset($this->_post[$name]) ? $this->_post[$name] : $defaultValue;
        return $this->_filterValue($value, $filters);
    }

    public function getQuery($name = null, $filters = null, $defaultValue = null)
    {
        if (empty($name)) {
            return $this->_get;
        }

        $value = isset($this->_get[$name]) ? $this->_get[$name] : $defaultValue;
        return $this->_filterValue($value, $filters);
    }

    /**
     * Returns the available headers in the request
     * @return array
     */
    public function getHeaders()
    {
        $list = getallheaders();
        return is_array($list) ? $list : array();
    }

    public function getPut($name = null, $filters = null, $defaultValue = null)
    {
        parse_str($this->getRawBody(), $put);
        if(empty($name)) {
            return $put;
        }

        $value = isset($put[$name]) ? $put[$name] : $defaultValue;
        return $this->_filterValue($value, $filters);
    }

    /**
     * @param string $name
     */
    public function getServer($name)
    {
        return isset($this->_server[$name]) ? $this->_server[$name] : null;
    }

    public function has($name)
    {
        return isset($this->_request[$name]);
    }

    public function hasPost($name)
    {
        return isset($this->_post[$name]);
    }

    public function hasPut($name)
    {
        parse_str($this->getRawBody(), $put);
        if(is_array($put) && isset($put[$name])) {
            return true;
        }
        return false;
    }

    public function hasQuery($name)
    {
        return isset($this->_get[$name]);
    }

    /**
     * @param string $name
     */
    public function hasServer($name)
    {
        return isset($this->_server[$name]);
    }

    /**
     * Gets HTTP header from request data
     * @param string $header
     * @return string
     */
    public function getHeader($header)
    {
        $headers = $this->getHeaders();
        return isset($headers[$header]) ? $headers[$header] : null;
    }

    /**
     * Gets HTTP schema (http/https)
     * @return string
     */
    public function getScheme()
    {
        return $this->getServer('HTTPS') ? 'https' : 'http';
    }
    
    /**
     * Checks whether request has been made using ajax. Checks if $_SERVER[‘HTTP_X_REQUESTED_WITH’]==’XMLHttpRequest’
     * @return bool
     */
    public function isAjax()
    {
        return ($this->getServer('HTTP_X_REQUESTED_WITH') == 'XMLHttpRequest');
    }

    /**
     * Checks whether request has been made using SOAP
     * @return bool
     */
    public function isSoapRequested()
    {
        if($this->hasServer('HTTP_SOAPACTION')) {
            return true;
        }

        if($this->getServer('CONTENT_TYPE') == 'application/soap+xml') {
            return true;
        }

        return false;
    }

    /**
     * Checks whether request has been made using any secure layer
     * @return bool
     */
    public function isSecureRequest()
    {
        return ($this->getScheme() == 'https');
    }

    /**
     * Gets HTTP raw request body
     * @return string
     */
    public function getRawBody()
    {
        return file_get_contents('php://input');
    }

    /**
     * Gets decoded JSON HTTP raw request body
     * @return string
     */
    public function getJsonRawBody()
    {
        $result = json_decode($this->getRawBody(), 1);
        if($result) {
            return $result;
        }

        $_messages = array(
            JSON_ERROR_NONE => 'No error has occurred',
            JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
            JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
            JSON_ERROR_SYNTAX => 'Syntax error',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded'
        );
        $error = json_last_error();
        $msg = isset($_messages[$error]) ? $_messages[$error] : 'Error decoding json';
        throw new RuntimeException($msg);
    }

    /**
     * Gets active server address IP
     * @return string
     */
    public function getServerAddress()
    {
        $address = $this->getServer('SERVER_ADDR');
        return ($address) ? $address : '127.0.0.1';
    }

    /**
     * Gets active server name
     * @return string
     */
    public function getServerName()
    {
        $address = $this->getServer('SERVER_NAME');
        return ($address) ? $address : 'localhost';
    }

    /**
     * Gets information about schema, host and port used by the request
     * @return string
     */
    public function getHttpHost()
    {
        return $this->getServer('HTTP_HOST');
    }

    /**
     * Gets most possible client IPv4 Address. This method search in $_SERVER[‘REMOTE_ADDR’] and optionally in $_SERVER[‘HTTP_X_FORWARDED_FOR’]
     * @param bool $trustForwardedHeader - should we trust forwarded header?
     * return string
     */
    public function getClientAddress($trustForwardedHeader = true)
    {
        $address = null;
        if($trustForwardedHeader) {
            $address = $this->getServer('HTTP_X_FORWARDED_FOR');
        }
        if(is_null($address)) {
            $address = $this->getServer('REMOTE_ADDR');
        }
        if(is_string($address)) {
            if(strpos($address, ',') !== false) {
                list($address) = explode(',', $address);
            }
        }
        return $address;
    }

    /**
     * Gets HTTP method which request has been made
     * @return string
     */
    public function getMethod()
    {
        return $this->getServer('REQUEST_METHOD');
    }

    /**
     * Gets HTTP URI which request has been made
     * @return string
     */
    public function getURI()
    {
        return $this->getServer('REQUEST_URI');
    }

    /**
     * Gets HTTP user agent used to made the request
     * @return string
     */
    public function getUserAgent()
    {
        return $this->getServer('HTTP_USER_AGENT');
    }

    /**
     * Check if HTTP method match any of the passed methods
     * @param string | array
     * @return bool
     */
    public function isMethod($methods)
    {
        $current = $this->getMethod();
        if(is_array($methods)) {
            foreach($methods as $method) {
                if($current == $method) {
                    return true;
                }
            }
            return false;
        }
        return ($current == $methods);
    }

    /**
     * Checks whether HTTP method is POST. if $_SERVER[‘REQUEST_METHOD’]==’POST’
     * @return bool
     */
    public function isPost()
    {
        return ($this->getMethod() == 'POST');
    }

    /**
     * Checks whether HTTP method is GET. if $_SERVER[‘REQUEST_METHOD’]==’GET’
     * @return bool
     */
    public function isGet()
    {
        return ($this->getMethod() == 'GET');
    }

    /**
     * Checks whether HTTP method is PUT. if $_SERVER[‘REQUEST_METHOD’]==’PUT’
     * @return bool
     */
    public function isPut()
    {
        return ($this->getMethod() == 'PUT');
    }

    /**
     * Checks whether HTTP method is PATCH. if $_SERVER[‘REQUEST_METHOD’]==’PATCH’
     * @return bool
     */
    public function isPatch()
    {
        return ($this->getMethod() == 'PATCH');
    }

    /**
     * Checks whether HTTP method is HEAD. if $_SERVER[‘REQUEST_METHOD’]==’HEAD’
     * @return bool
     */
    public function isHead()
    {
        return ($this->getMethod() == 'HEAD');
    }

    /**
     * Checks whether HTTP method is DELETE. if $_SERVER[‘REQUEST_METHOD’]==’DELETE’
     * @return bool
     */
    public function isDelete()
    {
        return ($this->getMethod() == 'DELETE');
    }

    /**
     * Checks whether HTTP method is OPTIONS. if $_SERVER[‘REQUEST_METHOD’]==’OPTIONS’
     * @return bool
     */
    public function isOptions()
    {
        return ($this->getMethod() == 'OPTIONS');
    }

    /**
     * Checks whether request includes attached files
     * @return int - number of files
     */
    public function hasFiles($onlySuccessful = true)
    {
        $number_of_files = 0;
        $number_of_successful_files = 0;
        foreach($_FILES as $file) {
            $number_of_files++;
            if(isset($file['error']) && $file['error'] == 0) {
                $number_of_successful_files++;
            }
        }
        return ($onlySuccessful)  ? $number_of_successful_files : $number_of_files;
    }

    /**
     * Gets attached files as SplFileInfo collection
     * @return array
     */
    public function getUploadedFiles($onlySuccessful = true)
    {
        $files = array();
        foreach($_FILES as $file) {
            $f = new Box_RequestFile($file);
            if($onlySuccessful) {
                if($file['error'] == 0) {
                    $files[] = $f;
                }
            } else {
                $files[] = $f;
            }
        }
        return $files;
    }

    /**
     * Gets web page that refers active request. ie: http://www.google.com
     * @return string
     */
    public function getHTTPReferer()
    {
        return $this->getServer('HTTP_REFERER');
    }

    /**
     * Gets array with mime/types and their quality accepted by the browser/client from $_SERVER[‘HTTP_ACCEPT’]
     * @return array
     */
    public function getAcceptableContent()
    {
        return $this->getServer('HTTP_ACCEPT');
    }

    /**
     * Gets best mime/type accepted by the browser/client from $_SERVER[‘HTTP_ACCEPT’]
     * @return array
     */
    public function getBestAccept()
    {
        //@todo
    }

    /**
     * Gets charsets array and their quality accepted by the browser/client from $_SERVER[‘HTTP_ACCEPT_CHARSET’]
     * @return array
     */
    public function getClientCharsets()
    {
        //@todo
    }

    /**
     * Gets best charset accepted by the browser/client from $_SERVER[‘HTTP_ACCEPT_CHARSET’]
     * @return string
     */
    public function getBestCharset()
    {
        //@todo
    }

    /**
     * Gets languages array and their quality accepted by the browser/client from $_SERVER[‘HTTP_ACCEPT_LANGUAGE’]
     * @return array
     */
    public function getLanguages()
    {
        $language_header = $this->getServer('HTTP_ACCEPT_LANGUAGE');
        return $this->_getQualityHeader($language_header, 'language');
    }

    /**
     * Gets best language accepted by the browser/client from $_SERVER[‘HTTP_ACCEPT_LANGUAGE’]
     * @return string
     */
    public function getBestLanguage()
    {
        //@todo
    }

    /**
     * Gets auth info accepted by the browser/client from $_SERVER[‘PHP_AUTH_USER’]
     * @return array
     */
    public function getBasicAuth()
    {
        $username = null;
        $password = null;

        if ($this->hasServer('PHP_AUTH_USER')) {
            $username = $_SERVER['PHP_AUTH_USER'];
            $password = $_SERVER['PHP_AUTH_PW'];
        } elseif ($this->hasServer('HTTP_AUTHENTICATION')) {
            $auth = $this->getServer('HTTP_AUTHENTICATION');
            if (strpos(strtolower($auth), 'basic') === 0) {
                list($username,$password) = explode(':',base64_decode(substr($auth, 6)));
            }
        }

        return array(
            'username'  =>  $username,
            'password'  =>  $password,
        );
    }

    /**
     * Gets auth info accepted by the browser/client from $_SERVER[‘PHP_AUTH_DIGEST’]
     * @return array
     */
    public function getDigestAuth()
    {
        // mod_php
        if ($this->hasServer('PHP_AUTH_DIGEST')) {
            $digest = $this->getServer('PHP_AUTH_DIGEST');
            // most other servers
        } elseif ($this->hasServer('HTTP_AUTHENTICATION')) {
            $auth = $this->getServer('HTTP_AUTHENTICATION');
            if (strpos(strtolower($auth),'digest')===0) {
                $digest = substr($auth, 7);
            }
        }

        // protect against missing data
        $needed_parts = array('nonce'=>1, 'nc'=>1, 'cnonce'=>1, 'qop'=>1, 'username'=>1, 'uri'=>1, 'response'=>1);
        $data = array();
        $keys = implode('|', array_keys($needed_parts));

        preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $digest, $matches, PREG_SET_ORDER);

        foreach ((array) $matches as $m) {
            $data[$m[1]] = $m[3] ? $m[3] : $m[4];
            unset($needed_parts[$m[1]]);
        }

        return $needed_parts ? false : $data;
    }

    /**
     * Process a request header and return an array of values with their qualities
     * @param string $q
     * @return array
     */
    protected function _getQualityHeader($header, $q)
    {
        $return = array();
        $parts = preg_split('/,\\s*/', $header);

        foreach($parts as $part) {
            $headerParts = explode(';', $part);
            if(isset($headerParts[1]) === true) {
                $quality = substr($headerParts[1], 2);
            } else {
                $quality = 1;
            }

            $return[] = array($q => $headerParts[0], 'quality' => $quality);
        }

        return $return;
    }

    /**
     * Process a request header and return the one with best quality
     * @return string
     */
    protected function _getBestQuality($header)
    {
        //@todo
    }

    /**
     * @todo implement filtering
     * @param $value - value to filter
     * @param string|array $filters - list of filter to apply on value
     */
    private function _filterValue($value, $filters)
    {
        return $value;
    }
}


if (!function_exists('getallheaders'))
{
    function getallheaders()
    {
        $headers = '';
        foreach ($_SERVER as $name => $value)
        {
            if (substr($name, 0, 5) == 'HTTP_')
            {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}