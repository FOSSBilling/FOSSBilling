<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Pimple\Container;
use Symfony\Component\Filesystem\Path;

class ProductTypeRegistry implements InjectionAwareInterface
{
    public const string MANIFEST_FILENAME = 'manifest.json';

    private const array DEFAULT_CAPABILITIES = [
        \Model_ClientOrder::ACTION_CREATE,
        \Model_ClientOrder::ACTION_ACTIVATE,
        \Model_ClientOrder::ACTION_RENEW,
        \Model_ClientOrder::ACTION_SUSPEND,
        \Model_ClientOrder::ACTION_UNSUSPEND,
        \Model_ClientOrder::ACTION_CANCEL,
        \Model_ClientOrder::ACTION_UNCANCEL,
        \Model_ClientOrder::ACTION_DELETE,
    ];

    private ?Container $di = null;

    /**
     * @var array<string, array>
     */
    private array $definitions = [];

    /**
     * @var array<string, object>
     */
    private array $handlers = [];

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    public function loadFromFilesystem(string $rootPath): void
    {
        if (!is_dir($rootPath)) {
            return;
        }

        $iterator = new \DirectoryIterator($rootPath);
        foreach ($iterator as $entry) {
            if (!$entry->isDir() || $entry->isDot()) {
                continue;
            }

            $manifestPath = Path::join($entry->getPathname(), self::MANIFEST_FILENAME);
            if (!is_file($manifestPath)) {
                continue;
            }

            try {
                $definition = $this->readManifest($manifestPath);
                $definition['base_path'] ??= $entry->getPathname();
                $definition['source'] ??= 'extensions';
                $this->registerDefinition($definition);
            } catch (\Throwable $exception) {
                $this->logManifestError($entry->getFilename(), $exception);
            }
        }
    }

    public function registerLegacyModule(string $moduleName, array $overrides = []): void
    {
        $normalized = strtolower($moduleName);
        $code = str_starts_with($normalized, 'service')
            ? substr($normalized, strlen('service'))
            : $normalized;

        if ($code === '') {
            throw new Exception('Legacy module name "%s" cannot be mapped to a product type.', [$moduleName]);
        }

        $definition = [
            'code' => $code,
            'label' => ucfirst($code),
            'templates' => [
                'order' => sprintf('mod_service%s_order.html.twig', $code),
                'manage' => sprintf('mod_service%s_manage.html.twig', $code),
            ],
            'source' => 'legacy',
            'legacy' => true,
            'legacy_module' => $normalized,
        ];

        $definition = array_replace($definition, $overrides);
        $this->registerDefinition($definition);
    }

    public function registerDefinition(array $definition): void
    {
        $definition = $this->normalizeDefinition($definition);
        $code = $definition['code'];

        if (isset($this->definitions[$code])) {
            $this->logOverride($code, $definition);
        }

        $this->definitions[$code] = $definition;
        unset($this->handlers[$code]);
    }

    public function has(string $code): bool
    {
        return isset($this->definitions[strtolower($code)]);
    }

    public function getDefinition(string $code): array
    {
        $code = strtolower($code);
        if (!isset($this->definitions[$code])) {
            throw new Exception('Product type "%s" is not registered.', [$code]);
        }

        return $this->definitions[$code];
    }

    public function getPairs(): array
    {
        $pairs = [];
        foreach ($this->definitions as $code => $definition) {
            $pairs[$code] = $definition['label'] ?? ucfirst($code);
        }

        return $pairs;
    }

    public function getPermissionKey(string $code): string
    {
        $code = strtolower($code);

        return 'product_type_' . $code;
    }

