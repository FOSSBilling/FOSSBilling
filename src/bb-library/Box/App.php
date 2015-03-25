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


use Box\InjectionAwareInterface;

class Box_App {

    protected $mappings       = array();
    protected $before_filters = array();
    protected $after_filters  = array();
    protected $shared         = array();
    protected $options;
    protected $di             = NULL;
    protected $ext            = 'phtml';
    protected $mod            = 'index';
    protected $url            = '/';

    public $uri = NULL;

    public function __construct($options=array())
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
        //bind module urls and process
        //determine module and bind urls
        $requestUri = $this->url;
        if(empty($requestUri)) {
            $requestUri = '/';
        }
        if($requestUri == '/') {
            $mod = 'index';
        } else {
            $requestUri = trim($requestUri, '/');
            if(strpos($requestUri, '/') === false) {
                $mod = $requestUri;
            } else {
                list($mod) = explode('/',$requestUri);
            }
        }
        $mod = filter_var($mod, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

        $this->mod = $mod;
        $this->uri = $requestUri;
    }

    protected function init(){}

    public function show404(Exception $e) {
        error_log($e->getMessage());
        header("HTTP/1.0 404 Not Found");
        return $this->render('404', array('exception'=>$e));
    }

    /**
     * @param string $url
     * @param string $methodName
     * @param string $class
     */
    public function get($url, $methodName, $conditions = array(), $class = null) {
       $this->event('get', $url, $methodName, $conditions, $class);
    }

    /**
     * @param string $url
     * @param string $methodName
     * @param string $class
     */
    public function post($url, $methodName, $conditions = array(), $class = null) {
       $this->event('post', $url, $methodName, $conditions, $class);
    }

    public function put($url, $methodName, $conditions = array(), $class = null) {
       $this->event('put', $url, $methodName, $conditions, $class);
    }

    public function delete($url, $methodName, $conditions = array(), $class = null) {
       $this->event('delete', $url, $methodName, $conditions, $class);
    }

    public function before($methodName, $filterName) {
        $this->push_filter($this->before_filters, $methodName, $filterName);
    }

    public function after($methodName, $filterName) {
        $this->push_filter($this->after_filters, $methodName, $filterName);
    }

    protected function push_filter(&$arr_filter, $methodName, $filterName) {
        if (!is_array($methodName)) {
            $methodName = explode('|', $methodName);
        }

        $counted = count($methodName);
        for ($i = 0; $i < $counted; $i++) {
            $method = $methodName[$i];
            if (!isset($arr_filter[$method])) {
                $arr_filter[$method] = array();
            }
            array_push($arr_filter[$method], $filterName);
        }
    }

    protected function run_filter($arr_filter, $methodName) {
        if(isset($arr_filter[$methodName])) {
            $counted = count($arr_filter[$methodName]);
            for ($i=0; $i < $counted; $i++) {
                $return = call_user_func(array($this, $arr_filter[$methodName][$i]));

                if(!is_null($return)) {
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
    public function render($fileName, $variableArray = array())
    {
        print 'Rendering '.$fileName;
    }

    public function sendFile($filename, $contentType, $path) {
        header("Content-type: $contentType");
        header("Content-Disposition: attachment; filename=$filename");
        return readfile($path);
    }

    public function sendDownload($filename, $path) {
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=$filename".";");
        header("Content-Transfer-Encoding: binary");
        return readfile($path);
    }

    protected function executeShared($classname, $methodName, $params)
    {
        $class = new $classname();
        if($class instanceof InjectionAwareInterface) {
            $class->setDi($this->di);
        }
        $reflection = new ReflectionMethod(get_class($class), $methodName);
        $args = array();
        $args[] = $this; // first param always app instance

        foreach($reflection->getParameters() as $param) {
            if(isset($params[$param->name])) {
                $args[$param->name] = $params[$param->name];
            }
            else if($param->isDefaultValueAvailable()) {
                $args[$param->name] = $param->getDefaultValue();
            }
        }

        return $reflection->invokeArgs($class, $args);
    }

    protected function execute($methodName, $params, $classname = null) {
        $return = $this->run_filter($this->before_filters, $methodName);
        if (!is_null($return)) {
          return $return;
        }

        $reflection = new ReflectionMethod(get_class($this), $methodName);
        $args = array();

        foreach($reflection->getParameters() as $param) {
            if(isset($params[$param->name])) {
                $args[$param->name] = $params[$param->name];
            }
            else if($param->isDefaultValueAvailable()) {
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
    protected function event($httpMethod, $url, $methodName, $conditions=array(), $classname = null) {
        if (method_exists($this, $methodName)) {
            array_push($this->mappings, array($httpMethod, $url, $methodName, $conditions));
        }
        if (null !== $classname) {
            array_push($this->shared, array($httpMethod, $url, $methodName, $conditions, $classname));
        }
    }

    protected function processRequest()
    {
        $sharedCount = count($this->shared);
        for($i = 0; $i < $sharedCount; $i++) {
            $mapping = $this->shared[$i];
            $url = new Box_UrlHelper($mapping[0], $mapping[1], $mapping[3], $this->url);
            if($url->match) {
                return $this->executeShared($mapping[4], $mapping[2], $url->params);
            }
        }

        // this class mappings
        $mappingsCount = count($this->mappings);
        for($i = 0; $i < $mappingsCount; $i++) {
            $mapping = $this->mappings[$i];
            $url = new Box_UrlHelper($mapping[0], $mapping[1], $mapping[3], $this->url);
            if($url->match) {
                return $this->execute($mapping[2], $url->params);
            }
        }

        $e = new \Box_Exception('Page :url not found', array(':url'=>$this->url), 404);
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