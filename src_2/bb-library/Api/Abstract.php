<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

use Box\InjectionAwareInterface;

class Api_Abstract implements InjectionAwareInterface
{
    /**
     * @var string - request ip
     */
    protected $ip           = null;

    /**
     * @var \Box_Mod
     */
    protected $mod          = null;

    /**
     * @var \Box\Mod\X\Service
     */
    protected $service      = null;

    /**
     * @var Model_Admin | Model_Client | Model_Guest
     */
    protected $identity     = null;

    /**
     * @var \Box_Di
     */
    protected $di           = null;

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
     * @param null $mod
     */
    public function setMod($mod)
    {
        $this->mod = $mod;
    }

    /**
     * @return Box_Mod
     */
    public function getMod()
    {
        if(!$this->mod) {
            throw new Box_Exception('Mod object is not set for the service');
        }
        return $this->mod;
    }

    /**
     * @param null $identity
     */
    public function setIdentity($identity)
    {
        $this->identity = $identity;
    }

    /**
     * @return Model_Admin
     */
    public function getIdentity()
    {
        return $this->identity;
    }

    /**
     * @param null $service
     */
    public function setService($service)
    {
        $this->service = $service;
    }

    /**
     * @return null
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * @param null $ip
     */
    public function setIp($ip)
    {
        $this->ip = $ip;
    }

    /**
     * @return string
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @deprecated use DI
     */
    public function getApiAdmin()
    {
        if($this->identity instanceof \Model_Admin) {
            return $this->di['api_admin'];
        }
        return $this->di['api_system'];
    }

    /**
     * @deprecated use DI
     */
    public function getApiGuest()
    {
        return $this->di['api_guest'];
    }

    /**
     * @deprecated use DI
     */
    public function getApiClient()
    {
        return $this->di['api_client'];
    }
}