<?php

/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use DebugBar\StandardDebugBar;
use FOSSBilling\Config;
use FOSSBilling\InjectionAwareInterface;

class Box_App
{
    protected $mappings = [];
    protected $before_filters = [];
    protected $after_filters = [];
    protected $shared = [];
    protected $options;
    protected ?Pimple\Container $di = null;
    protected $ext = 'html.twig';
    protected $mod = 'index';
    protected $url = '/';
    protected StandardDebugBar $debugBar;

    public $uri;

    public function __construct($options = [], StandardDebugBar $debugBar = null)
    {
        $this->options = new ArrayObject($options);

        if (!$debugBar) {
            $this->debugBar = new StandardDebugBar();
        } else {
            $this->debugBar = $debugBar;
        }
    }

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function getDebugBar(): StandardDebugBar
    {
        return $this->debugBar;
    }

    protected function registerModule()
    {
        // bind module urls and process
        // determine module and bind urls
        $requestUri = $this->url;
        if (empty($requestUri)) {
            $requestUri = '/';
        }
        if ($requestUri == '/') {
            $mod = 'index';
        } else {
            $requestUri = trim($requestUri, '/');
            if (!str_contains($requestUri, '/')) {
                $mod = $requestUri;
            } else {
                [$mod] = explode('/', $requestUri);
            }
        }
        $mod = htmlspecialchars($mod);

        $this->mod = $mod;
        $this->uri = $requestUri;
    }

    protected function init()
    {
    }

    protected function checkPermission()
    {
    }

