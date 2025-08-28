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
    protected $options = [];
    private readonly Filesystem $filesystem;

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
        $paths_arr = [$options['mods'], $options['theme']];
        $this->setPaths($paths_arr);
    }

    protected function findTemplate($name, $throw = true)
    {
        // normalize name
        $name = preg_replace('#/{2,}#', '/', strtr($name, '\\', '/'));

        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        $name_split = explode('_', $name);

        $paths = [];
        $paths[] = Path::join($this->options['theme'], 'html_custom');
        $paths[] = Path::join($this->options['theme'], 'html');
        if (isset($name_split[1])) {
            $paths[] = Path::join($this->options['mods'], ucfirst($name_split[1]), "html_{$this->options['type']}");
        }

        foreach ($paths as $path) {
            if ($this->filesystem->exists(Path::join($path, $name))) {
                return $this->cache[$name] = Path::join($path, $name);
            }

            if (str_ends_with($name, 'icon.svg') && $this->filesystem->exists(Path::join(Path::getDirectory($path), 'icon.svg'))) {
                return $this->cache[$name] = Path::join(Path::getDirectory($path), 'icon.svg');
            }
        }

        throw new Twig\Error\LoaderError(sprintf('Unable to find template "%s" (looked into: %s).', $name, implode(', ', $paths)));
    }
}
