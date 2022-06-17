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

use Box\InjectionAwareInterface;

class Box_App
{
    protected $mappings = [];
    protected $before_filters = [];
    protected $after_filters = [];
    protected $shared = [];
    protected $options;
    protected $di = null;
    protected $ext = 'html.twig';
    protected $mod = 'index';
    protected $url = '/';

    public $uri = null;

    public function __construct($options = [])
    {
        $this->options = new ArrayObject($options);
    }

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    protected function registerModule()
    {
        // bind module urls and process
        // determine module and bind urls
        $requestUri = $this->url;
        if (empty($requestUri)) {
            $requestUri = '/';
        }
        if ('/' == $requestUri) {
            $mod = 'index';
        } else {
            $requestUri = trim($requestUri, '/');
            if (false === strpos($requestUri, '/')) {
                $mod = $requestUri;
            } else {
                [$mod] = explode('/', $requestUri);
            }
        }
        $mod = filter_var($mod, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

        $this->mod = $mod;
        $this->uri = $requestUri;
    }

    protected function init()
    {
    }

    public function show404(Exception $e)
    {
        error_log($e->getMessage());
        header('HTTP/1.0 404 Not Found');

        return $this->render('404', ['exception' => $e]);
    }

    /**
     * @param string $url
     * @param string $methodName
     * @param string $class
     */
    public function get($url, $methodName, $conditions = [], $class = null)
    {
        $this->event('get', $url, $methodName, $conditions, $class);
    }

    /**
     * @param string $url
     * @param string $methodName
     * @param string $class
     */
    public function post($url, $methodName, $conditions = [], $class = null)
    {
        $this->event('post', $url, $methodName, $conditions, $class);
    }

    public function put($url, $methodName, $conditions = [], $class = null)
    {
        $this->event('put', $url, $methodName, $conditions, $class);
    }

    public function delete($url, $methodName, $conditions = [], $class = null)
    {
        $this->event('delete', $url, $methodName, $conditions, $class);
    }

    public function before($methodName, $filterName)
    {
        $this->push_filter($this->before_filters, $methodName, $filterName);
    }

    public function after($methodName, $filterName)
    {
        $this->push_filter($this->after_filters, $methodName, $filterName);
    }

    protected function push_filter(&$arr_filter, $methodName, $filterName)
    {
        if (!is_array($methodName)) {
            $methodName = explode('|', $methodName);
        }

        $counted = count($methodName);
        for ($i = 0; $i < $counted; ++$i) {
            $method = $methodName[$i];
            if (!isset($arr_filter[$method])) {
                $arr_filter[$method] = [];
            }
            array_push($arr_filter[$method], $filterName);
        }
    }

    protected function run_filter($arr_filter, $methodName)
    {
        if (isset($arr_filter[$methodName])) {
            $counted = count($arr_filter[$methodName]);
            for ($i = 0; $i < $counted; ++$i) {
                $return = call_user_func([$this, $arr_filter[$methodName][$i]]);

                if (!is_null($return)) {
                    return $return;
                }
            }
        }
    }

    public function run()
    {
        $this->registerModule();
        $this->init();

        return $this->processRequest();
    }

    /**
     * @param string $path
     */
    public function redirect($path)
    {
        $location = $this->di['url']->link($path);
        header("Location: $location");
        exit;
    }

    /**
     * @param string $fileName
     */
    public function render($fileName, $variableArray = [])
    {
        echo 'Rendering ' . $fileName;
    }

    public function sendFile($filename, $contentType, $path)
    {
        header("Content-type: $contentType");
        header("Content-Disposition: attachment; filename=$filename");

        return readfile($path);
    }

    public function sendDownload($filename, $path)
    {
        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream');
        header('Content-Type: application/download');
        header('Content-Description: File Transfer');
        header("Content-Disposition: attachment; filename=$filename" . ';');
        header('Content-Transfer-Encoding: binary');

        return readfile($path);
    }

    protected function executeShared($classname, $methodName, $params)
    {
        $class = new $classname();
        if ($class instanceof InjectionAwareInterface) {
            $class->setDi($this->di);
        }
        $reflection = new ReflectionMethod(get_class($class), $methodName);
        $args = [];
        $args[] = $this; // first param always app instance

        foreach ($reflection->getParameters() as $param) {
            if (isset($params[$param->name])) {
                $args[$param->name] = $params[$param->name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[$param->name] = $param->getDefaultValue();
            }
        }

        return $reflection->invokeArgs($class, $args);
    }

    protected function execute($methodName, $params, $classname = null)
    {
        $return = $this->run_filter($this->before_filters, $methodName);
        if (!is_null($return)) {
            return $return;
        }

        $reflection = new ReflectionMethod(get_class($this), $methodName);
        $args = [];

        foreach ($reflection->getParameters() as $param) {
            if (isset($params[$param->name])) {
                $args[$param->name] = $params[$param->name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[$param->name] = $param->getDefaultValue();
            }
        }

        $response = $reflection->invokeArgs($this, $args);

        $return = $this->run_filter($this->after_filters, $methodName);
        if (!is_null($return)) {
            return $return;
        }

        return $response;
    }

    /**
     * @param string $httpMethod
     */
    protected function event($httpMethod, $url, $methodName, $conditions = [], $classname = null)
    {
        if (method_exists($this, $methodName)) {
            array_push($this->mappings, [$httpMethod, $url, $methodName, $conditions]);
        }
        if (null !== $classname) {
            array_push($this->shared, [$httpMethod, $url, $methodName, $conditions, $classname]);
        }
    }

    /**
     * Check if the requested URL is in the allowlist.
     *
     * @since 4.22.0
     */
    protected function checkAllowedURLs()
    {
        $REQUEST_URI = $this->di['request']->getServer('REQUEST_URI');
        $allowedURLs = $this->di['config']['maintenance_mode']['allowed_urls'];
        $rootUrl = $this->di['config']['url'];

        // Allow access to the staff panel all the time
        $adminApiPrefixes = [
            '/api/guest/staff/login',
            '/api/admin',
        ];

        foreach ($adminApiPrefixes as $adminApiPrefix) {
            $realAdminApiUrl = '/' === $rootUrl[-1] ? substr($rootUrl, 0, -1) . $adminApiPrefix : $rootUrl . $adminApiPrefix;
            $allowedURLs[] = parse_url($realAdminApiUrl)['path'];
        }
        foreach ($allowedURLs as $url) {
            if (0 !== preg_match('/^' . str_replace('/', '\/', $url) . '(.*)/', $REQUEST_URI)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the visitor IP is in the allowlist.
     *
     * @since 4.22.0
     */
    protected function checkAllowedIPs()
    {
        $allowedIPs = $this->di['config']['maintenance_mode']['allowed_ips'];
        $visitorIP = $this->di['request']->getClientAddress();

        // Check if the visitor is in using of the allowed IPs/networks
        foreach ($allowedIPs as $network) {
            if (false == strpos($network, '/')) {
                $network .= '/32';
            }
            [$network, $netmask] = explode('/', $network, 2);
            $network_decimal = ip2long($network);
            $ip_decimal = ip2long($visitorIP);
            $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
            $netmask_decimal = ~$wildcard_decimal;
            if (($ip_decimal & $netmask_decimal) == ($network_decimal & $netmask_decimal)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the requested URL is a part of the admin area.
     *
     * @since 4.22.0
     */
    protected function checkAdminPrefix()
    {
        $REQUEST_URI = $this->di['request']->getServer('REQUEST_URI');
        $adminPrefix = $this->di['config']['admin_area_prefix'];
        $rootUrl = $this->di['config']['url'];

        $realAdminUrl = '/' === $rootUrl[-1] ? substr($rootUrl, 0, -1) . $adminPrefix : $rootUrl . $adminPrefix;
        $realAdminPath = parse_url($realAdminUrl)['path'];

        if (0 !== preg_match('/^' . str_replace('/', '\/', $realAdminPath) . '(.*)/', $REQUEST_URI)) {
            return false;
        }

        return true;
    }

    protected function processRequest()
    {
        /**
         * Block requests if the system is undergoing maintenance.
         * It will respect any URL/IP whitelisting under the configuration file.
         *
         * @since 4.22.0
         */
        if (true === $this->di['config']['maintenance_mode']['enabled']) {
            // Check the allowlists
            if ($this->checkAdminPrefix() && $this->checkAllowedURLs() && $this->checkAllowedIPs()) {
                // Set response code to 503.
                header('HTTP/1.0 503 Service Unavailable');

                if ('api' == $this->mod) {
                    $exc = new \Box_Exception('The system is undergoing maintenance. Please try again later.', [], 503);

                    return (new \Box\Mod\Api\Controller\Client())->renderJson(null, $exc);
                } else {
                    return $this->render('mod_system_maintenance');
                }
            }
        }

        $sharedCount = count($this->shared);
        for ($i = 0; $i < $sharedCount; ++$i) {
            $mapping = $this->shared[$i];
            $url = new Box_UrlHelper($mapping[0], $mapping[1], $mapping[3], $this->url);
            if ($url->match) {
                return $this->executeShared($mapping[4], $mapping[2], $url->params);
            }
        }

        // this class mappings
        $mappingsCount = count($this->mappings);
        for ($i = 0; $i < $mappingsCount; ++$i) {
            $mapping = $this->mappings[$i];
            $url = new Box_UrlHelper($mapping[0], $mapping[1], $mapping[3], $this->url);
            if ($url->match) {
                return $this->execute($mapping[2], $url->params);
            }
        }

        $e = new \Box_Exception('Page :url not found', [':url' => $this->url], 404);

        return $this->show404($e);
    }

    /**
     * @deprecated
     */
    public function getApiAdmin()
    {
        return $this->di['api_admin'];
    }

    /**
     * @deprecated
     */
    public function getApiClient()
    {
        return $this->di['api_client'];
    }

    /**
     * @deprecated
     */
    public function getApiGuest()
    {
        return $this->di['api_guest'];
    }
}
