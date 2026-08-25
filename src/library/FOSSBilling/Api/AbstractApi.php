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

use FOSSBilling\Exception\BaseException;
use FOSSBilling\Interfaces\InjectionAwareInterface;
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

    protected ?object $service = null;

    /**
     * @var \Box\Mod\Client\Entity\Client|\Box\Mod\Staff\Entity\Admin|\FOSSBilling\Identity\Guest
     */
    protected $identity;

    protected ?Container $di = null;

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
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
            throw new BaseException('Mod object is not set for the service');
        }

        return $this->mod;
    }

    /**
     * @param \Box\Mod\Client\Entity\Client|\Box\Mod\Staff\Entity\Admin|\FOSSBilling\Identity\Guest $identity
     */
    public function setIdentity($identity): void
    {
        $this->identity = $identity;
    }

    /**
     * @return \Box\Mod\Client\Entity\Client|\Box\Mod\Staff\Entity\Admin|\FOSSBilling\Identity\Guest
     */
    public function getIdentity()
    {
        return $this->identity;
    }

    public function setService(object $service): void
    {
        $this->service = $service;
    }

    public function getService(): object
    {
        if ($this->service === null) {
            throw new BaseException('Service object is not set for the API');
        }

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

    protected function checkCaptchaIfEnabled(array $data): void
    {
        $extensionService = $this->getDi()['mod_service']('extension');
        if (!$extensionService->isExtensionActive('mod', 'antispam')) {
            return;
        }

        $this->getDi()['mod_service']('Antispam')->checkCaptcha($data);
    }
}
