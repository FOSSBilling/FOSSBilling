<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Api;

use Api_Abstract;
use FOSSBilling\Exception;
use FOSSBilling\InformationException;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Validation\Api\RequiredParams;
use Pimple\Container;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

final class Dispatcher implements InjectionAwareInterface
{
    protected ?Container $di = null;

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    public function dispatch(object $identity, string $method, array $data = []): mixed
    {
        return $this->dispatchWithArguments($identity, $method, [$data]);
    }

    public function dispatchWithArguments(object $identity, string $method, array $arguments = []): mixed
    {
        if (!str_contains($method, '_')) {
            throw new Exception('Method :method must contain underscore', [':method' => $method], 710);
        }

        $role = str_replace('model_', '', strtolower($identity::class));
        $parts = explode('_', $method);
        $mod = strtolower($parts[0]);
        unset($parts[0]);
        $methodName = implode('_', $parts);

        if (empty($mod)) {
            throw new Exception('Invalid module name', null, 714);
        }

        $extensionService = $this->getDi()['mod']('extension')->getService();
        if (!$extensionService->isExtensionActive('mod', $mod)) {
            throw new Exception('FOSSBilling module :mod is not installed/activated', [':mod' => $mod], 715);
        }

        if ($role === 'admin') {
            $staffService = $this->getDi()['mod_service']('Staff');
            if (!$staffService->hasPermission($identity, $mod)) {
                throw new Exception('You do not have access to the :mod module', [':mod' => $mod], 725);
            }
        }

        $apiClass = '\Box\Mod\\' . ucfirst($mod) . '\Api\\' . ucfirst($role);
        if (!class_exists($apiClass)) {
            throw new Exception(':type API call :method does not exist in module :module', [':type' => ucfirst($role), ':method' => $methodName, ':module' => $mod], 740);
        }

        $api = new $apiClass();
        if (!$api instanceof Api_Abstract) {
            throw new Exception('Api class must be an instance of Api_Abstract', null, 730);
        }

        $module = $this->getDi()['mod']($mod);
        $api->setDi($this->di);
        $api->setMod($module);
        $api->setIdentity($identity);
        $api->setIp($this->getDi()['request']->getClientIp());
        if ($module->hasService()) {
            $api->setService($this->getDi()['mod_service']($mod));
        }

        if (!method_exists($api, $methodName) || !is_callable([$api, $methodName])) {
            $reflector = new ReflectionClass($api);
            if (!$reflector->hasMethod('__call')) {
                throw new Exception(':type API call :method does not exist in module :module', [':type' => ucfirst($role), ':method' => $methodName, ':module' => $mod], 740);
            }
        }

        $data = isset($arguments[0]) && is_array($arguments[0]) ? $arguments[0] : [];
        $this->validateRequiredParams($api, $methodName, $data);

        return $api->{$methodName}(...$this->normalizeArguments($api, $methodName, $arguments));
    }

    /**
     * Validate required parameters for an API method using attributes.
     *
     * @param Api_Abstract $api        The API instance
     * @param string       $methodName The method name
     * @param array        $data       The data array passed to the method
     *
     * @throws InformationException If required parameters are missing
     */
    public function validateRequiredParams(Api_Abstract $api, string $methodName, array $data): void
    {
        try {
            $reflection = new ReflectionMethod($api, $methodName);
        } catch (ReflectionException) {
            return;
        }

        $attributes = $reflection->getAttributes(RequiredParams::class);

        if (empty($attributes)) {
            return;
        }

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            foreach ($instance->params as $paramName => $errorMessage) {
                if (!isset($data[$paramName])) {
                    throw new InformationException($errorMessage);
                }

                if (is_string($data[$paramName]) && strlen(trim($data[$paramName])) === 0) {
                    throw new InformationException($errorMessage);
                }

                if (!is_numeric($data[$paramName]) && empty($data[$paramName])) {
                    throw new InformationException($errorMessage);
                }
            }
        }
    }

    private function normalizeArguments(Api_Abstract $api, string $methodName, array $arguments): array
    {
        try {
            $reflection = new ReflectionMethod($api, $methodName);
        } catch (ReflectionException) {
            return $arguments;
        }

        if (!empty($arguments) && $arguments !== [[]]) {
            return $arguments;
        }

        $parameters = $reflection->getParameters();
        if (empty($parameters)) {
            return [];
        }

        $firstParameter = $parameters[0];
        if ($firstParameter->isOptional()) {
            if ($arguments === [[]] && $firstParameter->isDefaultValueAvailable() && is_array($firstParameter->getDefaultValue())) {
                return $arguments;
            }

            return [];
        }

        return [[]];
    }
}
