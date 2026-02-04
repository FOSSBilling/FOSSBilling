<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\InjectionAwareInterface;

final class Api_Handler implements InjectionAwareInterface
{
    protected string|array $type;
    protected $ip;
    protected ?Pimple\Container $di = null;

    /**
     * @var bool When true, ACL permission denials are reported as exceptions.
     *           Defaults to true.
     */
    private bool $_acl_exception = true;

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
        if (!str_contains((string) $method, '_')) {
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

        // Check if this is a product type API call (starts with "service")
        $productTypeCode = null;
        if (str_starts_with($mod, 'service') && isset($this->di['product_type_registry'])) {
            $code = substr($mod, strlen('service'));
            if (!empty($code)) {
                $registry = $this->di['product_type_registry'];
                if ($registry->has($code)) {
                    $productTypeCode = $code;
                }
            }
        }

        $extensionService = $this->di['mod']('extension')->getService();
        $isModuleActive = $extensionService->isExtensionActive('mod', $mod);

        // If not a module and not a product type, throw error
        if (!$isModuleActive && $productTypeCode === null) {
            throw new FOSSBilling\Exception('FOSSBilling module :mod is not installed/activated', [':mod' => $mod], 715);
        }

        // permissions check
        if ($this->type == 'admin') {
            $staff_service = $this->di['mod_service']('Staff');
            $hasPermission = false;

            if ($productTypeCode !== null) {
                // Product type permission check
                $registry = $this->di['product_type_registry'];
                $permissionKey = $registry->getPermissionKey($productTypeCode);
                $hasPermission = $staff_service->hasPermission($this->identity, $permissionKey);
            } else {
                // Standard module permission check
                $hasPermission = $staff_service->hasPermission($this->identity, $mod);
            }

            if (!$hasPermission) {
                if ($this->_acl_exception) {
                    throw new FOSSBilling\Exception('You do not have access to the :mod module', [':mod' => $mod], 725);
                }
                if (DEBUG) {
                    error_log('You do not have access to ' . $mod . ' module');
                }

                return null;
            }
        }

        // Route to product type API or standard module API
        if ($productTypeCode !== null) {
            return $this->callProductTypeApi($productTypeCode, $method_name, $arguments);
        }

        $api_class = '\Box\Mod\\' . ucfirst($mod) . '\Api\\' . ucfirst((string) $this->type);

        $api = new $api_class();

        if (!$api instanceof Api_Abstract) {
            throw new FOSSBilling\Exception('Api class must be an instance of Api_Abstract', null, 730);
        }

        $bb_mod = $this->di['mod']($mod);

        $api->setDi($this->di);
        $api->setMod($bb_mod);
        $api->setIdentity($this->identity);
        $api->setIp($this->di['request']->getClientIp());
        if ($bb_mod->hasService()) {
            $api->setService($this->di['mod_service']($mod));
        }

        if (!method_exists($api, $method_name) || !is_callable([$api, $method_name])) {
            $reflector = new ReflectionClass($api);
            if (!$reflector->hasMethod('__call')) {
                throw new FOSSBilling\Exception(':type API call :method does not exist in module :module', [':type' => ucfirst((string) $this->type), ':method' => $method_name, ':module' => $mod], 740);
            }
        }

        $data = is_array($arguments) ? $arguments : [];

        $this->validateRequiredParams($api, $method_name, $data);
        $this->validateRequiredRole($api, $method_name);

        return $api->{$method_name}($arguments);
    }

