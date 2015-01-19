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

namespace Box\Mod\Serviceyouhosting;

class Youhosting_Api
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function call($resource, $params = array())
    {
        list($class, $method) = explode('.', $resource);
        $version = isset($this->config['version']) ? $this->config['version'] : 'V1';
        $r       = '\Box\Mod\Serviceyouhosting\Youhosting_Api_' . $version . '_' . $class;

        $reflection = new \ReflectionClass($r);

        if (!$reflection->hasMethod($method)) {
            throw new \Exception("Resource $resource is not valid. (Hint: Check resource method)", 101);
        }


        $m = $reflection->getMethod($method);

        return $m->invoke(new $r($this->config), $params);
    }
}

class Youhosting_Api_V1
{
    const VERSION = '1.0.0';

    private $config;
    private $apiBase = 'https://rest.main-hosting.com/v1/';

    public function __construct($config)
    {
        if (!isset($config['api_key'])) {
            throw new \Exception('No API key provided');
        }
        $this->config = $config;
    }

    /**
     * @param string $url
     */
    protected function _post($url, array $params = array())
    {
        return $this->_request($url, $params, 'POST');
    }

    /**
     * @param string $url
     */
    protected function _get($url, array $params = array())
    {
        return $this->_request($url, $params, 'GET');
    }

    /**
     * @param string $url
     */
    protected function _delete($url, array $params = array())
    {
        return $this->_request($url, $params, 'DELETE');
    }

    private function _request($url, array $params = array(), $meth = 'POST')
    {
        $client = self::VERSION;
        $absUrl = $this->apiBase . $url;

        $params['client_ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : $this->config['ip'];

        $langVersion = phpversion();
        $uname       = php_uname();
        $ua          = array(
            'bindings_version' => $client,
            'lang'             => 'php',
            'lang_version'     => $langVersion,
            'publisher'        => 'youhosting',
            'uname'            => $uname
        );
        $headers     = array(
            'X-Youhosting-Client-User-Agent: ' . json_encode($ua),
            'User-Agent: YouHosting/PHP/' . $client,
            'Authorization: Basic ' . base64_encode('reseller:' . $this->config['api_key']),
        );

        if (isset($this->config['boxbilling'])) {
            $headers[] = 'X-BoxBilling: ' . json_encode($this->config['boxbilling']);
        }

        list($rbody, $rcode) = $this->_curlRequest($meth, $absUrl, $headers, $params);

        $result = json_decode($rbody, true);
        if ($result['error']) {
            throw new \Exception($result['error']['message'], $result['error']['code']);
        }

        return $result['result'];
    }

    /**
     * @param string $meth
     * @param string $absUrl
     * @param string[] $headers
     */
    private function _curlRequest($meth, $absUrl, $headers, $params)
    {
        $curl = curl_init();
        $meth = strtolower($meth);
        $opts = array();
        if ($meth == 'get') {
            $opts[CURLOPT_HTTPGET] = 1;
            if (count($params) > 0) {
                $encoded = $this->_encode($params);
                $absUrl  = "$absUrl?$encoded";
            }
        } else if ($meth == 'post') {
            $opts[CURLOPT_POST]       = 1;
            $opts[CURLOPT_POSTFIELDS] = $this->_encode($params);
        } else if ($meth == 'delete') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
            if (count($params) > 0) {
                $encoded = $this->_encode($params);
                $absUrl  = "$absUrl?$encoded";
            }
        } else {
            throw new \Exception("Unrecognized method $meth");
        }

        $absUrl                       = $this->_utf8($absUrl);
        $opts[CURLOPT_URL]            = $absUrl;
        $opts[CURLOPT_RETURNTRANSFER] = true;
        $opts[CURLOPT_CONNECTTIMEOUT] = 30;
        $opts[CURLOPT_TIMEOUT]        = 80;
        $opts[CURLOPT_HTTPHEADER]     = $headers;
        $opts[CURLOPT_SSL_VERIFYPEER] = false;

        curl_setopt_array($curl, $opts);
        $rbody = curl_exec($curl);
        $errno = curl_errno($curl);

        if ($rbody === false) {
            $errno   = curl_errno($curl);
            $message = curl_error($curl);
            curl_close($curl);
            $this->_handleCurlError($errno, $message);
        }

        $rcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return array($rbody, $rcode);
    }

    /**
     * @param integer $errno
     * @param string $message
     */
    private function _handleCurlError($errno, $message)
    {
        $supportEmail = 'support@youhosting.com';
        $apiBase      = $this->apiBase;
        switch ($errno) {
            case CURLE_COULDNT_CONNECT:
            case CURLE_COULDNT_RESOLVE_HOST:
            case CURLE_OPERATION_TIMEOUTED:
                $msg = "Could not connect to Youhosting ($apiBase).  Please check your internet connection and try again.  If this problem persists, you should check YouHosting's service status at https://twitter.com/youhostingstatus, or let us know at $supportEmail.";
                break;
            case CURLE_SSL_CACERT:
            case CURLE_SSL_PEER_CERTIFICATE:
                $msg = "Could not verify Youhosting's SSL certificate.  Please make sure that your network is not intercepting certificates.  (Try going to $apiBase in your browser.)  If this problem persists, let us know at $supportEmail.";
                break;
            default:
                $msg = "Unexpected error communicating with Youhosting.  If this problem persists, let us know at $supportEmail.";
        }

        $msg .= "\n\n(Network error [errno $errno]: $message)";
        throw new \Exception($msg, (int)$errno);
    }

    private function _utf8($value)
    {
        if (is_string($value))
            return utf8_encode($value);
        else
            return $value;
    }

    private function _encode($d)
    {
        return http_build_query($d, null, '&');
    }
}

class Youhosting_Api_V1_Captcha extends Youhosting_Api_V1
{
    public function generate()
    {
        return $this->_post('captcha');
    }

    public function verify($params)
    {
        return $this->_post('captcha/' . $params['id'], $params);
    }
}

class Youhosting_Api_V1_Client extends Youhosting_Api_V1
{
    public function create($params)
    {
        return $this->_post('client', $params);
    }

    public function get($params)
    {
        return $this->_get('client/' . $params['id'], $params);
    }

    public function getList($params)
    {
        return $this->_get('client/list', $params);
    }

    public function getLoginUrl($params)
    {
        return $this->_get('client/' . $params['id'] . '/login-url', $params);
    }
}

class Youhosting_Api_V1_Account extends Youhosting_Api_V1
{
    public function check($params)
    {
        return $this->_get('account/check', $params);
    }

    public function create($params)
    {
        return $this->_post('account', $params);
    }

    public function get($params)
    {
        return $this->_get('account/' . $params['id'], $params);
    }

    public function getList($params)
    {
        return $this->_get('account/list', $params);
    }

    public function suspend($params)
    {
        return $this->_post('account/' . $params['id'] . '/suspend', $params);
    }

    public function unsuspend($params)
    {
        return $this->_post('account/' . $params['id'] . '/unsuspend', $params);
    }

    public function delete($params)
    {
        return $this->_delete('account/' . $params['id'], $params);
    }

    public function getLoginUrl($params)
    {
        return $this->_get('account/' . $params['id'] . '/login-url', $params);
    }
}

class Youhosting_Api_V1_Settings extends Youhosting_Api_V1
{
    public function plans()
    {
        return $this->_get('settings/plans');
    }

    public function subdomains()
    {
        return $this->_get('settings/subdomains');
    }

    public function nameservers()
    {
        return $this->_get('settings/nameservers');
    }
}
