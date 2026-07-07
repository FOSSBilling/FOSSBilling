<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Extension;

use Box\Mod\Extension\Entity\Extension;
use Box\Mod\Extension\Entity\ExtensionMeta;
use Box\Mod\Extension\Repository\ExtensionMetaRepository;
use Box\Mod\Extension\Repository\ExtensionRepository;
use FOSSBilling\Config;
use FOSSBilling\InjectionAwareInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\Cache\ItemInterface;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    protected ExtensionRepository $extensionRepository;
    protected ExtensionMetaRepository $extensionMetaRepository;

    public function __construct(private ?Filesystem $filesystem = null)
    {
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        if (isset($di['filesystem'])) {
            $this->filesystem ??= $di['filesystem'];
        }
        $this->extensionRepository = $di['em']->getRepository(Extension::class);
        $this->extensionMetaRepository = $di['em']->getRepository(ExtensionMeta::class);
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getExtensionRepository(): ExtensionRepository
    {
        return $this->extensionRepository;
    }

    public function getExtensionMetaRepository(): ExtensionMetaRepository
    {
        return $this->extensionMetaRepository;
    }

    public function getModulePermissions(): array
    {
        return [
            'view' => [
                'type' => 'bool',
                'display_name' => __trans('View extensions'),
                'description' => __trans('Allows the staff member to view installed and available extensions, languages, and extension details.'),
            ],
            'manage_extensions' => [
                'type' => 'bool',
                'display_name' => __trans('Manage extensions'),
                'description' => __trans('Allows the staff member to install, update, or deactivate extensions.'),
            ],
            'uninstall_extensions' => [
                'type' => 'bool',
                'display_name' => __trans('Uninstall extensions'),
                'description' => __trans('Allow the staff member to uninstall extensions, including their associated database records and files.'),
            ],
        ];
    }

    public function isCoreModule(string $mod): bool
    {
        $core = array_map(strtolower(...), $this->di['mod']('extension')->getCoreModules());

        return in_array(strtolower($mod), $core, true);
    }

    public function isExtensionActive(string $type, string $id): bool
    {
        if ($type == 'mod' && $this->isCoreModule($id)) {
            return true;
        }

        return $this->getExtensionRepository()->existsActiveByTypeAndName($type, $id);
    }

    public static function onBeforeAdminCronRun(\Box_Event $event): bool
    {
        $di = $event->getDi();
        $extensionService = $di['mod_service']('extension');

        try {
            $extensionService->getExtensionsList([]);
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        return true;
    }

    public function removeNotExistingModules(): int
    {
        $list = $this->getExtensionRepository()->findByType(Extension::TYPE_MOD);
        $removedItems = 0;
        foreach ($list as $ext) {
            try {
                $mod = $this->di['mod']($ext->getName());
                $mod->getManifest();
            } catch (\Exception) {
                $this->di['em']->remove($ext);
                ++$removedItems;
            }
        }

        if ($removedItems > 0) {
            $this->di['em']->flush();
        }

        return $removedItems;
    }

    /**
     * @return mixed[]
     */
    public function getExtensionsList($filter): array
    {
        $this->removeNotExistingModules();

        $installedEntities = $this->getExtensionRepository()
            ->getSearchQueryBuilder([...$filter, 'status' => Extension::STATUS_INSTALLED])
            ->getQuery()
            ->getResult();

        $has_settings = $filter['has_settings'] ?? null;
        $only_installed = $filter['installed'] ?? null;
        $installed_and_core = $filter['active'] ?? null;
        $search = isset($filter['search']) ? strtolower($filter['search']) : null;
        $result = [];

        if ($installed_and_core) {
            $core = $this->di['mod']('extension')->getCoreModules();
            foreach ($core as $core_mod) {
                $m = $this->di['mod']($core_mod);
                $manifest = $m->getManifest();
                $manifest['status'] = 'core';
                $manifest['has_settings'] = $m->hasSettingsPage();
                $result[] = $manifest;
            }
        }

        foreach ($installedEntities as $ext) {
            $m = $this->di['mod']($ext->getName());

            try {
                $manifest = $m->getManifest();
            } catch (\Exception $e) {
                error_log("Error while decoding the manifest file for {$ext->getName()} : {$e->getMessage()}.");

                continue;
            }

            $manifest['version'] = $ext->getVersion();
            $manifest['status'] = $ext->getStatus();
            if ($ext->getType() === Extension::TYPE_MOD && $ext->getStatus() === Extension::STATUS_INSTALLED) {
                $manifest['has_settings'] = $m->hasSettingsPage();
            }

            $result[] = $manifest;
        }

        if (!$only_installed && !$installed_and_core) {
            // add inactive modules
            $a = $this->_getAvailable();

            // unset installed modules
            foreach ($installedEntities as $ins) {
                if (in_array($ins->getName(), $a, true)) {
                    $key = array_search($ins->getName(), $a, true);
                    unset($a[$key]);
                }
            }

            foreach ($a as $mod) {
                $m = $this->di['mod']($mod);
                $manifest = $m->getManifest();
                $manifest['status'] = null;
                $manifest['has_settings'] = false;
                $manifest['has_settings_routes'] = false;
                $manifest['settings_routes'] = [];

                $result[] = $manifest;
            }
        }

        if ($has_settings) {
            foreach ($result as $idx => $mod) {
                if (!$mod['has_settings']) {
                    unset($result[$idx]);
                }
            }
            $result = array_values($result);
        }

        if (!empty($search)) {
            foreach ($result as $idx => $mod) {
                if (!str_contains(strtolower((string) $mod['name']), $search)) {
                    unset($result[$idx]);
                }
            }
            $result = array_values($result);
        }

        foreach ($result as $key => $value) {
            $iconPath = 'assets/icons/cog.svg';
            $icon_url = $value['icon_url'] ?? null;
            if ($icon_url) {
                $iconPath = SYSTEM_URL . $icon_url;
            }
            $result[$key]['icon_url'] = $iconPath;
        }

        foreach ($result as $key => $value) {
            $icon_url = $value['icon_url'] ?? null;
            if ($icon_url && isset($value['id'])) {
                $iconFilename = pathinfo((string) $icon_url, PATHINFO_BASENAME);
                $iconPath = Path::join(PATH_MODS, ucfirst((string) $value['id']), $iconFilename);
                if ($this->filesystem->exists($iconPath)) {
                    $result[$key]['icon_path'] = 'mod_' . ucfirst((string) $value['id']) . '_' . $iconFilename;
                }
            }
        }

        return $result;
    }

    /**
     * @return string[]
     */
    private function _getAvailable(): array
    {
        $mods = [];
        $finder = new Finder();
        $finder->directories()->in(PATH_MODS)->depth('== 0')->name('/\A[a-zA-Z0-9]+\z/');

        foreach ($finder as $dir) {
            $m = $dir->getBasename();
            $mod = $this->di['mod']($m);
            if ($mod->isCore()) {
                continue;
            }

            if (!$mod->hasManifest()) {
                error_log("Module {$m} manifest file is missing or is not readable.");

                continue;
            }

            $mods[] = strtolower($m);
        }

        return $mods;
    }

    public function getAdminNavigation($admin, $url = null)
    {
        $staff_service = $this->di['mod_service']('staff');
        $nav = [];
        $subpages = [];

        $modules = $this->di['mod']('extension')->getCoreModules();
        $installed = $this->getInstalledMods();
        $list = array_unique(array_merge($modules, $installed));
        foreach ($list as $mod) {
            if (!$staff_service->hasPermission($admin, $mod)) {
                continue;
            }
            $m = $this->di['mod']($mod);
            $obj = $m->getAdminController();

            if (!is_null($obj) && method_exists($obj, 'fetchNavigation')) {
                $n = $obj->fetchNavigation();

                if (isset($n['group'])) {
                    $l = $n['group']['location'];
                    unset($n['group']['location']);
                    $n['group']['active'] = false;
                    $n['group']['uri'] = $this->normalizeNavigationUri($n['group']['uri'] ?? null);
                    $nav[$l] = $n['group'];
                }

                if (isset($n['subpages'])) {
                    foreach ($n['subpages'] as $nn) {
                        if (is_array($nn)) {
                            $nn['active'] = false;
                            $subpages[] = $nn;
                        }
                    }
                }
            }
        }
        // groups sorting
        $nav = $this->di['tools']->sortByOneKey($nav, 'index');
        foreach ($subpages as $page) {
            if (!isset($page['location'])) {
                error_log('Invalid module menu item: ' . print_r($page, true));

                continue;
            }

            if (!isset($nav[$page['location']])) {
                error_log("Submenu item belongs to not existing location: {$page['location']}.");

                continue;
            }

            $l = $page['location'];
            unset($page['location']);
            $page['uri'] = $this->normalizeNavigationUri($page['uri'] ?? null);
            $nav[$l]['subpages'][] = $page;
        }

        // submenu sorting
        foreach ($nav as &$group) {
            $group['subpages'] = $this->di['tools']->sortByOneKey($group['subpages'], 'index');
            $group['uri'] = $this->resolveNavigationGroupUri($group);
        }

        return $nav;
    }

    private function resolveNavigationGroupUri(array $group): ?string
    {
        $groupUri = $this->normalizeNavigationUri($group['uri'] ?? null);
        if (!empty($groupUri)) {
            return $groupUri;
        }

        foreach ($group['subpages'] ?? [] as $subpage) {
            $subpageUri = $this->normalizeNavigationUri($subpage['uri'] ?? null);
            if (!empty($subpageUri)) {
                return $subpageUri;
            }
        }

        return null;
    }

    private function normalizeNavigationUri(?string $uri): ?string
    {
        if (is_null($uri)) {
            return null;
        }

        $uri = trim($uri);
        if ($uri === '') {
            return null;
        }

        if (preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/|#|\?)/i', $uri) === 1) {
            return $uri;
        }

        return $this->di['url']->adminLink(ltrim($uri, '/'));
    }

    public function update(Extension $model): never
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('extension', 'manage_extensions');

        throw new \FOSSBilling\InformationException('Visit the extension directory for more information on updating this extension.', null, 252);
    }

    /**
     * Activate an extension.
     *
     * @return array|array{has_settings: bool, id: string, redirect: bool, type: string}
     */
    public function activate(Extension $ext): array
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('extension', 'manage_extensions');

        $result = [
            'id' => $ext->getName(),
            'type' => $ext->getType(),
            'redirect' => false,
            'has_settings' => false,
        ];

        switch ($ext->getType()) {
            case \FOSSBilling\ExtensionManager::TYPE_MOD:
                $mod = $this->di['mod']($ext->getName());
                $manifest = $mod->getManifest();
                $this->installModule($ext);
                $ext->setVersion($manifest['version'] !== null ? (string) $manifest['version'] : null);
                $result['redirect'] = $mod->hasAdminController();
                $result['has_settings'] = $mod->hasSettingsPage();

                break;

            default:
                break;
        }

        $ext->setStatus(Extension::STATUS_INSTALLED);
        $this->di['em']->flush();

        return $result;
    }

    /**
     * Deactivate an extension.
     *
     * @throws \FOSSBilling\InformationException
     */
    public function deactivate(Extension $ext): bool
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('extension', 'manage_extensions');

        switch ($ext->getType()) {
            case \FOSSBilling\ExtensionManager::TYPE_HOOK:
                $file = Path::changeExtension(ucfirst((string) $ext->getName()), '.php');
                $destination = Path::join(PATH_LIBRARY, 'Hook', $file);
                if ($this->filesystem->exists($destination)) {
                    $this->filesystem->remove($destination);
                }

                break;

            case \FOSSBilling\ExtensionManager::TYPE_MOD:
                $mod = $ext->getName();
                if ($this->isCoreModule($mod)) {
                    throw new \FOSSBilling\InformationException('Core modules are an integral part of the FOSSBilling system and cannot be deactivated.');
                }

                break;

            default:
                break;
        }

        $this->di['em']->remove($ext);
        $this->di['em']->flush();

        return true;
    }

    /**
     * Uninstall a deactivated extension, remove its files from the disk and call $extension->uninstall() to trigger database cleanup.
     *
     * @param string $type Type of the extension (mod, theme, ...)
     * @param string $id   ID of the extension
     *
     * @throws \FOSSBilling\Exception
     */
    public function uninstall(string $type, string $id): bool
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('extension', 'uninstall_extensions');

        if ($this->isCoreModule($id)) {
            throw new \FOSSBilling\InformationException('Core modules are an integral part of the FOSSBilling system and cannot be uninstalled.');
        }

        if ($this->isExtensionActive($type, $id)) {
            throw new \FOSSBilling\InformationException('Cannot uninstall an active module. Please deactivate it first.');
        }

        // Determine the path based on extension type
        $path = $this->getExtensionPath($type, $id);

        // Try calling $module->uninstall() for modules to trigger database cleanup
        if ($type === \FOSSBilling\ExtensionManager::TYPE_MOD) {
            $mod = $this->di['mod']($id);

            try {
                $mod->uninstall();
            } catch (\Exception $e) {
                throw new \FOSSBilling\Exception('An exception was thrown by the :name module: :err', [':name' => $id, ':err' => $e->getMessage()]);
            }
        }

        // Finally remove the extension files from disk if they exist
        if ($this->filesystem->exists($path)) {
            try {
                $this->filesystem->remove($path);
                $this->di['logger']->info('Removed extension files for "%s" from %s', $id, $path);
            } catch (IOException $e) {
                $this->di['logger']->warning('Failed to remove extension files for "%s": %s', $id, $e->getMessage());

                throw new \FOSSBilling\Exception('Failed to remove extension files. Please check file permissions and try again or manually remove the files from :path', [':path' => $path]);
            }
        } else {
            throw new \FOSSBilling\Exception('Could not find the extension files in the supposed path. Please remove them from the disk manually.');
        }

        return true;
    }

    public function downloadAndExtract(string $type, string $id): bool
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('extension', 'manage_extensions');

        $latest = $this->di['extension_manager']->getLatestExtensionRelease($id);

        if (!isset($latest['download_url'])) {
            throw new \FOSSBilling\Exception('Couldn\'t find a valid download URL for the extension.');
        }

        if (!$this->di['extension_manager']->isExtensionCompatible($id)) {
            throw new \FOSSBilling\InformationException('This extension is not compatible with your version of FOSSBilling. Please update FOSSBilling to the latest version and try again.');
        }

        $extractedPath = Path::join(PATH_CACHE, md5(uniqid()));
        $zipPath = Path::join(PATH_CACHE, md5(uniqid()) . '.zip');

        // Create a temporary directory to extract the extension
        $this->filesystem->mkdir($extractedPath, 0o755);

        // Download the extension archive and save it to the cache folder
        $httpClient = $this->di['http_client'];
        $response = $httpClient->request('GET', $latest['download_url']);

        $code = $response->getStatusCode();
        if ($code !== 200) {
            throw new \FOSSBilling\Exception('Failed to download the extension with error :code', [':code' => $code]);
        }

        $fileHandler = fopen($zipPath, 'w');
        foreach ($httpClient->stream($response) as $chunk) {
            fwrite($fileHandler, (string) $chunk->getContent());
        }
        fclose($fileHandler);

        // Extract the archive
        $zip = new \PhpZip\ZipFile();

        try {
            $zip->openFile($zipPath);
            $zip->extractTo($extractedPath);
            $zip->close();
        } catch (\PhpZip\Exception\ZipException $e) {
            error_log($e->getMessage());

            throw new \FOSSBilling\Exception('Failed to extract file, please check file and folder permissions. Further details are available in the error log.');
        }

        // Get the destination path for the extension (includes LC_MESSAGES for translations)
        $destination = $this->getExtensionPath($type, $id, true);

        if ($this->filesystem->exists($destination)) {
            throw new \FOSSBilling\InformationException('Extension :id seems to be already installed.', [':id' => $id], 436);
        }

        try {
            $this->filesystem->rename($extractedPath, $destination);
        } catch (IOException) {
            throw new \FOSSBilling\Exception("Failed to move extension to it's final destination. Please check permissions for the destination folder. (:destination)", [':destination' => $destination], 437);
        }

        if ($this->filesystem->exists($zipPath)) {
            $this->filesystem->remove($zipPath);
        }

        $this->filesystem->remove($extractedPath);

        return true;
    }

    public function getInstalledMods()
    {
        return $this->getExtensionRepository()->findInstalledNamesByType(Extension::TYPE_MOD);
    }

    private function installModule(Extension $ext): bool
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('extension', 'manage_extensions');

        $mod = $this->di['mod']($ext->getName());

        if ($mod->isCore()) {
            throw new \FOSSBilling\InformationException('FOSSBilling core modules cannot be installed or removed');
        }

        $info = $mod->getManifest();
        if (isset($info['minimum_fossbilling_version']) && \FOSSBilling\Version::compareVersion($info['minimum_fossbilling_version']) > 0) {
            throw new \FOSSBilling\InformationException('Module cannot be installed. It requires at least :min version of FOSSBilling. You are using :v', [':min' => $info['minimum_fossbilling_version'], ':v' => \FOSSBilling\Version::VERSION]);
        }

        // Allow install module even if no installer exists
        // as it can be simple module
        // perform install script if available
        try {
            $mod->install();
        } catch (\FOSSBilling\Exception $e) {
            if ($e->getCode() != 408) {
                throw $e;
            }
        }

        $ext->setVersion($info['version'] !== null ? (string) $info['version'] : null);
        $this->di['em']->flush();

        return true;
    }

    public function activateExistingExtension(array $data): array
    {
        $ext = $this->getExtensionRepository()->findOneByTypeAndName($data['type'], $data['id']);
        $persistedNewly = false;
        if (!$ext instanceof Extension) {
            $ext = new Extension();
            $ext->setName($data['id']);
            $ext->setType($data['type']);
            $ext->setVersion(null);
            $ext->setStatus(Extension::STATUS_DEACTIVATED);
            $this->di['em']->persist($ext);
            $this->di['em']->flush();
            $persistedNewly = true;
        }
        $ext_id = $ext->getId();
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminActivateExtension', 'params' => ['id' => $ext_id]]);

        try {
            $result = $this->activate($ext);
        } catch (\Exception $e) {
            if ($persistedNewly) {
                $this->di['em']->remove($ext);
                $this->di['em']->flush();
            }

            throw $e;
        }
        $this->di['events_manager']->fire(['event' => 'onAfterAdminActivateExtension', 'params' => ['id' => $ext_id]]);
        $this->di['logger']->info('Activated extension "%s"', $data['id']);

        return $result;
    }

    public function getConfig(string $ext): array
    {
        return $this->di['cache']->get("config_{$ext}", function (ItemInterface $item) use ($ext) {
            $item->expiresAfter(60 * 60);

            $meta = $this->getExtensionMetaRepository()->findOneByExtensionAndScope($ext, 'config');
            if ($meta === null) {
                $meta = new ExtensionMeta();
                $meta->setExtension($ext);
                $meta->setMetaKey('config');
                $meta->setMetaValue(null);
                $this->di['em']->persist($meta);
                $this->di['em']->flush();
                $config = [];
            } else {
                $decrypted = $this->di['crypt']->decrypt($meta->getMetaValue(), $this->_getSalt());
                $config = is_string($decrypted) ? json_decode($decrypted, true) : [];
            }

            $config['ext'] = $ext;

            return $config;
        });
    }

    public function setConfig($data): bool
    {
        $this->hasManagePermission($data['ext']);
        $ext = $data['ext'];
        $this->getConfig($ext); // Creates new config if it does not exist in DB

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminExtensionConfigSave', 'params' => $data]);

        $meta = $this->getExtensionMetaRepository()->findOneByExtensionAndScope($ext, 'config');
        $config = json_encode($data);
        $encrypted = $this->di['crypt']->encrypt($config, $this->_getSalt());

        if ($meta === null) {
            $meta = new ExtensionMeta();
            $meta->setExtension($ext);
            $meta->setMetaKey('config');
            $meta->setMetaValue($encrypted);
            $this->di['em']->persist($meta);
        } else {
            $meta->setMetaValue($encrypted);
        }
        $this->di['em']->flush();

        $this->di['events_manager']->fire(['event' => 'onAfterAdminExtensionConfigSave', 'params' => $data]);
        $this->di['logger']->info("Updated extension {$ext} configuration.");
        $this->di['cache']->delete("config_{$ext}");

        return true;
    }

    /**
     * Get the filesystem path for an extension based on its type and ID.
     *
     * @param string $type                  Extension type
     * @param string $id                    Extension ID
     * @param bool   $includeMessagesSubdir Whether to include LC_MESSAGES subdirectory for translations (used during installation)
     *
     * @return string The filesystem path for the extension
     *
     * @throws \FOSSBilling\InformationException If the extension type is not supported
     */
    public function getExtensionPath(string $type, string $id, bool $includeMessagesSubdir = false): string
    {
        $this->assertValidExtensionIdentifier($id);

        $basePath = $this->getExtensionBasePath($type);
        $path = match ($type) {
            \FOSSBilling\ExtensionManager::TYPE_MOD,
            \FOSSBilling\ExtensionManager::TYPE_PG => Path::join($basePath, ucfirst($id)),
            \FOSSBilling\ExtensionManager::TYPE_THEME => Path::join($basePath, $id),
            \FOSSBilling\ExtensionManager::TYPE_TRANSLATION => $includeMessagesSubdir
                ? Path::join($basePath, $id, 'LC_MESSAGES')
                : Path::join($basePath, $id),
            default => throw new \FOSSBilling\InformationException('Extension type (:type) is not supported for automatic path determination.', [':type' => $type]),
        };

        $this->assertPathWithinBasePath($path, $basePath);

        return $path;
    }

    private function getExtensionBasePath(string $type): string
    {
        return match ($type) {
            \FOSSBilling\ExtensionManager::TYPE_MOD => PATH_MODS,
            \FOSSBilling\ExtensionManager::TYPE_THEME => PATH_THEMES,
            \FOSSBilling\ExtensionManager::TYPE_TRANSLATION => PATH_LANGS,
            \FOSSBilling\ExtensionManager::TYPE_PG => Path::join(PATH_LIBRARY, 'Payment', 'Adapter'),
            default => throw new \FOSSBilling\InformationException('Extension type (:type) is not supported for automatic path determination.', [':type' => $type]),
        };
    }

    private function assertValidExtensionIdentifier(string $id): void
    {
        if (preg_match('/\A[A-Za-z0-9_-]+\z/', $id) !== 1) {
            throw new \FOSSBilling\InformationException('Extension ID contains invalid characters.');
        }
    }

    private function assertPathWithinBasePath(string $path, string $basePath): void
    {
        $canonicalBasePath = Path::canonicalize($basePath);
        $canonicalPath = Path::canonicalize($path);

        if (!Path::isBasePath($canonicalBasePath, $canonicalPath)) {
            throw new \FOSSBilling\InformationException('Extension path resolved outside the expected extension directory.');
        }
    }

    private function _getSalt(): ?string
    {
        return Config::getProperty('info.salt');
    }

    /**
     * @return mixed[]
     */
    public function getCoreAndActiveModules(): array
    {
        $list = $this->getExtensionRepository()->findInstalledNamesByType(Extension::TYPE_MOD);

        $extensionMod = $this->di['mod']('extension');
        $mods = $extensionMod->getCoreModules();

        $modules = array_merge($mods, $list);
        sort($modules);

        return $modules;
    }

    public function getCoreAndActiveModulesAndPermissions(): array
    {
        $enabledModules = $this->getCoreAndActiveModules();
        $modules = [];

        foreach ($enabledModules as $module) {
            if ($module == 'index') {
                continue;
            }

            $permissions = $this->getSpecificModulePermissions($module);
            $modules[$module]['permissions'] = $permissions;
        }

        return $modules;
    }

    public function getSpecificModulePermissions(string $module): array
    {
        $class = 'Box\Mod\\' . ucfirst($module) . '\Service';
        if (class_exists($class) && method_exists($class, 'getModulePermissions')) {
            $moduleService = new $class();
            if (method_exists($moduleService, 'setDi')) {
                $moduleService->setDi($this->di);
            }
            $permissions = $moduleService->getModulePermissions();

            if (isset($permissions['hide_permissions']) && !$permissions['hide_permissions']) {
                unset($permissions['hide_permissions']);
            }

            // Fill in the manage_settings permission as it will always be the same
            if (isset($permissions['manage_settings'])) {
                $permissions['manage_settings'] = [
                    'type' => 'bool',
                    'display_name' => __trans('Manage settings'),
                    'description' => __trans('Allows the staff member to edit settings for this module.'),
                ];
            }

            return $permissions;
        }

        return [];
    }

    // Checks if the current user has permission to edit a module's settings
    public function hasManagePermission(string $module): void
    {
        $staff_service = $this->di['mod_service']('Staff');
        $permission_module = str_starts_with($module, 'mod_') ? substr($module, 4) : $module;

        // The module isn't active or has no permissions if this is the case, so continue as normal
        if (!$this->isExtensionActive('mod', $permission_module)) {
            return;
        }

        // First check if any access is allowed to the module for this person
        if (!$staff_service->hasPermission(null, $permission_module)) {
            throw new \FOSSBilling\InformationException('You do not have permission to access the :mod: module', [':mod:' => $permission_module], 403);
        }

        $module_permissions = $this->getSpecificModulePermissions($permission_module);

        // If they have access, let's see if that module has a permission specifically for managing settings and check if they have that permission.
        if (!array_key_exists('manage_settings', $module_permissions) || !$staff_service->hasPermission(null, $permission_module, 'manage_settings')) {
            throw new \FOSSBilling\InformationException('You do not have permission to perform this action', [], 403);
        }
    }
}
