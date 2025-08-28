<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\Config;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class Box_Mod
{
    private ?string $mod = null;

    private ?Pimple\Container $di = null;

    private array $core = [
        'api',
        'activity',
        'cart',
        'client',
        'cron',
        'currency',
        'email',
        'extension',
        'hook',
        'index',
        'invoice',
        'order',
        'page',
        'product',
        'profile',
        'security',
        'servicecustom',
        'servicedomain',
        'servicedownloadable',
        'servicehosting',
        'servicelicense',
        'staff',
        'stats',
        'support',
        'system',
        'theme',
        'orderbutton',
        'formbuilder',
    ];
    private readonly Filesystem $filesystem;

    /**
     * @param string $mod
     */
    public function __construct($mod)
    {
        if (!preg_match('#[a-zA-Z]#', $mod)) {
            throw new FOSSBilling\Exception('Invalid module name');
        }

        $this->filesystem = new Filesystem();
        $this->mod = strtolower($mod);
    }

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function hasManifest()
    {
        return $this->filesystem->exists(Path::join($this->_getModPath(), 'manifest.json'));
    }

    public function getManifest(): array
    {
        try {
            $contents = $this->filesystem->readFile(Path::join($this->_getModPath(), 'manifest.json'));
            $json = json_decode($contents, true, JSON_THROW_ON_ERROR);
        } catch (IOException) {
            throw new FOSSBilling\Exception('Module :mod manifest file is missing or not readable.', [':mod' => $this->mod], 5897);
        } catch (JsonException) {
            throw new FOSSBilling\Exception('Module :mod manifest file is invalid. Check file syntax and permissions.', [':mod' => $this->mod]);
        }

        // default module info if some fields are missing
        $info = [
            'id' => $this->mod,
            'type' => 'mod',
            'name' => $this->mod,
            'description' => null,
            'homepage_url' => 'https://fossbilling.org/',
            'author' => 'FOSSBilling',
            'author_url' => 'https://extensions.fossbilling.org/',
            'license' => 'N/A',
            'version' => '1.0',
            'icon_url' => null,
            'download_url' => null,
            'project_url' => 'https://extensions.fossbilling.org/',
            'minimum_boxbilling_version' => null,
            'maximum_boxbilling_version' => null,
        ];

        $info = array_merge($info, $json);
        $info['id'] = $this->mod;
        $info['type'] = 'mod';
        if (!empty($info['icon_url'])) {
            $info['icon_url'] = Path::join('modules', ucfirst($this->mod), $info['icon_url']);
        }

        return $info;
    }

    public function hasService($sub = '')
    {
        $filename = sprintf('Service%s.php', ucfirst($sub));

        return $this->filesystem->exists(Path::join($this->_getModPath(), $filename));
    }

    public function getService($sub = '')
    {
        if (!$this->hasService($sub)) {
            throw new FOSSBilling\Exception('Module :mod does not have service class', [':mod' => $this->mod], 5898);
        }
        $class = 'Box\\Mod\\' . ucfirst($this->mod) . '\\Service' . ucfirst($sub);
        $service = new $class();
        if (method_exists($service, 'setDi')) {
            $service->setDi($this->di);
        }

        return $service;
    }

    public function hasClientController()
    {
        return $this->filesystem->exists(Path::join($this->_getModPath(), 'Controller', 'Client.php'));
    }

    public function getClientController()
    {
        if (!$this->hasClientController()) {
            throw new FOSSBilling\Exception('Module :mod Client controller class was not found', [':mod' => $this->mod]);
        }

        $class = 'Box\\Mod\\' . ucfirst($this->mod) . '\\Controller\\Client';
        $service = new $class();
        if (method_exists($service, 'setDi')) {
            $service->setDi($this->di);
        }

        return $service;
    }

    public function hasSettingsPage()
    {
        return $this->filesystem->exists(Path::join($this->_getModPath(), 'html_admin', "mod_{$this->mod}_settings.html.twig"));
    }

    public function hasAdminController()
    {
        return $this->filesystem->exists(Path::join($this->_getModPath(), 'Controller', 'Admin.php'));
    }

    public function getAdminController()
    {
        if (!$this->hasAdminController()) {
            return null;
        }
        $class = 'Box\\Mod\\' . ucfirst($this->mod) . '\\Controller\\Admin';
        $service = new $class();
        if (method_exists($service, 'setDi')) {
            $service->setDi($this->di);
        }

        return $service;
    }

    public function install()
    {
        if ($this->isCore()) {
            return true;
        }

        if ($this->hasService()) {
            $s = $this->getService();
            if (method_exists($s, 'install')) {
                $s->install();

                return true;
            }
        }

        return false;
    }

    public function uninstall()
    {
        if ($this->isCore()) {
            return true;
        }

        if ($this->hasService()) {
            $s = $this->getService();
            if (method_exists($s, 'uninstall')) {
                $s->uninstall();

                return true;
            }
        }

        return false;
    }

    public function update()
    {
        if ($this->isCore()) {
            throw new FOSSBilling\InformationException('Core modules cannot be updated');
        }

        if ($this->hasService()) {
            $s = $this->getService();
            if (method_exists($s, 'update')) {
                $manifest = $this->getManifest();
                $s->update($manifest);

                return true;
            }
        }

        return false;
    }

    public function getCoreModules()
    {
        return $this->core;
    }

    public function isCore()
    {
        return in_array($this->mod, $this->core);
    }

    public function getConfig()
    {
        $db = $this->di['db'];
        $config = [];

        $modName = 'mod_' . strtolower($this->mod);
        $c = $db->findOne('extension_meta', 'extension = :ext AND meta_key = :key', [':ext' => $modName, ':key' => 'config']);
        if ($c) {
            $config = $this->di['crypt']->decrypt($c->meta_value, Config::getProperty('info.salt'));
            $config = is_string($config) ? json_decode($config, true) : [];
        }

        return $config;
    }

    public function getName()
    {
        return $this->mod;
    }

    public function registerClientRoutes(Box_App &$app)
    {
        if ($this->hasClientController()) {
            $cc = $this->getClientController();
            if (method_exists($cc, 'register')) {
                $cc->register($app);

                return true;
            }
        }

        return false;
    }

    private function _getModPath()
    {
        return Path::join(PATH_MODS, ucfirst($this->mod));
    }
}
