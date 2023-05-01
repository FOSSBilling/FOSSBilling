<?php

/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Extension;

use Box\InjectionAwareInterface;
use PhpZip\ZipFile;
use Symfony\Component\HttpClient\HttpClient;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function isCoreModule($mod)
    {
        $core = $this->di['mod']('extension')->getCoreModules();

        return in_array($mod, $core);
    }

    public function isExtensionActive($type, $id)
    {
        if ('mod' == $type && $this->isCoreModule($id)) {
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

        return 0 == $removedItems ? true : $removedItems;
    }

    public function getSearchQuery($filter)
    {
        $search = $filter['search'] ?? null;
        $type = $filter['type'] ?? null;

        $params = [];
        $sql = "SELECT * FROM extension
            WHERE status = 'installed' ";

        if (null !== $type) {
            $sql .= ' AND type = :type';
            $params[':type'] = $type;
        }

        if (null !== $search) {
            $sql .= ' AND name LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY type ASC, status DESC, id ASC';

        return [$sql, $params];
    }

    public function getExtensionsList($filter)
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
            $manifest = json_decode($im['manifest'], 1);
            if (!is_array($manifest)) {
                error_log('Error decoding module json file. ' . $im['name']);
                continue;
            }
            $m = $this->di['mod']($im['name']);
            $manifest['version'] = $im['version'];

            $manifest['status'] = $im['status'];
            if ('mod' == $im['type'] && 'installed' == $im['status']) {
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
                $manifest['settings_routes'] = array();

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
                $iconPath = $this->di['config']['url'] . $icon_url;
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

    private function _getAvailable()
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
                            array_push($subpages, $nn);
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
        $extension = $this->di['db']->findOne('Extension', 'type = ? and name = ? ', [$type, $id]);

        return $extension;
    }

    public function update(\Model_Extension $model)
    {
        throw new \Box_Exception('Visit the extension directory for more information on updating this extension.', null, 252);
    }

    public function activate(\Model_Extension $ext)
    {
        $result = [
            'id' => $ext->name,
            'type' => $ext->type,
            'redirect' => false,
            'has_settings' => false,
        ];

        switch ($ext->type) {
            case \FOSSBilling_ExtensionManager::TYPE_MOD:
                $mod = $this->di['mod']($ext->name);
                $manifest = $mod->getManifest();
                $this->installModule($ext);
                $ext->version = $manifest['version'];
                $ext->manifest = json_encode($manifest);
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
        switch ($ext->type) {
            case \FOSSBilling_ExtensionManager::TYPE_HOOK:
                $file = ucfirst($ext->name) . '.php';
                $destination = PATH_LIBRARY . '/Hook/' . $file;
                if (file_exists($destination)) {
                    unlink($destination);
                }
                break;

            case \FOSSBilling_ExtensionManager::TYPE_MOD:
                $mod = $ext->name;
                if ($this->isCoreModule($mod)) {
                    throw new \Box_Exception('FOSSBilling core modules can not be managed');
                }

                try {
                    $mm = $this->di['mod']($mod);
                    $mm->uninstall();
                } catch (\Box_Exception $e) {
                    if (408 != $e->getCode()) {
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
        $this->deactivate($ext);

        switch ($ext->type) {
            case \FOSSBilling_ExtensionManager::TYPE_MOD:
                break;

            default:
                break;
        }

        $this->di['db']->trash($ext);

        return true;
    }

    public function downloadAndExtract($type, $id)
    {
        $latest = $this->di['extension_manager']->getLatestExtensionRelease($id);

        if (!isset($latest['download_url'])) {
            throw new \Exception('Coudn\'t find a valid download URL for the extension.');
        }

        if (!$this->di['extension_manager']->isExtensionCompatible($id)) {
            throw new \Exception('This extension is not compatible with your version of FOSSBilling. Please update FOSSBilling to the latest version and try again.');
        }

        $extractedPath = PATH_CACHE . '/' . md5(uniqid());
        $zipPath = PATH_CACHE . '/' . md5(uniqid()) . '.zip';

        // Create a temporary directory to extract the extension
        mkdir($extractedPath, 0755, true);

        // Download the extension archive and save it to the cache folder
        $fileHandler = fopen($zipPath, 'w');
        $client = HttpClient::create();
        $response = $client->request('GET', $latest['download_url']);

        $code = $response->getStatusCode();
        if ($code !== 200) {
            throw new \Box_Exception("Failed to download the extension with error :code", [':code' => $code]);
        }

        foreach ($client->stream($response) as $chunk) {
            fwrite($fileHandler, $chunk->getContent());
        }

        // Extract the archive
        $zip = new ZipFile();
        try {
            $zip->openFile($zipPath);
            $zip->extractTo($extractedPath);
            $zip->close();
        } catch (\PhpZip\Exception\ZipException $e) {
            error_log($e->getMessage());
            throw new \Box_Exception('Failed to extract file, please check file and folder permissions. Further details are available in the error log.');
        }

        switch ($type) {
            case \FOSSBilling_ExtensionManager::TYPE_MOD:
                $destination = PATH_MODS . DIRECTORY_SEPARATOR . $id;
                break;
            case \FOSSBilling_ExtensionManager::TYPE_THEME:
                $destination = PATH_THEMES . DIRECTORY_SEPARATOR . $id;
                break;
            case \FOSSBilling_ExtensionManager::TYPE_TRANSLATION:
                $destination = PATH_LANGS . DIRECTORY_SEPARATOR . $id . '/LC_MESSAGES';
                break;
            case \FOSSBilling_ExtensionManager::TYPE_PG:
                $destination = PATH_LIBRARY . DIRECTORY_SEPARATOR . 'Payment' . DIRECTORY_SEPARATOR . 'Adapter' . DIRECTORY_SEPARATOR . $id;
                break;
        }

        if (isset($destination)) {
            if (file_exists($destination)) {
                throw new \Box_Exception('Extension :id seems to be already installed.', [':id' => $id], 436);
            }
            if (!rename($extractedPath, $destination)) {
                throw new \Box_Exception('Failed to move extension to it\'s final destination. Please check permissions for the destination folder. (:destination)', [':destination' => $destination], 437);
            }
        } else {
            throw new \Box_Exception('Extension type (:type) cannot be automatically installed.', [':type' => $type]);
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
        $mod = $this->di['mod']($ext->name);

        if ($mod->isCore()) {
            throw new \Box_Exception('FOSSBilling core modules can not be installed or removed');
        }

        $info = $mod->getManifest();
        if (isset($info['minimum_boxbilling_version']) && \FOSSBilling_Version::compareVersion($info['minimum_boxbilling_version']) > 0) {
            throw new \Box_Exception('Module can not be installed. It requires at least :min version of FOSSBilling. You are using :v', [':min' => $info['minimum_boxbilling_version'], ':v' => \FOSSBilling_Version::VERSION]);
        }

        // Allow install module even if no installer exists
        // as it can be simple module
        // perform install script if available
        try {
            $mod->install();
        } catch (\Box_Exception $e) {
            if (408 != $e->getCode()) {
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
            $ext->manifest = null;
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

    public function getConfig($ext)
    {
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
            $config = $this->di['tools']->decodeJ($config);
        }

        return $config;
    }

    public function setConfig($data)
    {
        $this->getConfig($data['ext']); // Creates new config if it does not exist in DB

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
            'ext' => $data['ext'],
            'config' => $config,
        ];
        $this->di['db']->exec($sql, $params);
        $this->di['events_manager']->fire(['event' => 'onAfterAdminExtensionConfigSave', 'params' => $data]);
        $this->di['logger']->info('Updated extension "%s" configuration', $data['ext']);

        return true;
    }

    private function _getSalt()
    {
        return $this->di['config']['salt'];
    }

    public function getCoreAndActiveModules()
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

        $result = array_merge($mods, $list);
        sort($result);

        return $result;
    }
}
