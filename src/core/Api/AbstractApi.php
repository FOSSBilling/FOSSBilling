<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Api;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Staff\Entity\Admin;
use FOSSBilling\Core\Container\InjectionAwareInterface;
use FOSSBilling\Core\Exception\BaseException;
use FOSSBilling\Core\Identity\Guest;
use FOSSBilling\Core\Module;
use Pimple\Container;

abstract class AbstractApi implements InjectionAwareInterface
{
    protected string $ip = '';
    protected ?Module $module = null;
    protected ?object $service = null;

    protected Client|Admin|Guest|null $identity = null;

    protected ?Container $di = null;

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    public function setModule(Module $module): void
    {
        $this->module = $module;
    }

    public function getModule(): Module
    {
        if ($this->module === null) {
            throw new BaseException('Module object is not set for the service');
        }

        return $this->module;
    }

    public function setIdentity(Client|Admin|Guest $identity): void
    {
        $this->identity = $identity;
    }

    public function getIdentity(): Client|Admin|Guest
    {
        if ($this->identity === null) {
            throw new BaseException('Identity is not set for the API');
        }

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

    public function setIp(string $ip): void
    {
        $this->ip = $ip;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    // Wraps checkPermissionsAndThrowException, always forwarding identity so cron/IPN contexts work without an active session.
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
