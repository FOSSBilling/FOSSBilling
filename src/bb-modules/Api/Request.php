<?php


namespace Box\Mod\Api;

use Box\InjectionAwareInterface;

class Request implements InjectionAwareInterface {


    /**
     * @var \Box_Di
     */
    protected $di = null;

    /**
     * @var array
     */
    private $_request = array();

    /**
     * @param array $request
     */
    public function setRequest(array $request)
    {
        $this->_request = $request;
    }

    /**
     * @return array
     */
    public function getRequest()
    {
        return $this->_request;
    }

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
     * @param string $name
     * @param null $defaultValue
     * @return array|null
     */
    public function get($name = '', $defaultValue = null)
    {
        if (empty($name)) {
            return $this->_request;
        }
        $value = isset($this->_request[$name]) ? $this->_request[$name] : $defaultValue;
        return $value;
    }
}