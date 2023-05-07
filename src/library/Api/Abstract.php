<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
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
     * @return
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
