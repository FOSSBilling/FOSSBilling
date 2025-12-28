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

namespace FOSSBilling;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class Module implements InjectionAwareInterface
{
    private ?\Pimple\Container $di = null;
    private readonly string $module;
    private readonly Filesystem $filesystem;

    public const MANIFEST_FILENAME = 'manifest.json';
    public const SERVICE_CLASS_PREFIX = 'Box\\Mod\\';
    public const CONTROLLER_CLIENT_SUFFIX = '\\Controller\\Client';
    public const CONTROLLER_ADMIN_SUFFIX = '\\Controller\\Admin';

    public const CORE_MODULES = [ 'api', 'activity', 'cart', 'client',
                                  'cron', 'currency', 'email', 'extension',
                                  'hook', 'index', 'invoice', 'order',
                                  'page', 'product', 'profile', 'security',
                                  'servicecustom', 'servicedomain', 'servicedownloadable',
                                  'servicehosting', 'servicelicense', 'staff', 'stats',
                                  'support', 'system', 'theme', 'orderbutton', 'formbuilder' ];

    public function setDi(?\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function __construct(string $mod)
    {
        if (!preg_match('#[a-zA-Z]#', $mod)) throw new Exception('Invalid module name (:mod).', ['mod' => $mod]);

        $this->module = strtolower($mod);
        $this->filesystem = new Filesystem();
    }

    public function hasManifest(): bool
    {
        $path = Path::join($this->getModulePath(), self::MANIFEST_FILENAME);
        return $this->filesystem->exists($path);
    }

    public function getManifest(): array
    {
        if (!$this->hasManifest()) throw new Exception('Missing manifest file for the :mod module.', [':mod' => $this->module], 5897);

        $contents = file_get_contents(Path::join($this->getModulePath(), self::MANIFEST_FILENAME));

        try {
            $json = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new Exception('Invalid manifest file for the :mod module. Please check file syntax and permissions.', [':mod' => $this->module]);
        }

        // Default manifest fields if some fields are missing
        $info = [
            'id' => $this->module,
            'type' => 'mod',
            'name' => $this->module,
            'description' => null,
            'homepage_url' => 'https://fossbilling.org/',
            'author' => 'FOSSBilling',
            'author_url' => 'https://extensions.fossbilling.org/',
            'license' => 'N/A',
            'version' => '1.0',
            'icon_url' => null,
            'download_url' => null,
            'project_url' => 'https://extensions.fossbilling.org/',
            'minimum_boxbilling_version' => null, // @TODO: Rename these
            'maximum_boxbilling_version' => null,
        ];

        $info = array_merge($info, $json);
        $info['id'] = $this->module;
        $info['type'] = 'mod';

        if (!empty($info['icon_url'])) {
            $info['icon_url'] = '/modules/' . ucfirst($this->module) . '/' . $info['icon_url'];
        }

        return $info;
    }

    public function hasService(string $sub = ''): bool
    {
        $path = Path::join($this->getModulePath(), 'Service' . ucfirst($sub) . '.php');

        return $this->filesystem->exists($path);
    }

    public function getService(string $sub = ''): object
    {
        if (!$this->hasService($sub)) throw new Exception('Module :mod does not have a service class. Make sure Service.php exists and is valid.', [':mod' => $this->module], 5898);

        $class = self::SERVICE_CLASS_PREFIX . ucfirst($this->module) . '\\Service' . ucfirst($sub);
        $service = new $class();

        if (method_exists($service, 'setDi')) $service->setDi($this->di);

        return $service;
    }

    public function hasClientController(): bool
    {
        $path = Path::join($this->getModulePath(), 'Controller/Client.php');
        return $this->filesystem->exists($path);
    }

    public function getClientController(): object
    {
        if (!$this->hasClientController()) {
            throw new Exception('Client controller class for the module :mod was not found.', [':mod' => $this->module]);
        }

        return $this->createControllerInstance(self::CONTROLLER_CLIENT_SUFFIX);
    }

    public function hasAdminController(): bool
    {
        $path = Path::join($this->getModulePath(), 'Controller/Admin.php');

        return $this->filesystem->exists($path);
    }

    public function getAdminController(): ?object
    {
        if (!$this->hasAdminController()) return null;

        return $this->createControllerInstance(self::CONTROLLER_ADMIN_SUFFIX);
    }

    public function hasSettingsPage(): bool
    {
        $path = Path::join($this->getModulePath(), "html_admin/mod_{$this->module}_settings.html.twig");

        return $this->filesystem->exists($path);
    }

    public function install(): bool
    {
        if ($this->isCore()) return true;

        if ($this->hasService()) {
            $s = $this->getService();

            if (method_exists($s, 'install')) {
                $s->install();
                return true;
            }
        }

        return false;
    }

    public function uninstall(): bool
    {
        if ($this->isCore()) return true;

        if ($this->hasService()) {
            $s = $this->getService();

            if (method_exists($s, 'uninstall')) {
                $s->uninstall();
                return true;
            }
        }

        return false;
    }

    public function update(): bool
    {
        if ($this->isCore()) throw new InformationException('Core modules cannot be updated separately. Please update to the latest FOSSBilling version to have the module updated.');

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

    public function getCoreModules(): array
    {
        return self::CORE_MODULES;
    }

    public function isCore(): bool
    {
        return in_array($this->module, self::CORE_MODULES);
    }

    public function getConfig(): array
    {
        $modName = "mod_{$this->module}";

        $bean = $this->di['db']->findOne('extension_meta', 'extension = :ext AND meta_key = :key', [':ext' => $modName, ':key' => 'config']);
        if (!$bean || empty($bean->meta_value)) return [];

        $decrypted = $this->di['crypt']->decrypt($bean->meta_value, Config::getProperty('info.salt'));
        if (!is_string($decrypted) || !json_validate($decrypted)) return [];

        return json_decode($decrypted, true);
    }

    public function getName(): string
    {
        return $this->module;
    }

    public function registerClientRoutes(\Box_App &$app): bool
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

    private function getModulePath(): string
    {
        return Path::join(PATH_MODS, ucfirst($this->module)) . DIRECTORY_SEPARATOR;
    }

    private function createControllerInstance(string $suffix): object
    {
        $class = self::SERVICE_CLASS_PREFIX . ucfirst($this->module) . $suffix;
        $instance = new $class();

        if (method_exists($instance, 'setDi')) $instance->setDi($this->di);

        return $instance;
    }
}