    /**
     * Validate required parameters for an API method using attributes.
     *
     * @param Api_Abstract $api         The API instance
     * @param string       $method_name The method name
     * @param array        $data        The data array passed to the method
     *
     * @throws FOSSBilling\InformationException If required parameters are missing
     */
    public function validateRequiredParams(Api_Abstract $api, string $method_name, array $data): void
    {
        try {
            $reflection = new ReflectionMethod($api, $method_name);
        } catch (ReflectionException) {
            // Method doesn't exist, skip validation
            return;
        }

        $attributes = $reflection->getAttributes(FOSSBilling\Validation\Api\RequiredParams::class);

        if (empty($attributes)) {
            return; // No validation attributes found
        }

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            foreach ($instance->params as $paramName => $errorMessage) {
                if (!isset($data[$paramName])) {
                    throw new FOSSBilling\InformationException($errorMessage);
                }

                if (is_string($data[$paramName]) && strlen(trim($data[$paramName])) === 0) {
                    throw new FOSSBilling\InformationException($errorMessage);
                }

                if (!is_numeric($data[$paramName]) && empty($data[$paramName])) {
                    throw new FOSSBilling\InformationException($errorMessage);
                }
            }
        }
    }

    /**
     * Validate required role for an API method using attributes.
     *
     * @param Api_Abstract $api         The API instance
     * @param string       $method_name The method name
     *
     * @throws FOSSBilling\Exception If the current role is not allowed
     */
    public function validateRequiredRole(Api_Abstract $api, string $method_name): void
    {
        try {
            $reflection = new ReflectionMethod($api, $method_name);
        } catch (ReflectionException) {
            // Method doesn't exist, skip validation
            return;
        }

        $attributes = $reflection->getAttributes(FOSSBilling\Validation\Api\RequiredRole::class);

        // If no RequiredRole attribute, allow access (backward compatible behavior)
        if (empty($attributes)) {
            return;
        }

        // Get current role from identity
        $currentRole = match (true) {
            $this->identity instanceof Model_Admin => 'admin',
            $this->identity instanceof Model_Client => 'client',
            default => 'guest',
        };

        $allowedRoles = $attributes[0]->newInstance()->roles;

        if (!in_array($currentRole, $allowedRoles, true)) {
            throw new FOSSBilling\Exception('Method :method requires one of these roles: :roles. Current role: :current', [':method' => $method_name, ':roles' => implode(', ', $allowedRoles), ':current' => $currentRole]);
        }
    }

    /**
     * Call a product type API method.
     *
     * @param string $code      The product type code (e.g., 'domain', 'apikey')
     * @param string $method    The method name to call
     * @param mixed  $arguments The arguments to pass to the method
     *
     * @return mixed The result of the API call
     *
     * @throws FOSSBilling\Exception If the API class is not found or the method doesn't exist
     */
    private function callProductTypeApi(string $code, string $method, $arguments)
    {
        $registry = $this->di['product_type_registry'];

        // Get the API definition for this product type and role
        $typeKey = strtolower($this->type);

        try {
            $apiDefinition = $registry->getApiDefinition($code);
        } catch (Throwable) {
            $apiDefinition = null;
        }

        if ($apiDefinition === null) {
            throw new FOSSBilling\Exception('Product type :code does not expose :type API', [':code' => $code, ':type' => $this->type], 715);
        }

        $apiClass = $apiDefinition['class'];

        if (!class_exists($apiClass)) {
            throw new FOSSBilling\Exception('API class :class not found for product type :code', [':class' => $apiClass, ':code' => $code], 730);
        }

        $api = new $apiClass();

        if (!$api instanceof Api_Abstract) {
            throw new FOSSBilling\Exception('API class must be an instance of Api_Abstract', null, 730);
        }

        // Set up the API instance
        $api->setDi($this->di);
        $api->setIdentity($this->identity);
        $api->setIp($this->di['request']->getClientIp());

        // Set the service/handler
        $handler = $registry->getHandler($code);
        if (method_exists($api, 'setService')) {
            $api->setService($handler);
        }

        if (!str_starts_with($method, $typeKey . '_')) {
            throw new FOSSBilling\Exception('Product type :code API calls must be prefixed with :prefix', [':code' => $code, ':prefix' => $typeKey . '_'], 740);
        }
        $methodToCall = $method;

        // Check if method exists
        if (!method_exists($api, $methodToCall) || !is_callable([$api, $methodToCall])) {
            $reflector = new ReflectionClass($api);
            if (!$reflector->hasMethod('__call')) {
                throw new FOSSBilling\Exception(':type API call :method does not exist for product type :code', [':type' => ucfirst((string) $this->type), ':method' => $methodToCall, ':code' => $code], 740);
            }
        }

        $data = is_array($arguments) ? $arguments : [];
        $this->validateRequiredParams($api, $methodToCall, $data);
        $this->validateRequiredRole($api, $methodToCall);

        return $api->{$methodToCall}($arguments);
    }
}
