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

final class Api_Handler implements InjectionAwareInterface
{
    protected $type;
    protected $ip;
    protected ?Pimple\Container $di = null;

    private bool $_acl_exception = false;

    public function __construct(protected $identity)
    {
        $role = str_replace('model_', '', strtolower($identity::class));
        $this->type = $role;
    }

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    public function __call($method, $arguments)
    {
        if (!str_contains($method, '_')) {
            throw new FOSSBilling\Exception('Method :method must contain underscore', [':method' => $method], 710);
        }

        if (isset($arguments[0])) {
            $arguments = $arguments[0];
        }

        $e = explode('_', $method);
        $mod = strtolower($e[0]);
        unset($e[0]);
        $method_name = implode('_', $e);

        if (empty($mod)) {
            throw new FOSSBilling\Exception('Invalid module name', null, 714);
        }

        $service = $this->di['mod']('extension')->getService();

        if (!$service->isExtensionActive('mod', $mod)) {
            throw new FOSSBilling\Exception('FOSSBilling module :mod is not installed/activated', [':mod' => $mod], 715);
        }

        // permissions check
        if ($this->type == 'admin') {
            $staff_service = $this->di['mod_service']('Staff');
            if (!$staff_service->hasPermission($this->identity, $mod)) {
                if ($this->_acl_exception) {
                    throw new FOSSBilling\Exception('You do not have access to the :mod module', [':mod' => $mod], 725);
                } else {
                    if (DEBUG) {
                        error_log('You do not have access to ' . $mod . ' module');
                    }

                    return null;
                }
            }
        }

        $api_class = '\Box\Mod\\' . ucfirst($mod) . '\\Api\\' . ucfirst($this->type);

        $api = new $api_class();

        if (!$api instanceof Api_Abstract) {
            throw new FOSSBilling\Exception('Api class must be an instance of Api_Abstract', null, 730);
        }

        $bb_mod = $this->di['mod']($mod);

        $api->setDi($this->di);
        $api->setMod($bb_mod);
        $api->setIdentity($this->identity);
        $api->setIp($this->di['request']->getClientAddress());
        if ($bb_mod->hasService()) {
            $api->setService($this->di['mod_service']($mod));
        }

        if (!method_exists($api, $method_name) || !is_callable([$api, $method_name])) {
            $reflector = new ReflectionClass($api);
            if (!$reflector->hasMethod('__call')) {
                throw new FOSSBilling\Exception(':type API call :method does not exist in module :module', [':type' => ucfirst($this->type), ':method' => $method_name, ':module' => $mod], 740);
            }
        }

        return $api->{$method_name}($arguments);
    }
}