    public function show404(Exception $e)
    {
        $this->di['logger']->setChannel('routing')->info($e->getMessage());
        http_response_code(404);

        return $this->render('error', ['exception' => $e]);
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
            $arr_filter[$method][] = $filterName;
        }
    }

    protected function run_filter($arr_filter, $methodName)
    {
        if (isset($arr_filter[$methodName])) {
            $counted = is_countable($arr_filter[$methodName]) ? count($arr_filter[$methodName]) : 0;
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
        $this->debugBar['time']->startMeasure('registerModule', 'Registering module routes');
        $this->registerModule();
        $this->debugBar['time']->stopMeasure('registerModule');

        $this->debugBar['time']->startMeasure('init', 'Initializing the app');
        $this->init();
        $this->debugBar['time']->stopMeasure('init');

        $this->debugBar['time']->startMeasure('checkperm', 'Checking access to module');
        $this->checkPermission();
        $this->debugBar['time']->stopMeasure('checkperm');

        return $this->processRequest();
    }

    /**
     * @param string $path
     */
    public function redirect($path): never
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
        $this->debugBar['time']->startMeasure('executeShared', 'Reflecting module controller (shared mapping)');
        $class = new $classname();
        if ($class instanceof InjectionAwareInterface) {
            $class->setDi($this->di);
        }
        $reflection = new ReflectionMethod($class::class, $methodName);
        $args = [];
        $args[] = $this; // first param always app instance

        foreach ($reflection->getParameters() as $param) {
            if (isset($params[$param->name])) {
                $args[$param->name] = $params[$param->name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[$param->name] = $param->getDefaultValue();
            }
        }
        $this->debugBar['time']->stopMeasure('executeShared');

        return $reflection->invokeArgs($class, $args);
    }

    protected function execute($methodName, $params, $classname = null)
    {
        $this->debugBar['time']->startMeasure('execute', 'Reflecting module controller');
        $return = $this->run_filter($this->before_filters, $methodName);
        if (!is_null($return)) {
            return $return;
        }

        $reflection = new ReflectionMethod(static::class, $methodName);
        $args = [];

        foreach ($reflection->getParameters() as $param) {
            if (isset($params[$param->name])) {
                $args[$param->name] = $params[$param->name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[$param->name] = $param->getDefaultValue();
            }
        }

        $this->debugBar['time']->stopMeasure('execute');

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
            $this->mappings[] = [$httpMethod, $url, $methodName, $conditions];
        }
        if ($classname !== null) {
            $this->shared[] = [$httpMethod, $url, $methodName, $conditions, $classname];
        }
    }

    /**
     * Check if the requested URL is in the allowlist.
     *
     * @since 4.22.0
     */
    protected function checkAllowedURLs()
    {
        $REQUEST_URI = $_SERVER['REQUEST_URI'] ?? null;

        $allowedURLs = Config::getProperty('maintenance_mode.allowed_urls', []);

        // Allow access to the staff panel all the time
        $adminApiPrefixes = [
            '/api/guest/staff/login',
            '/api/admin',
            'api/admin',
            '/index.php?_url=/api/admin/',
        ];

        foreach ($adminApiPrefixes as $adminApiPrefix) {
            $realAdminApiUrl = SYSTEM_URL[-1] === '/' ? substr(SYSTEM_URL, 0, -1) . $adminApiPrefix : SYSTEM_URL . $adminApiPrefix;
            $allowedURLs[] = parse_url($realAdminApiUrl)['path'];
        }
        foreach ($allowedURLs as $url) {
            if (preg_match('/^' . str_replace('/', '\/', $url) . '(.*)/', $REQUEST_URI) !== 0) {
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
        $allowedIPs = Config::getProperty('maintenance_mode.allowed_ips', []);
        $visitorIP = $this->di['request']->getClientAddress();

        // Check if the visitor is in using of the allowed IPs/networks
        foreach ($allowedIPs as $network) {
            if (!str_contains($network, '/')) {
                $network .= '/32';
            }
            [$network, $netmask] = explode('/', $network, 2);
            $network_decimal = ip2long($network);
            $ip_decimal = ip2long($visitorIP);
            $wildcard_decimal = 2 ** (32 - (int) $netmask) - 1;
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
        $REQUEST_URI = $_SERVER['REQUEST_URI'] ?? null;

        $realAdminUrl = SYSTEM_URL[-1] === '/' ? substr(SYSTEM_URL, 0, -1) . ADMIN_PREFIX : SYSTEM_URL . ADMIN_PREFIX;
        $realAdminPath = parse_url($realAdminUrl)['path'];

        if (preg_match('/^' . str_replace('/', '\/', $realAdminPath) . '(.*)/', $REQUEST_URI) !== 0) {
            return false;
        }

        return true;
    }

    protected function processRequest()
    {
        /*
         * Block requests if the system is undergoing maintenance.
         * It will respect any URL/IP whitelisting under the configuration file.
         */
        if (Config::getProperty('maintenance_mode.enabled', false)) {
            // Check the allowlists
            if ($this->checkAdminPrefix() && $this->checkAllowedURLs() && $this->checkAllowedIPs()) {
                // Set response code to 503.
                header('HTTP/1.0 503 Service Unavailable');

                if ($this->mod == 'api') {
                    $exc = new FOSSBilling\InformationException('The system is undergoing maintenance. Please try again later', [], 503);
                    $apiController = new Box\Mod\Api\Controller\Client();
                    $apiController->setDi($this->di);

                    return $apiController->renderJson(null, $exc);
                } else {
                    return $this->render('mod_system_maintenance');
                }
            }
        }

        $this->debugBar['time']->startMeasure('sharedMapping', 'Checking shared mappings');
        $sharedCount = count($this->shared);
        for ($i = 0; $i < $sharedCount; ++$i) {
            $mapping = $this->shared[$i];
            $url = new Box_UrlHelper($mapping[0], $mapping[1], $mapping[3], $this->url);
            if ($url->match) {
                $this->debugBar['time']->stopMeasure('sharedMapping');

                return $this->executeShared($mapping[4], $mapping[2], $url->params);
            }
        }
        $this->debugBar['time']->stopMeasure('sharedMapping');

        // this class mappings
        $this->debugBar['time']->startMeasure('mapping', 'Checking mappings');
        $mappingsCount = count($this->mappings);
        for ($i = 0; $i < $mappingsCount; ++$i) {
            $mapping = $this->mappings[$i];
            $url = new Box_UrlHelper($mapping[0], $mapping[1], $mapping[3], $this->url);
            if ($url->match) {
                $this->debugBar['time']->stopMeasure('mapping');

                return $this->execute($mapping[2], $url->params);
            }
        }
        $this->debugBar['time']->stopMeasure('mapping');

        $e = new FOSSBilling\InformationException('Page :url not found', [':url' => $this->url], 404);

        return $this->show404($e);
    }
}
