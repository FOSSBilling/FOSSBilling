<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Api;

use FOSSBilling\Exception;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Module;
use Pimple\Container;

class AbstractApi implements InjectionAwareInterface
{
    /**
     * @var string - request ip
     */
    protected $ip;

    /**
     * @var Module
     */
    protected $mod;

    // TODO: Find a way to correctly set the type. Maybe a module's service should extend a "Service" class?
    protected $service;

    /**
     * @var \Model_Admin|\Model_Client|\Model_Guest
     */
    protected $identity;

    protected ?Container $di = null;

    public function __construct()
    {
        if (function_exists('Tests\Helpers\container')) {
            $this->di = \Tests\Helpers\container();
        }
    }

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        if ($this->di === null && function_exists('Tests\Helpers\container')) {
            $this->di = \Tests\Helpers\container();
        }

        return $this->di;
    }

    /**
     * @param Module $mod
     */
    public function setMod($mod): void
    {
        $this->mod = $mod;
    }

    /**
     * @return Module
     */
    public function getMod()
    {
        // @phpstan-ignore isset.property (Runtime check to ensure mod is set)
        if (!isset($this->mod)) {
            throw new Exception('Mod object is not set for the service');
        }

        return $this->mod;
    }

    /**
     * @param \Model_Admin|\Model_Client|\Model_Guest $identity
     */
    public function setIdentity($identity): void
    {
        $this->identity = $identity;
    }

    /**
     * @return \Model_Admin|\Model_Client|\Model_Guest
     */
    public function getIdentity()
    {
        return $this->identity;
    }

    // TODO: Find a way to correctly set the type. Maybe a module's service should extend a "Service" class?
    public function setService($service): void
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
    public function setIp($ip): void
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

    // Wraps checkPermissionsAndThrowException, always forwarding $this->identity so cron/IPN contexts work without an active session.
    protected function checkPermissions(string $module, ?string $key = null, mixed $constraint = null): void
    {
        $this->getDi()['mod_service']('Staff')->checkPermissionsAndThrowException($module, $key, $constraint, $this->identity);
    }
}
