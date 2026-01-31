<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class Box_TwigLoader extends Twig\Loader\FilesystemLoader
{
    protected array $options;
    private readonly Filesystem $filesystem;
    private ?FOSSBilling\ProductTypeRegistry $productTypeRegistry = null;
    private ?string $extensionsRoot = null;

    /**
     * Constructor.
     *
     * @param array $options A path or an array of options and paths
     */
    public function __construct(array $options)
    {
        parent::__construct();
        if (!isset($options['mods'])) {
            throw new FOSSBilling\Exception('Missing :missing: param for Box_TwigLoader', ['missing' => 'mods']);
        }

        if (!isset($options['theme'])) {
            throw new FOSSBilling\Exception('Missing :missing: param for Box_TwigLoader', ['missing' => 'theme']);
        }

        if (!isset($options['type'])) {
            throw new FOSSBilling\Exception('Missing :missing: param for Box_TwigLoader', ['missing' => 'type']);
        }

        $this->options = $options;
        $this->filesystem = new Filesystem();
        if (isset($options['product_type_registry']) && $options['product_type_registry'] instanceof FOSSBilling\ProductTypeRegistry) {
            $this->productTypeRegistry = $options['product_type_registry'];
        }
        if (isset($options['extensions_products']) && is_string($options['extensions_products'])) {
            $this->extensionsRoot = $options['extensions_products'];
        }
        $paths_arr = [$options['mods'], $options['theme']];
        $this->setPaths($paths_arr);
    }

    #[Override]
    protected function findTemplate($name, $throw = true)
    {
        $name = preg_replace('#/{2,}#', '/', strtr($name, '\\', '/'));

        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        $name_split = explode('_', (string) $name);

        $paths = [];
        $paths[] = Path::join($this->options['theme'], 'html_custom');
        $paths[] = Path::join($this->options['theme'], 'html');

        $moduleName = isset($name_split[1]) ? strtolower((string) $name_split[1]) : null;
        $extProductCode = null;

        if (isset($name_split[0]) && strtolower((string) $name_split[0]) === 'ext' && isset($name_split[2])) {
            $extProductCode = strtolower((string) $name_split[2]);
            if ($this->isProductTypeCode($extProductCode)) {
                foreach ($this->getProductTypeTemplatePaths($extProductCode) as $templatePath) {
                    $paths[] = $templatePath;
                }
            }
        }

        if ($moduleName !== null && $moduleName !== 'ext') {
            $code = $this->resolveProductTypeCode($moduleName);
            if ($code !== null) {
                foreach ($this->getProductTypeTemplatePaths($code) as $templatePath) {
                    $paths[] = $templatePath;
                }
            }
            $paths[] = Path::join($this->options['mods'], ucfirst($moduleName), "html_{$this->options['type']}");
        }

        foreach ($paths as $path) {
            if ($this->filesystem->exists(Path::join($path, $name))) {
                return $this->cache[$name] = Path::join($path, $name);
            }

            if (str_ends_with((string) $name, 'icon.svg') && $this->filesystem->exists(Path::join(Path::getDirectory($path), 'icon.svg'))) {
                return $this->cache[$name] = Path::join(Path::getDirectory($path), 'icon.svg');
            }
        }

        throw new Twig\Error\LoaderError(sprintf('Unable to find template "%s" (looked into: %s).', $name, implode(', ', $paths)));
    }

    private function isProductTypeCode(string $code): bool
    {
        if ($this->productTypeRegistry && $this->productTypeRegistry->has($code)) {
            return true;
        }

        if ($this->extensionsRoot && is_dir($this->extensionsRoot)) {
            return $this->resolveExtensionsProductPath($code) !== null;
        }

        return false;
    }

    private function resolveProductTypeCode(string $moduleName): ?string
    {
        $moduleName = strtolower($moduleName);

        if (str_starts_with($moduleName, 'service')) {
            $code = substr($moduleName, strlen('service'));
            if ($code !== '') {
                return $code;
            }
        }

        if ($this->productTypeRegistry && $this->productTypeRegistry->has($moduleName)) {
            return $moduleName;
        }

        if ($this->extensionsRoot && is_dir($this->extensionsRoot)) {
            if ($this->resolveExtensionsProductPath($moduleName) !== null) {
                return $moduleName;
            }
        }

        return null;
    }

    private function getProductTypeTemplatePaths(string $code): array
    {
        $paths = [];

        if ($this->productTypeRegistry && $this->productTypeRegistry->has($code)) {
            $definition = $this->productTypeRegistry->getDefinition($code);
            $basePath = $definition['base_path'] ?? null;
            if (is_string($basePath) && $basePath !== '') {
                $paths[] = Path::join($basePath, 'templates', $this->options['type']);
                $paths[] = Path::join($basePath, "html_{$this->options['type']}");
            }

            return $paths;
        }

        if ($this->extensionsRoot && is_dir($this->extensionsRoot)) {
            $resolved = $this->resolveExtensionsProductPath($code);
            if ($resolved !== null) {
                $paths[] = Path::join($resolved, 'templates', $this->options['type']);
                $paths[] = Path::join($resolved, "html_{$this->options['type']}");
            }
        }

        return $paths;
    }

    private function resolveExtensionsProductPath(string $code): ?string
    {
        if (!$this->extensionsRoot) {
            return null;
        }

        $directPath = Path::join($this->extensionsRoot, $code);
        if ($this->filesystem->exists($directPath)) {
            return $directPath;
        }

        $iterator = new DirectoryIterator($this->extensionsRoot);
        foreach ($iterator as $entry) {
            if (!$entry->isDir() || $entry->isDot()) {
                continue;
            }
            if (strtolower($entry->getFilename()) === $code) {
                return $entry->getPathname();
            }
        }

        return null;
    }
}