    /**
     * @return array<string, array>
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    public function getTemplate(string $code, string $key, ?string $fallback = null): string
    {
        $code = strtolower($code);
        $key = strtolower($key);

        try {
            $definition = $this->getDefinition($code);
        } catch (Exception) {
            if ($fallback !== null) {
                return $fallback;
            }

            return sprintf('mod_service%s_%s.html.twig', $code, $key);
        }

        $templates = $definition['templates'] ?? [];
        $template = is_array($templates) ? ($templates[$key] ?? null) : null;
        if (!is_string($template) || $template === '') {
            if ($fallback !== null) {
                return $fallback;
            }

            return sprintf('mod_service%s_%s.html.twig', $code, $key);
        }

        return $template;
    }

    public function getApiDefinition(string $code, string $role): ?array
    {
        $code = strtolower($code);
        $role = strtolower($role);

        $definition = $this->getDefinition($code);
        $api = $definition['api'] ?? null;
        if (!is_array($api)) {
            return null;
        }

        $entry = $api[$role] ?? null;
        if ($entry === null) {
            return null;
        }

        if (is_string($entry)) {
            $entry = ['class' => $entry];
        }

        if (!is_array($entry) || empty($entry['class'])) {
            throw new Exception('Product type "%s" API definition for "%s" must define "class".', [$code, $role]);
        }

        return [
            'class' => $entry['class'],
            'file' => $this->resolveApiFile($definition, $entry),
        ];
    }

    public function getHandler(string $code): object
    {
        $code = strtolower($code);
        if (isset($this->handlers[$code])) {
            return $this->handlers[$code];
        }

        $definition = $this->getDefinition($code);
        $handler = $definition['handler'] ?? null;
        $isLegacy = !empty($definition['legacy']);

        if ($handler === null) {
            if ($isLegacy) {
                if (!$this->di) {
                    throw new Exception('DI container is not set for legacy product type "%s".', [$code]);
                }
                $handler = $this->di['mod_service']('service' . $code);
            } else {
                $handlerClass = $definition['handler_class'];
                if (!class_exists($handlerClass)) {
                    $handlerFile = $this->resolveHandlerFile($definition);
                    if ($handlerFile !== null) {
                        if (!is_file($handlerFile)) {
                            throw new Exception('Product type handler file "%s" was not found.', [$handlerFile]);
                        }
                        require_once $handlerFile;
                    }
                }
                if (!class_exists($handlerClass)) {
                    throw new Exception('Product type handler class "%s" was not found.', [$handlerClass]);
                }
                $handler = new $handlerClass();
            }
        }

        if (!$isLegacy && !$handler instanceof \FOSSBilling\Interfaces\ProductTypeHandlerInterface) {
            throw new Exception('Product type handler for "%s" does not implement ProductTypeHandlerInterface.', [$code]);
        }

        if ($this->di && method_exists($handler, 'setDi')) {
            $handler->setDi($this->di);
        }

        $this->handlers[$code] = $handler;

        return $handler;
    }

    public function dispatchLifecycle(string $code, string $action, \Model_ClientOrder $order)
    {
        $code = strtolower($code);
        $action = strtolower($action);

        $handler = $this->getHandler($code);

        if ($handler instanceof \FOSSBilling\Interfaces\ProductTypeHandlerInterface) {
            if (!method_exists($handler, $action)) {
                throw new Exception('Product type "%s" does not support action "%s".', [$code, $action]);
            }

            return $handler->{$action}($order);
        }

        $legacyMethod = $action;
        if (method_exists($handler, $legacyMethod)) {
            return $handler->{$legacyMethod}($order);
        }

        $legacyMethod = 'action_' . $action;
        if (!method_exists($handler, $legacyMethod)) {
            $this->logLegacyMissingMethod($code, $action);

            return null;
        }

        $this->warnDeprecatedLegacyAction($code);

        $ref = new \ReflectionMethod($handler, $legacyMethod);
        $paramCount = $ref->getNumberOfParameters();
        if ($paramCount >= 2) {
            [$orderBean, $serviceBean] = $this->getLegacyBeans($code, $order, $ref);

            return $handler->{$legacyMethod}($orderBean, $serviceBean);
        }

        if ($paramCount === 1) {
            return $handler->{$legacyMethod}($order);
        }

        return $handler->{$legacyMethod}();
    }

    private function readManifest(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new Exception('Unable to read product type manifest file "%s".', [$path]);
        }

        try {
            return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new Exception('Invalid product type manifest "%s".', [$path]);
        }
    }

    private function normalizeDefinition(array $definition): array
    {
        $code = $definition['code'] ?? null;
        if (!is_string($code) || trim($code) === '') {
            throw new Exception('Product type definition requires a non-empty "code".');
        }
        $code = strtolower(trim($code));
        $definition['code'] = $code;

        $definition['label'] ??= ucfirst($code);

        if (empty($definition['handler_class']) && !empty($definition['service_class'])) {
            $definition['handler_class'] = $definition['service_class'];
        }

        $isLegacy = !empty($definition['legacy']);

        if (!$isLegacy && !isset($definition['handler']) && empty($definition['handler_class'])) {
            throw new Exception('Product type "%s" must define "handler_class" or "handler".', [$code]);
        }

        if (!$isLegacy
            && isset($definition['handler'])
            && !$definition['handler'] instanceof \FOSSBilling\Interfaces\ProductTypeHandlerInterface
        ) {
            throw new Exception('Product type "%s" handler must implement ProductTypeHandlerInterface.', [$code]);
        }

        $templates = $definition['templates'] ?? [];
        if (!is_array($templates)) {
            $templates = [];
        }

        $templates['order'] ??= sprintf('mod_service%s_order.html.twig', $code);
        $templates['manage'] ??= sprintf('mod_service%s_manage.html.twig', $code);
        $definition['templates'] = $templates;

        $capabilities = $definition['capabilities'] ?? self::DEFAULT_CAPABILITIES;
        if (!is_array($capabilities)) {
            $capabilities = self::DEFAULT_CAPABILITIES;
        }
        $definition['capabilities'] = array_values(array_unique(array_map('strtolower', $capabilities)));

        return $definition;
    }

    private function resolveHandlerFile(array $definition): ?string
    {
        $handlerFile = $definition['handler_file'] ?? null;
        if (!is_string($handlerFile) || trim($handlerFile) === '') {
            return null;
        }

        $handlerFile = trim($handlerFile);
        $basePath = $definition['base_path'] ?? null;
        if (!is_string($basePath) || trim($basePath) === '') {
            return $handlerFile;
        }

        return Path::join($basePath, $handlerFile);
    }

    private function resolveApiFile(array $definition, array $apiDefinition): ?string
    {
        $apiFile = $apiDefinition['file'] ?? null;
        if (!is_string($apiFile) || trim($apiFile) === '') {
            return null;
        }

        $apiFile = trim($apiFile);
        $basePath = $definition['base_path'] ?? null;
        if (!is_string($basePath) || trim($basePath) === '') {
            return $apiFile;
        }

        return Path::join($basePath, $apiFile);
    }

    private function getLegacyBeans(string $code, \Model_ClientOrder $order, \ReflectionMethod $method): array
    {
        if (!$this->di) {
            throw new Exception('DI container is not set for legacy product type "%s".', [$code]);
        }

        $firstParam = $method->getParameters()[0] ?? null;
        $type = $firstParam?->getType();
        $expectsModelOrder = $type instanceof \ReflectionNamedType
            && $type->getName() === \Model_ClientOrder::class;

        $orderBean = $expectsModelOrder
            ? $order
            : $this->di['db']->findOne('client_order', 'id = :id', [':id' => $order->id]);

        $serviceBean = null;
        if (!empty($order->service_id)) {
            $serviceBean = $this->di['db']->load('service_' . $code, $order->service_id);
        }

        return [$orderBean, $serviceBean];
    }

    private function warnDeprecatedLegacyAction(string $code): void
    {
        if ($this->di && isset($this->di['logger'])) {
            $this->di['logger']->warning(
                'Service module "%s" uses deprecated action_* methods. Please migrate to ProductTypeHandlerInterface.',
                $code
            );
        }
    }

    private function logLegacyMissingMethod(string $code, string $action): void
    {
        if ($this->di && isset($this->di['logger'])) {
            $this->di['logger']->warning(
                'Service module "%s" does not implement "%s" or "action_%s".',
                $code,
                $action,
                $action
            );
        } else {
            error_log("Service module {$code} does not implement {$action} or action_{$action}.");
        }
    }

    private function logManifestError(string $name, \Throwable $exception): void
    {
        if ($this->di && isset($this->di['logger'])) {
            $this->di['logger']->error(
                'Failed to load product type manifest for "%s": %s',
                $name,
                $exception->getMessage()
            );
        }
    }

    private function logOverride(string $code, array $definition): void
    {
        if ($this->di && isset($this->di['logger'])) {
            $source = $definition['source'] ?? 'unknown';
            $this->di['logger']->warning(
                'Product type "%s" was overridden by definition from "%s".',
                $code,
                $source
            );
        }
    }
}
