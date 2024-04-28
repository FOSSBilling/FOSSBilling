<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Extension;

use FOSSBilling\Config;
use FOSSBilling\InjectionAwareInterface;
use Symfony\Contracts\Cache\ItemInterface;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getModulePermissions(): array
    {
        return [
            'can_always_access' => true,
            'manage_extensions' => [
                'type' => 'bool',
                'display_name' => __trans('Manage extensions'),
                'description' => __trans('Allows the staff member to install, update, or remove extensions.'),
            ],
        ];
    }

    public function isCoreModule($mod)
    {
        $core = $this->di['mod']('extension')->getCoreModules();

        return in_array($mod, $core);
    }

    public function isExtensionActive($type, $id)
    {
        if ($type == 'mod' && $this->isCoreModule($id)) {
            return true;
        }

        $query = "SELECT id
                FROM extension
                WHERE type = :type
                AND status = 'installed'
                AND name = :id
                LIMIT 1
               ";

        $id_or_null = $this->di['db']->getCell($query, ['type' => $type, 'id' => $id]);

        return (bool) $id_or_null;
    }

    public static function onBeforeAdminCronRun(\Box_Event $event)
    {
        $di = $event->getDi();
        $extensionService = $di['mod_service']('extension');

        try {
            $extensionService->getExtensionsList([]);
        } catch (\Exception $e) {
            error_log($e);
        }

        return true;
    }

    public function removeNotExistingModules()
    {
        $list = $this->di['db']->find('Extension', "type = 'mod'");
        $removedItems = 0;
        foreach ($list as $ext) {
            try {
                $mod = $this->di['mod']($ext->name);
                $mod->getManifest();
            } catch (\Exception) {
                $this->di['db']->trash($ext);
                ++$removedItems;
            }
        }

        return $removedItems == 0 ? true : $removedItems;
    }

    public function getSearchQuery($filter)
    {
        $search = $filter['search'] ?? null;
        $type = $filter['type'] ?? null;

        $params = [];
        $sql = "SELECT * FROM extension
            WHERE status = 'installed' ";

        if ($type !== null) {
            $sql .= ' AND type = :type';
            $params[':type'] = $type;
        }

        if ($search !== null) {
            $sql .= ' AND name LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY type ASC, status DESC, id ASC';

        return [$sql, $params];
    }

    /**
     * @return mixed[]
     */
    public function getExtensionsList($filter): array
    {
        $this->removeNotExistingModules();

        [$sql, $params] = $this->getSearchQuery($filter);
        $installed = $this->di['db']->getAll($sql, $params);

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

        foreach ($installed as $im) {
            $m = $this->di['mod']($im['name']);

            try {
                $manifest = $m->getManifest();
            } catch (\Exception $e) {
                error_log('Error while decoding the manifest file for ' . $im['name'] . ' : ' . $e->getMessage());

                continue;
            }

            $manifest['version'] = $im['version'];
            $manifest['status'] = $im['status'];
            if ($im['type'] == 'mod' && $im['status'] == 'installed') {
                $manifest['has_settings'] = $m->hasSettingsPage();
            }

            $result[] = $manifest;
        }

        if (!$only_installed && !$installed_and_core) {
            // add inactive modules
            $a = $this->_getAvailable();

            // unset installed modules
            foreach ($installed as $ins) {
                if (in_array($ins['name'], $a)) {
                    $key = array_search($ins['name'], $a);
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
                if (!str_contains(strtolower($mod['name']), $search)) {
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
            if ($icon_url) {
                $iconPath = realpath(PATH_MODS . DIRECTORY_SEPARATOR . ucfirst($value['id']) . DIRECTORY_SEPARATOR . basename($icon_url));
                if (file_exists($iconPath)) {
                    $result[$key]['icon_path'] = 'mod_' . ucfirst($value['id']) . '_' . basename($icon_url);
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
        $handle = opendir(PATH_MODS);
        while ($name = readdir($handle)) {
            if (ctype_alnum($name)) {
                $m = $name;
                $mod = $this->di['mod']($m);
                if ($mod->isCore()) {
                    continue;
                }

                if (!$mod->hasManifest()) {
                    error_log('Module ' . $m . ' manifest file is missing or is not readable.');

                    continue;
                }

                $mods[] = strtolower($m);
            }
        }
        closedir($handle);

        return $mods;
    }

    public function getAdminNavigation($admin, $url = null)
    {
        $staff_service = $this->di['mod_service']('staff');
        $current_mod = null;
        $current_url = null;
        $nav = [];
        $subpages = [];

        $modules = $this->di['mod']('extension')->getCoreModules();
        $installed = $this->getInstalledMods();
        $list = array_merge($modules, $installed);
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
                error_log('Invalid module menu item: ' . print_r($page, 1));

                continue;
            }

            if (!isset($nav[$page['location']])) {
                error_log('Submenu item belongs to not existing location: ' . $page['location']);

                continue;
            }

            $l = $page['location'];
            unset($page['location']);
            $nav[$l]['subpages'][] = $page;
        }

        // submenu sorting
        foreach ($nav as &$group) {
            $group['subpages'] = $this->di['tools']->sortByOneKey($group['subpages'], 'index');
        }

        return $nav;
    }

    /**
     * @return \Model_Extension
     */
    public function findExtension($type, $id)
    {
        return $this->di['db']->findOne('Extension', 'type = ? and name = ? ', [$type, $id]);
    }

    public function update(\Model_Extension $model): never
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('extension', 'manage_extensions');

        throw new \FOSSBilling\InformationException('Visit the extension directory for more information on updating this extension.', null, 252);
    }

    public function activate(\Model_Extension $ext): array
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('extension', 'manage_extensions');

        $result = [
            'id' => $ext->name,
            'type' => $ext->type,
            'redirect' => false,
            'has_settings' => false,
        ];

        switch ($ext->type) {
            case \FOSSBilling\ExtensionManager::TYPE_MOD:
                $mod = $this->di['mod']($ext->name);
                $manifest = $mod->getManifest();
                $this->installModule($ext);
                $ext->version = $manifest['version'];
                $result['redirect'] = $mod->hasAdminController();
                $result['has_settings'] = $mod->hasSettingsPage();

                break;

            default:
                break;
        }

        $ext->status = 'installed';
        $this->di['db']->store($ext);

        return $result;
    }

    public function deactivate(\Model_Extension $ext)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('extension', 'manage_extensions');

        switch ($ext->type) {
            case \FOSSBilling\ExtensionManager::TYPE_HOOK:
                $file = ucfirst($ext->name) . '.php';
                $destination = PATH_LIBRARY . '/Hook/' . $file;
                if (file_exists($destination)) {
                    unlink($destination);
                }

                break;

            case \FOSSBilling\ExtensionManager::TYPE_MOD:
                $mod = $ext->name;
                if ($this->isCoreModule($mod)) {
                    throw new \FOSSBilling\InformationException('FOSSBilling core modules cannot be managed');
                }

                try {
                    $mm = $this->di['mod']($mod);
                    $mm->uninstall();
                } catch (\FOSSBilling\Exception $e) {
                    if ($e->getCode() != 408) {
                        throw $e;
                    }
                }

                break;

            default:
                break;
        }

        $this->di['db']->trash($ext);

        return true;
    }

    public function uninstall(\Model_Extension $ext)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('extension', 'manage_extensions');

        $this->deactivate($ext);

        switch ($ext->type) {
            case \FOSSBilling\ExtensionManager::TYPE_MOD:
                break;

            default:
                break;
        }

        $this->di['db']->trash($ext);

        return true;
    }

    public function downloadAndExtract($type, $id)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('extension', 'manage_extensions');

        $latest = $this->di['extension_manager']->getLatestExtensionRelease($id);

        if (!isset($latest['download_url'])) {
            throw new \Exception('Coudn\'t find a valid download URL for the extension.');
        }

        if (!$this->di['extension_manager']->isExtensionCompatible($id)) {
            throw new \Exception('This extension is not compatible with your version of FOSSBilling. Please update FOSSBilling to the latest version and try again.');
        }

        $extractedPath = PATH_CACHE . DIRECTORY_SEPARATOR . md5(uniqid());
        $zipPath = PATH_CACHE . DIRECTORY_SEPARATOR . md5(uniqid()) . '.zip';

        // Create a temporary directory to extract the extension
        mkdir($extractedPath, 0755, true);

        // Download the extension archive and save it to the cache folder
        $fileHandler = fopen($zipPath, 'w');
        $client = \Symfony\Component\HttpClient\HttpClient::create(['bindto' => BIND_TO]);
        $response = $client->request('GET', $latest['download_url']);

        $code = $response->getStatusCode();
        if ($code !== 200) {
            throw new \FOSSBilling\Exception('Failed to download the extension with error :code', [':code' => $code]);
        }

        foreach ($client->stream($response) as $chunk) {
            fwrite($fileHandler, $chunk->getContent());
        }

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

        switch ($type) {
            case \FOSSBilling\ExtensionManager::TYPE_MOD:
                $destination = PATH_MODS . DIRECTORY_SEPARATOR . ucfirst($id);

                break;
            case \FOSSBilling\ExtensionManager::TYPE_THEME:
                $destination = PATH_THEMES . DIRECTORY_SEPARATOR . $id;

                break;
            case \FOSSBilling\ExtensionManager::TYPE_TRANSLATION:
                $destination = PATH_LANGS . DIRECTORY_SEPARATOR . $id . '/LC_MESSAGES';

                break;
            case \FOSSBilling\ExtensionManager::TYPE_PG:
                $destination = PATH_LIBRARY . DIRECTORY_SEPARATOR . 'Payment' . DIRECTORY_SEPARATOR . 'Adapter' . DIRECTORY_SEPARATOR . ucfirst($id);

                break;
        }

        if (isset($destination)) {
            if (file_exists($destination)) {
                throw new \FOSSBilling\InformationException('Extension :id seems to be already installed.', [':id' => $id], 436);
            }
            if (!rename($extractedPath, $destination)) {
                throw new \FOSSBilling\Exception('Failed to move extension to it\'s final destination. Please check permissions for the destination folder. (:destination)', [':destination' => $destination], 437);
            }
        } else {
            throw new \FOSSBilling\InformationException('Extension type (:type) cannot be automatically installed.', [':type' => $type]);
        }

        if (file_exists($zipPath)) {
            unlink($zipPath);
        }

        $this->di['tools']->emptyFolder($extractedPath);

        return true;
    }

    public function getInstalledMods()
    {
        $query = "SELECT name
                FROM extension
                WHERE type = 'mod'
                AND status = 'installed'
               ";
        $pdo = $this->di['pdo'];
        $stmt = $pdo->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function installModule(\Model_Extension $ext)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('extension', 'manage_extensions');

        $mod = $this->di['mod']($ext->name);

        if ($mod->isCore()) {
            throw new \FOSSBilling\InformationException('FOSSBilling core modules cannot be installed or removed');
        }

        $info = $mod->getManifest();
        if (isset($info['minimum_boxbilling_version']) && \FOSSBilling\Version::compareVersion($info['minimum_boxbilling_version']) > 0) {
            throw new \FOSSBilling\InformationException('Module cannot be installed. It requires at least :min version of FOSSBilling. You are using :v', [':min' => $info['minimum_boxbilling_version'], ':v' => \FOSSBilling\Version::VERSION]);
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

        $ext->version = $info['version'];
        $this->di['db']->store($ext);

        return true;
    }

    public function activateExistingExtension($data)
    {
        $ext = $this->findExtension($data['type'], $data['id']);
        if (!$ext instanceof \Model_Extension) {
            $ext = $this->di['db']->dispense('Extension');
            $ext->name = $data['id'];
            $ext->type = $data['type'];
            $ext->version = null;
            $ext->status = 'deactivated';
            $ext_id = $this->di['db']->store($ext);
        }
        $ext_id ??= $ext->id;
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminActivateExtension', 'params' => ['id' => $ext_id]]);

        try {
            $result = $this->activate($ext);
        } catch (\Exception $e) {
            $this->di['db']->trash($ext);

            throw $e;
        }
        $this->di['events_manager']->fire(['event' => 'onAfterAdminActivateExtension', 'params' => ['id' => $ext_id]]);
        $this->di['logger']->info('Activated extension "%s"', $data['id']);

        return $result;
    }

    public function getConfig($ext): array
    {
        return $this->di['cache']->get("config_$ext", function (ItemInterface $item) use ($ext) {
            $item->expiresAfter(60 * 60);

            $c = $this->di['db']->findOne('ExtensionMeta', 'extension = :ext AND meta_key = :key', [':ext' => $ext, ':key' => 'config']);
            if (is_null($c)) {
                $c = $this->di['db']->dispense('ExtensionMeta');
                $c->extension = $ext;
                $c->meta_key = 'config';
                $c->meta_value = null;
                $c->created_at = date('Y-m-d H:i:s');
                $c->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($c);
                $config = [];
            } else {
                $config = $this->di['crypt']->decrypt($c->meta_value, $this->_getSalt());

                if (is_string($config) && json_validate($config)) {
                    $config = json_decode($config, true);
                } else {
                    $config = [];
                }
            }

            $config['ext'] = $ext;

            return $config;
        });
    }

    public function setConfig($data)
    {
        $this->hasManagePermission($data['ext']);
        $ext = $data['ext'];
        $this->getConfig($ext); // Creates new config if it does not exist in DB

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminExtensionConfigSave', 'params' => $data]);
        $sql = "
            UPDATE extension_meta
            SET meta_value = :config
            WHERE extension = :ext
            AND meta_key = 'config'
            LIMIT 1;
        ";

        $config = json_encode($data);
        $config = $this->di['crypt']->encrypt($config, $this->_getSalt());

        $params = [
            'ext' => $ext,
            'config' => $config,
        ];
        $this->di['db']->exec($sql, $params);
        $this->di['events_manager']->fire(['event' => 'onAfterAdminExtensionConfigSave', 'params' => $data]);
        $this->di['logger']->info('Updated extension "%s" configuration', $ext);
        $this->di['cache']->delete("config_$ext");

        return true;
    }

    private function _getSalt()
    {
        return Config::getProperty('info.salt');
    }

    /**
     * @return mixed[]
     */
    public function getCoreAndActiveModules(): array
    {
        $query = "SELECT name, name
                FROM extension
                WHERE `type` = 'mod'
                AND status = 'installed'
               ";
        $stmt = $this->di['pdo']->prepare($query);
        $stmt->execute();
        $extensions = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        if (!$extensions) {
            $list = [];
        } else {
            $list = array_values($extensions);
        }

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
            if ($module == 'index' || $module == 'dashboard') {
                continue;
            }

            // If getSpecificModulePermissions returns false, we need to skip that module and not include it in the permissions list
            $permissions = $this->getSpecificModulePermissions($module, true);
            if ($permissions === false) {
                continue;
            } else {
                $modules[$module]['permissions'] = $permissions;
            }
        }

        return $modules;
    }

    public function getSpecificModulePermissions(string $module, bool $buildingCompleteList = false): array|false
    {
        $class = 'Box\Mod\\' . ucfirst($module) . '\Service';
        if (class_exists($class) && method_exists($class, 'getModulePermissions')) {
            $moduleService = new $class();
            if (method_exists($moduleService, 'setDi')) {
                $moduleService->setDi($this->di);
            }
            $permissions = $moduleService->getModulePermissions();

            if (isset($permissions['hide_permissions']) && $permissions['hide_permissions']) {
                return $buildingCompleteList ? false : [];
            } else {
                unset($permissions['hide_permissions']);

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
        }

        return [];
    }

    // Checks if the current user has permission to edit a module's settings
    public function hasManagePermission(string $module, \Box_App $app = null): void
    {
        $staff_service = $this->di['mod_service']('Staff');

        // The module isn't active or has no permissions if this is the case, so continue as normal
        if (!$this->isExtensionActive('mod', $module)) {
            return;
        }

        // First check if any access is allowed to the module for this person
        if (!$staff_service->hasPermission(null, $module)) {
            http_response_code(403);
            $e = new \FOSSBilling\InformationException('You do not have permission to access the :mod: module', [':mod:' => $module], 403);
            if (!is_null($app)) {
                echo $app->render('error', ['exception' => $e]);
                exit;
            } else {
                throw $e;
            }
        }

        $module_permissions = $this->getSpecificModulePermissions($module);

        // If they have access, let's see if that module has a permission specifically for managing settings and check if they have that permission.
        if (array_key_exists('manage_settings', $module_permissions) && !$staff_service->hasPermission(null, $module, 'manage_settings')) {
            http_response_code(403);
            $e = new \FOSSBilling\InformationException('You do not have permission to perform this action', [], 403);
            if (!is_null($app)) {
                echo $app->render('error', ['exception' => $e]);
                exit;
            } else {
                throw $e;
            }
        }
    }
}
