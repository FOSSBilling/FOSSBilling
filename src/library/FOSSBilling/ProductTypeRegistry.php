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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class ProductTypeRegistry implements InjectionAwareInterface
{
    public const string MANIFEST_FILENAME = 'manifest.json';
    public const string CACHE_KEY = 'extensions.products';
    public const int CACHE_TTL = 86400;

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
    /**
     * @var array<int, array<string, string>>
     */
    private array $loadErrors = [];

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    /**
     * Load product type definitions from filesystem with optional caching.
     *
     * @param string $path     Path to scan for product type manifests
     * @param bool   $useCache Whether to use caching (defaults to true)
     */
    public function loadFromFilesystem(string $path, bool $useCache = true): void
    {
        if (!is_dir($path)) {
            return;
        }

        $cache = $useCache && $this->di && isset($this->di['cache']) ? $this->di['cache'] : null;

        // Try to load from cache
        if ($cache) {
            $item = $cache->getItem(self::CACHE_KEY);
            if ($item->isHit() && is_array($cached = $item->get())) {
                $this->definitions = $cached;

                return;
            }
        }

        // Load from filesystem
        foreach (new \DirectoryIterator($path) as $entry) {
            if (!$entry->isDir() || $entry->isDot()) {
                continue;
            }

            $manifestPath = Path::join($entry->getPathname(), self::MANIFEST_FILENAME);
            if (!is_file($manifestPath)) {
                continue;
            }

            try {
                $filesystem = new Filesystem();
                $contents = $filesystem->readFile($manifestPath);
                $definition = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                $definition['base_path'] ??= $entry->getPathname();
                $definition['source'] ??= 'extension';
                $this->registerDefinition($definition);
            } catch (\Throwable $exception) {
                $this->logManifestError($entry->getFilename(), $exception);
            }
        }

        // Save to cache
        if ($cache) {
            $item = $cache->getItem(self::CACHE_KEY);
            $item->expiresAfter(self::CACHE_TTL);
            $item->set($this->definitions);
            $cache->save($item);
        }
    }

    /**
     * Register a product type definition.
     *
     * @param array{
     *     code?: string,
     *     label?: string,
     *     handler_class?: string,
     *     capabilities?: string[],
     *     base_path?: string,
     *     source?: string
     * } $definition Product type definition array
     */
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

    /**
     * Get a product type definition.
     *
     * @return array{
     *     code: string,
     *     label: string,
     *     handler_class: string,
     *     capabilities: string[],
     *     templates?: array<string, string>,
     *     base_path?: string,
     *     source: string
     * }
     *
     * @throws Exception When product type is not registered
     */
    public function getDefinition(string $code): array
    {
        $code = strtolower($code);
        if (!isset($this->definitions[$code])) {
            throw new Exception('Product type "%s" is not registered. Available types: %s', [$code, implode(', ', array_keys($this->definitions))]);
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

        return 'product_' . $code;
    }

    /**
     * @return array<string, array>
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    /**
     * @return array<int, array{name: string, message: string}>
     */
    public function getLoadErrors(): array
    {
        return $this->loadErrors;
    }

    /**
     * Invalidate the product type definitions cache.
     * Call this after adding/modifying/removing product type manifests.
     */
    public function invalidateCache(): void
    {
        if ($this->di && isset($this->di['cache'])) {
            $this->di['cache']->deleteItem(self::CACHE_KEY);
        }
    }

    public function assertHasDefinitions(string $rootPath): void
    {
        if (empty($this->definitions)) {
            $message = sprintf(
                'No product types were found in "%s". Ensure product type extensions are installed.',
                $rootPath
            );
            if ($this->di && isset($this->di['logger'])) {
                $this->di['logger']->critical($message);
            }

            throw new Exception($message);
        }
    }

    public function getTemplate(string $code, string $key): string
    {
        $code = strtolower($code);
        $key = strtolower($key);

        $definition = $this->getDefinition($code);

        $templates = $definition['templates'] ?? null;
        if (is_array($templates) && isset($templates[$key]) && $templates[$key] !== '') {
            return $templates[$key];
        }

        return 'ext_product_' . $code . '_' . $key . '.html.twig';
    }

    /**
     * Get API class definition for a product type and role.
     * Uses convention: {handler_class namespace}\Api for the API class.
     *
     * @param string $code Product type code
     *
     * @return array{class: string}|null API definition or null if not configured
     */
    public function getApiDefinition(string $code): ?array
    {
        $code = strtolower($code);

        $definition = $this->getDefinition($code);
        $handlerClass = $definition['handler_class'];
        if (!class_exists($handlerClass)) {
            return null;
        }

        $lastBackslash = strrpos($handlerClass, '\\');
        if ($lastBackslash === false) {
            return null;
        }
        $namespace = substr($handlerClass, 0, $lastBackslash);
        $apiClass = $namespace . '\\Api';

        return [
            'class' => $apiClass,
        ];
    }

    public function getHandler(string $code): object
    {
        $code = strtolower($code);
        if (isset($this->handlers[$code])) {
            return $this->handlers[$code];
        }

        $definition = $this->getDefinition($code);
        $handlerClass = $definition['handler_class'];

        if (!class_exists($handlerClass)) {
            throw new Exception('Product type handler class "%s" was not found.', [$handlerClass]);
        }
        $handler = new $handlerClass();

        if (!$handler instanceof Interfaces\ProductTypeHandlerInterface) {
            throw new Exception('Product type handler for "%s" does not implement ProductTypeHandlerInterface.', [$code]);
        }

        if ($this->di !== null) {
            $handler->setDi($this->di);
        }

        $this->handlers[$code] = $handler;

        return $handler;
    }

    /**
     * Invoke a lifecycle action on a product type handler.
     *
     * @param string             $code   Product type code
     * @param string             $action Lifecycle action (e.g., 'activate', 'suspend', 'cancel')
     * @param \Model_ClientOrder $order  Order model
     *
     * @return mixed Handler action result
     *
     * @throws ProductTypeActionNotSupportedException When product type does not support the action
     * @throws Exception                              When product type is not found
     */
    public function invokeProductTypeAction(string $code, string $action, \Model_ClientOrder $order)
    {
        $code = strtolower($code);
        $action = strtolower($action);

        $handler = $this->getHandler($code);

        if (!method_exists($handler, $action)) {
            throw new ProductTypeActionNotSupportedException($code, $action);
        }

        return $handler->{$action}($order);
    }

    private function normalizeDefinition(array $definition): array
    {
        $basePath = $definition['base_path'] ?? null;

        $code = $definition['code'] ?? null;
        if (!is_string($code) || trim($code) === '') {
            if (is_string($basePath) && trim($basePath) !== '') {
                $code = strtolower(basename($basePath));
            }
        }
        if (!is_string($code) || trim($code) === '') {
            throw new Exception('Product type definition requires a non-empty "code".');
        }
        $code = strtolower(trim($code));
        $definition['code'] = $code;

        $definition['label'] ??= ucfirst($code);

        if (empty($definition['handler_class']) && is_string($basePath) && trim($basePath) !== '') {
            $dirName = basename($basePath);
            $definition['handler_class'] = 'FOSSBilling\\ProductType\\' . $dirName . '\\' . $dirName . 'Handler';
        }

        if (empty($definition['handler_class'])) {
            throw new Exception('Product type "%s" must define "handler_class".', [$code]);
        }

        $capabilities = $definition['capabilities'] ?? self::DEFAULT_CAPABILITIES;
        if (!is_array($capabilities)) {
            $capabilities = self::DEFAULT_CAPABILITIES;
        }
        $definition['capabilities'] = array_values(array_unique(array_map('strtolower', $capabilities)));

        return $definition;
    }

    private function logManifestError(string $name, \Throwable $exception): void
    {
        $this->loadErrors[] = [
            'name' => $name,
            'message' => $exception->getMessage(),
        ];
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
