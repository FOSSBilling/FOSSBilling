<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @author Adam Daley <contact@adly.dev>
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Pimple\Container;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class Module
{
    private readonly array $coreModules;
    private readonly Filesystem $filesystem;

    /**
     * Constructor for the Module class.
     *
     * @param Container $di     the dependency injection container
     * @param string    $module the module name (containing only letters)
     */
    public function __construct(private Container $di, private string $module)
    {
        if (!preg_match('/^[a-zA-Z]+$/', $module)) {
            throw new Exception('Invalid module name (:module). Module names must contain only letters.', [':module' => $module]);
        }

        $this->coreModules = [
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
        $this->filesystem = new Filesystem();
        $this->module = strtolower($module);
    }

    /**
     * Get the module admin controller.
     *
     * @return object the admin controller instance. If the controller implements InjectionAwareInterface, the DI container will be injected.
     *
     * @throws Exception             if the admin controller class does not exist
     * @throws FileNotFoundException if the admin controller class is not found
     */
    public function getAdminController(): object
    {
        $controllerPath = Path::join($this->getPath(), 'Controller', 'Admin.php');
        if (!$this->filesystem->exists($controllerPath)) {
            throw new FileNotFoundException("Module {$this->getName()} Admin controller class was not found at: {$controllerPath}.");
        }

        $adminControllerClass = "FOSSBilling\\Module\\{$this->getName()}\\Controller\\Admin";
        if (!class_exists($adminControllerClass)) {
            throw new Exception('Module :module Admin controller class does not exist.', [':module' => $this->getName()]);
        }

        // Instantiate the admin controller class and inject the DI container if it implements InjectionAwareInterface.
        $adminController = new $adminControllerClass();
        $this->injectDi($adminController);

        return $adminController;
    }

    /**
     * Get the module client controller.
     *
     * @return object the client controller instance. If the controller implements InjectionAwareInterface, the DI container will be injected.
     *
     * @throws Exception             if the client controller class does not exist
     * @throws FileNotFoundException if the client controller class is not found
     */
    public function getClientController(): object
    {
        $controllerPath = Path::join($this->getPath(), 'Controller', 'Client.php');
        if (!$this->filesystem->exists($controllerPath)) {
            throw new FileNotFoundException("Module {$this->getName()} Client controller class was not found at: {$controllerPath}.");
        }

        $clientControllerClass = "FOSSBilling\\Module\\{$this->getName()}\\Controller\\Client";
        if (!class_exists($clientControllerClass)) {
            throw new Exception('Module :module Client controller class does not exist.', [':module' => $this->getName()]);
        }

        // Instantiate the client controller class and inject the DI container if it implements InjectionAwareInterface.
        $clientController = new $clientControllerClass();
        $this->injectDi($clientController);

        return $clientController;
    }

    /**
     * Get the module configuration from the database.
     *
     * @TODO: Rework / merge with the Extension Service class getConfig method.
     *
     * @return array the module configuration as an associative array
     */
    public function getConfig()
    {
        $db = $this->di['db'];
        $config = [];

        $modName = "mod_{$this->module}";
        $c = $db->findOne('extension_meta', 'extension = :ext AND meta_key = :key', [':ext' => $modName, ':key' => 'config']);
        if ($c) {
            $config = $this->di['crypt']->decrypt($c->meta_value, Config::getProperty('info.salt'));
            if (is_string($config)) {
                $decoded = json_decode($config, true);
                $config = is_array($decoded) ? $decoded : [];
            } else {
                $config = [];
            }
        }

        return $config;
    }

    /**
     * Get the module name.
     *
     * @return string the module name (first character uppercase)
     */
    public function getName(): string
    {
        return ucfirst($this->module);
    }

    /**
     * Get the module manifest.
     *
     * @return array the module manifest data
     *
     * @throws Exception             if the manifest file is invalid or not readable
     * @throws FileNotFoundException if the manifest file does not exist
     */
    public function getManifest(): array
    {
        $manifestPath = Path::join($this->getPath(), 'manifest.json');
        if (!$this->filesystem->exists($manifestPath)) {
            throw new FileNotFoundException("Module {$this->getName()} manifest file was not found at: {$manifestPath}.");
        }

        try {
            $manifestContent = $this->filesystem->readFile($manifestPath);
            $manifestFileData = json_decode($manifestContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (IOException) {
            throw new Exception('Module :module manifest file is missing or not readable.', [':module' => $this->getName()], 5897);
        } catch (\JsonException) {
            throw new Exception('Module :module manifest file is invalid. Check file syntax and permissions.', [':module' => $this->getName()]);
        }

        // Default manifest data.
        $defaultManifestData = [
            'id' => $this->module,
            'type' => 'mod',
            'name' => $this->getName(),
            'description' => null,
            'homepage_url' => 'https://fossbilling.org/',
            'author' => 'FOSSBilling',
            'author_url' => 'https://extensions.fossbilling.org/',
            'license' => 'N/A',
            'version' => '1.0',
            'icon_url' => null,
            'download_url' => null,
            'project_url' => 'https://extensions.fossbilling.org/',
            'min_fb_version' => null,
            'max_fb_version' => null,
        ];

        // Merge the default manifest data with the file data.
        $manifestData = array_merge($defaultManifestData, $manifestFileData ?? []);
        $manifestData['id'] = $this->module;
        $manifestData['type'] = 'mod';

        // Ensure the icon URL is relative to the module path.
        if (!empty($manifestData['icon_url'])) {
            $manifestData['icon_url'] = Path::join('modules', $this->getName(), $manifestData['icon_url']);
        }

        return $manifestData;
    }

    /**
     * Get the service for the module.
     *
     * @param string $serviceName the service name (containing only letters), or empty string for the main service
     *
     * @return object The service instance. If the service implements InjectionAwareInterface, the DI container will be injected.
     *
     * @throws Exception             if the service name is invalid or the service class does not exist
     * @throws FileNotFoundException if the service file is not found
     */
    public function getService(string $serviceName = ''): object
    {
        if ($serviceName !== '' && !preg_match('/^[a-zA-Z]+$/', $serviceName)) {
            throw new Exception('Invalid service name (:serviceName). Service names must contain only letters.', [':serviceName' => $serviceName]);
        }

        $serviceName = ucfirst($serviceName);
        $serviceFile = "Service{$serviceName}.php";
        $servicePath = Path::join($this->getPath(), $serviceFile);
        if (!$this->filesystem->exists($servicePath)) {
            throw new FileNotFoundException("Module {$this->getName()} Service file ({$serviceFile}) was not found at: {$servicePath}.");
        }

        $serviceClass = "FOSSBilling\\Module\\{$this->getName()}\\Service{$serviceName}";
        if (!class_exists($serviceClass)) {
            throw new Exception('Module :module Service class does not exist (:serviceClass).', [':module' => $this->getName(), ':serviceClass' => $serviceClass]);
        }

        // Instantiate the service class and inject the DI container if it implements InjectionAwareInterface.
        $service = new $serviceClass();
        $this->injectDi($service);

        return $service;
    }

    /**
     * Check if the module has an admin area settings page.
     *
     * @return bool true if the module has a settings page, false otherwise
     */
    public function hasSettingsPage(): bool
    {
        return $this->filesystem->exists(Path::join($this->getPath(), 'html_admin', "mod_{$this->module}_settings.html.twig"));
    }

    /**
     * Check if the module is a core module.
     *
     * @return bool true if the module is a core module, false otherwise
     */
    public function isCore(): bool
    {
        return in_array($this->module, $this->coreModules, true);
    }

    /**
     * Register admin routes for the module.
     *
     * @param App $app the application instance
     *
     * @return bool true if the module has an admin controller and routes were registered, false otherwise
     */
    public function registerAdminRoutes(App $app): bool
    {
        try {
            $adminController = $this->getAdminController();
            if (is_callable([$adminController, 'register'])) {
                $adminController->register($app);

                return true;
            }
        } catch (FileNotFoundException) {
            $this->di['logger']->setChannel('routing')->notice("Module {$this->getName()} Admin controller class was not found.");

            return false;
        }

        return false;
    }

    /**
     * Register client routes for the module.
     *
     * @return bool true if module has a client controller and routes were registered, false otherwise
     */
    public function registerClientRoutes(App &$app): bool
    {
        try {
            $clientController = $this->getClientController();
            if (method_exists($clientController, 'register')) {
                $clientController->register($app);

                return true;
            }
        } catch (FileNotFoundException) {
            $this->di['logger']->setChannel('routing')->notice("Module {$this->getName()} Client controller class was not found.");

            return false;
        }

        return false;
    }

    /**
     * Install the module if it has a service class with an install method.
     *
     * @return bool true if the module was installed, false otherwise
     *
     * @throws InformationException if the module is a core module
     */
    public function install(): bool
    {
        // Core modules cannot be installed.
        if ($this->isCore()) {
            throw new InformationException('Core module :module cannot be installed.', [':module' => $this->getName()]);
        }

        // Check if the module has an install method and call it.
        try {
            $service = $this->getService();
            if (method_exists($service, 'install')) {
                $service->install();

                return true;
            }
        } catch (FileNotFoundException) {
            // If the service class does not exist, we consider the module as not having an install method.
        }

        return false;
    }

    /**
     * Update the module if it has a service class with an install method.
     *
     * @return bool true if the module was updated, false otherwise
     *
     * @throws InformationException if the module is a core module
     */
    public function update(): bool
    {
        // Core modules cannot be updated.
        if ($this->isCore()) {
            throw new InformationException('Core module :module cannot be updated.', [':module' => $this->getName()]);
        }

        // Check if the module has an update method and call it.
        try {
            $service = $this->getService();
            if (method_exists($service, 'update')) {
                $service->update();

                return true;
            }
        } catch (FileNotFoundException) {
            // If the service class does not exist, we consider the module as not having an update method.
        }

        return false;
    }

    /**
     * Uninstall the module if it is not a core module and has an uninstall method.
     *
     * @return bool true if the module was uninstalled, false otherwise
     *
     * @throws InformationException if the module is a core module
     */
    public function uninstall(): bool
    {
        // Core modules cannot be uninstalled.
        if ($this->isCore()) {
            throw new InformationException('Core module :module cannot be uninstalled.', [':module' => $this->getName()]);
        }

        // Check if the module has an uninstall method and call it.
        try {
            $service = $this->getService();
            if (method_exists($service, 'uninstall')) {
                $result = $service->uninstall();

                return $result === null ? true : (bool) $result;
            }
        } catch (FileNotFoundException) {
            // If the service class does not exist, we consider the module as not having an uninstall method.
        }

        return false;
    }

    /**
     * Get the module path.
     *
     * @return string the full path to the module directory
     */
    private function getPath(): string
    {
        return Path::join(PATH_MODS, $this->getName());
    }

    /**
     * Injects the DI container into the object if it implements InjectionAwareInterface.
     */
    private function injectDi(object $object): void
    {
        if ($object instanceof InjectionAwareInterface) {
            $object->setDi($this->di);
        }
    }
}
