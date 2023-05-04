<?php
/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE.
 */

use \FOSSBilling\InjectionAwareInterface;

class Api_Abstract implements InjectionAwareInterface
{
    /**
     * @var string - request ip
     */
    protected $ip = null;

    /**
     * @var \Box_Mod
     */
    protected $mod  = null;

    /**
     * @var \Box\Mod\X\Service
     */
    protected $service  = null;

    /**
     * @var Model_Admin | Model_Client | Model_Guest
     */
    protected $identity = null;

    protected ?\Pimple\Container $di;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
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
}
