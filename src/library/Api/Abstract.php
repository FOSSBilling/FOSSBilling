<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\InjectionAwareInterface;

class Api_Abstract implements InjectionAwareInterface
{
    /**
     * @var string - request ip
     */
    protected $ip;

    /**
     * @var Box_Mod
     */
    protected $mod;

    // TODO: Find a way to correctly set the type. Maybe a module's service should extend a "Service" class?
    protected $service;

    /**
     * @var Model_Admin|Model_Client|Model_Guest
     */
    protected $identity;

    protected ?Pimple\Container $di = null;

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    /**
     * @param Box_Mod $mod
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
        if (!$this->mod) {
            throw new FOSSBilling\Exception('Mod object is not set for the service');
        }

        return $this->mod;
    }

    /**
     * @param Model_Admin|Model_Client|Model_Guest $identity
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

    // TODO: Find a way to correctly set the type. Maybe a module's service should extend a "Service" class?
    public function setService($service)
    {
        $this->service = $service;
    }

    // TODO: Find a way to correctly set the type. Maybe a module's service should extend a "Service" class?
    public function getService()
    {
        return $this->service;
    }

    /**
     * @param string $ip
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
