<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


namespace Box\Mod\Extension;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected $di = null;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
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
        if($type == 'mod' && $this->isCoreModule($id)) {
            return true;
        }

        $query = "SELECT id
                FROM extension
                WHERE type = :type
                AND status = 'installed'
                AND name = :id
                LIMIT 1
               ";

        $id_or_null = $this->di['db']->getCell($query, array('type'=>$type, 'id'=>$id));
        return (bool)$id_or_null;
    }

    public static function onBeforeAdminCronRun(\Box_Event $event)
    {
        $di               = $event->getDi();
        $extensionService = $di['mod_service']('extension');

        try {
            $extensionService->getExtensionsList(array());
        } catch (\Exception $e) {
            error_log($e);
        }

        return true;
    }

    public function removeNotExistingModules()
    {
        $list = $this->di['db']->find('Extension', "type = 'mod'");
        $removedItems = 0;
        foreach($list as $ext) {
            try {
                $mod = $this->di['mod']($ext->name);
                $mod->getManifest();
            } catch(\Exception $e) {
                $this->di['db']->trash($ext);
                $removedItems++;
            }
        }
        return $removedItems == 0 ? true : $removedItems;
    }

    public function getSearchQuery($filter)
    {
        $search = $this->di['array_get']($filter, 'search', NULL);
        $type = $this->di['array_get']($filter, 'type', NULL);

        $params = array();
        $sql="SELECT * FROM extension
            WHERE status = 'installed' ";

        if(NULL !== $type) {
            $sql .= ' AND type = :type';
            $params[':type'] = $type;
        }

        if(NULL !== $search) {
            $sql .= ' AND name LIKE :search';
            $params[':search'] = '%'.$search.'%';
        }

        $sql .= ' ORDER BY type ASC, status DESC, id ASC';
        return array($sql, $params);
    }

    public function getExtensionsList($filter)
    {
        $this->removeNotExistingModules();

        list($sql, $params) = $this->getSearchQuery($filter);
        $installed = $this->di['db']->getAll($sql, $params);

        $has_settings       = $this->di['array_get']($filter, 'has_settings');
        $only_installed     = $this->di['array_get']($filter, 'installed');
        $installed_and_core = $this->di['array_get']($filter, 'active');
        $search             = isset($filter['search']) ? strtolower($filter['search']) : NULL;
        $result             = array();

        if ($installed_and_core) {
            $core = $this->di['mod']('extension')->getCoreModules();
            foreach ($core as $core_mod) {
                $m                        = $this->di['mod']($core_mod);
                $manifest                 = $m->getManifest();
                $manifest['status']       = 'core';
                $manifest['has_settings'] = $m->hasSettingsPage();
                $result[]                 = $manifest;
            }
        }

        foreach ($installed as $im) {
            $manifest = json_decode($im['manifest'], 1);
            if (!is_array($manifest)) {
                error_log('Error decoding module json file. ' . $im['name']);
                continue;
            }
            $m                   = $this->di['mod']($im['name']);
            $manifest['version'] = $im['version'];

            $manifest['status'] = $im['status'];
            if ($im['type'] == 'mod' && $im['status'] == 'installed' && $m->hasSettingsPage()) {
                $manifest['has_settings'] = true;
            } else {
                $manifest['has_settings'] = false;
            }

            $result[] = $manifest;
        }

        if (!$only_installed && !$installed_and_core) {
            //add inactive modules
            $a = $this->_getAvailable();

            //unset installed modules
            foreach ($installed as $ins) {
                if (in_array($ins['name'], $a)) {
                    $key = array_search($ins['name'], $a);
                    unset($a[$key]);
                }
            }

            foreach ($a as $mod) {
                $m                        = $this->di['mod']($mod);
                $manifest                 = $m->getManifest();
                $manifest['status']       = null;
                $manifest['has_settings'] = false;

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
                if (strpos(strtolower($mod['name']), $search) === false) {
                    unset($result[$idx]);
                }

            }
            $result = array_values($result);
        }

        foreach ($result as $key => $value){
            $iconPath = 'images/icons/middlenav/cog.png';
            $icon_url = $this->di['array_get']($value, 'icon_url');
            if ($icon_url){
                $iconPath = $this->di['config']['url'].$icon_url;
            }
            $result[$key]['icon_url'] = $iconPath;
        }

        return $result;
    }

    private function _getAvailable()
    {
        $mods = array();
        $handle = opendir(BB_PATH_MODS);
        while($name = readdir($handle)) {
            if(ctype_alnum($name)) {
                $m = $name;
                $mod = $this->di['mod']($m);
                if($mod->isCore()) {
                    continue;
                }

                if(!$mod->hasManifest()) {
                    error_log('Module '.$m.' manifest file is missing or is not readable.');
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
        $nav = array();
        $subpages = array();

        $modules = $this->di['mod']('extension')->getCoreModules();
        $installed = $this->getInstalledMods();
        $list = array_merge($modules, $installed);
        foreach ($list as $mod) {
            if(!$staff_service->hasPermission($admin, $mod)) {
                continue;
            }

            try {
                $m = $this->di['mod']($mod);
                $obj = $m->getAdminController();
            } catch(\Exception $e) {

                continue;
            }
            if(method_exists($obj, 'fetchNavigation')) {
                $n = $obj->fetchNavigation();

                if(isset($n['group'])) {
                    $l = $n['group']['location'];
                    unset($n['group']['location']);
                    $n['group']['active'] = false;
                    $nav[$l] = $n['group'];
                }

                if(isset($n['subpages'])) {
                    foreach($n['subpages'] as $nn) {
                        if(is_array($nn)) {
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
            if(!isset($page['location'])) {
                error_log('Invalid module menu item: '.print_r($page, 1));
                continue;
            }

            if(!isset($nav[$page['location']])) {
                error_log('Submenu item belongs to not existing location: '.$page['location']);
                continue;
            }

            $l = $page['location'];
            unset($page['location']);
            $nav[$l]['subpages'][] = $page;
        }

        // submenu sorting
        foreach($nav as &$group){
            $group['subpages'] = $this->di['tools']->sortByOneKey($group['subpages'], 'index');
        }

        return $nav;
    }

    /**
     * @return \Model_Extension
     */
    public function findExtension($type, $id)
    {
        $extension = $this->di['db']->findOne('Extension', 'type = ? and name = ? ', array($type, $id));
        return $extension;
    }

    public function update(\Model_Extension $model)
    {
        $result = array();

        if($model->type == 'mod') {

            $ext = $this->di['extension'];
            $latest = $ext->getLatestExtensionVersion($model->name);
            if(empty($latest)) {
                throw new \Box_Exception('Could not retrieve version information for extension :ext', array(':ext'=>$model->name), 745);
            }
            $haveUpdate = (version_compare($model->version, $latest) < 0);
            if(!$haveUpdate) {
                throw new \Box_Exception('Latest :mod version installed. No need to update', array(':mod'=>$model->name), 785);
            }

            $extension = $ext->getExtension($model->name);
            if(empty($extension)) {
                throw new \Box_Exception('Could not retrieve :ext information', array(':ext'=>$model->name), 744);
            }

            throw new \Box_Exception('Visit extension site for update information.', null, 252);

            $result = array(
                'version_old' => $model->version,
                'version_new' => $latest,
            );
        }

        return $result;
    }

    public function activate(\Model_Extension $ext)
    {
        $result = array(
            'id'        => $ext->name,
            'type'      => $ext->type,
            'redirect'  => false,
            'has_settings'  => false,
        );

        switch ($ext->type) {
            case \Box_Extension::TYPE_MOD:
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
            case \Box_Extension::TYPE_HOOK:
                $file = ucfirst($ext->name).'.php';
                $destination = BB_PATH_LIBRARY . '/Hook/'.$file;
                if(file_exists($destination)) {
                    unlink($destination);
                }
                break;

            case \Box_Extension::TYPE_MOD:
                $mod = $ext->name;
                if($this->isCoreModule($mod)) {
                    throw new \Box_Exception("BoxBilling core modules can not be managed");
                }

                try {
                    $mm = $this->di['mod']($mod);
                    $mm->uninstall();
                } catch (\Box_Exception $e) {
                    if($e->getCode() != 408) {
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
            case \Box_Extension::TYPE_MOD:

                break;

            default:
                break;
        }

        $this->di['db']->trash($ext);
        return true;
    }

    public function downloadAndExtract($type, $id)
    {
        $ext = $this->di['extension'];
        $manifest = $ext->getExtension($id, $type);

        if(!isset($manifest['download_url'])) {
            throw new \Exception('Extensions download url is not valid');
        }

        $extracted = BB_PATH_CACHE.'/'.md5(uniqid());
        $zip = BB_PATH_CACHE.'/'.md5(uniqid()).'.zip';

        $curl = $this->di['curl']($manifest['download_url']);
        $curl->downloadTo($zip);

        $em = $this->di['zip_archive'];
        $res = $em->open($zip);
        if ($res === TRUE) {
            $em->extractTo($extracted);
            $em->close();
        } else {
            throw new \Box_Exception('Could not extract extension zip file');
        }

        //install by type
        switch ($type) {
            case \Box_Extension::TYPE_MOD:
                $destination = BB_PATH_MODS . '/mod_'.$id;
                if($this->di['tools']->fileExists($destination)) {
                    throw new \Box_Exception('Module already installed.', null, 436);
                }
                if(!$this->di['tools']->rename($extracted, $destination)) {
                    throw new \Box_Exception('Extension can not be moved. Make sure your server write permissions to bb-modules folder.', null, 437);
                }
                break;

            case \Box_Extension::TYPE_THEME:
                $destination = BB_PATH_THEMES . '/'.$id;
                if(!$this->di['tools']->fileExists($destination)) {
                    if(!$this->di['tools']->rename($extracted, $destination)) {
                        throw new \Box_Exception('Extension can not be moved. Make sure your server write permissions to bb-themes folder.', null, 439);
                    }
                }
                break;

            case \Box_Extension::TYPE_TRANSLATION:
                $destination = BB_PATH_LANGS . '/'.$id.'/LC_MESSAGES';
                $this->di['tools']->emptyFolder($destination);
                if(!$this->di['tools']->fileExists($destination)) {
                    $this->di['tools']->mkdir($destination, 0777, true);
                }
                if(!$this->di['tools']->rename($extracted, $destination)) {
                    throw new \Box_Exception('Extension can not be moved. Make sure your server write permissions to bb-locale folder.', null, 440);
                }
                break;

            default:
                throw new \Box_Exception('Extension does not support auto-install feature. Extension must be installed manually');
        }

        if (file_exists($zip)){
            unlink($zip);
        }
        $this->di['tools']->emptyFolder($extracted);

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

        if($mod->isCore()) {
            throw new \Box_Exception("BoxBilling core modules can not be installed or removed");
        }

        $info = $mod->getManifest();
        if(isset($info['minimum_boxbilling_version']) && Box_Version::compareVersion($info['minimum_boxbilling_version']) > 0) {
            throw new \Box_Exception('Module can not be installed. It requires at least :min version of BoxBilling. You are using :v', array(':min'=>$info['minimum_boxbilling_version'], ':v'=>Box_Version::VERSION));
        }

        // Allow install module even if no installer exists
        // as it can be simple module
        // perform install script if available
        try {
            $mod->install();
        } catch (\Box_Exception $e) {
            if($e->getCode() != 408) {
                throw $e;
            }
        }

        $ext->version     = $info['version'];
        $this->di['db']->store($ext);
        return true;
    }

    public function activateExistingExtension($data)
    {
        $ext = $this->findExtension($data['type'], $data['id']);
        if(!$ext instanceof \Model_Extension) {
            $ext = $this->di['db']->dispense('Extension');
            $ext->name        = $data['id'];
            $ext->type        = $data['type'];
            $ext->version     = null;
            $ext->status      = 'deactivated';
            $ext->manifest    = null;
            $ext_id = $this->di['db']->store($ext);
        }
        $ext_id = isset($ext_id) ? $ext_id : $ext->id;
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminActivateExtension', 'params'=>array('id'=>$ext_id)));
        try {
            $result = $this->activate($ext);
        } catch(\Exception $e) {
            $this->di['db']->trash($ext);
            throw $e;
        }
        $this->di['events_manager']->fire(array('event'=>'onAfterAdminActivateExtension', 'params'=>array('id'=>$ext_id)));
        $this->di['logger']->info('Activated extension "%s"', $data['id']);

        return $result;
    }

    public function getConfig($ext)
    {
        $c = $this->di['db']->findOne('ExtensionMeta', 'extension = :ext AND meta_key = :key', array(':ext'=>$ext, ':key'=>'config'));
        if(is_null($c)) {
            $c = $this->di['db']->dispense('ExtensionMeta');
            $c->extension = $ext;
            $c->meta_key = 'config';
            $c->meta_value = null;
            $c->created_at = date('Y-m-d H:i:s');
            $c->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($c);
            $config = array();
        } else {
            $config = $this->di['crypt']->decrypt($c->meta_value, $this->_getSalt());
            $config = $this->di['tools']->decodeJ($config);
        }
        return $config;
    }

    public function setConfig($data)
    {
        $this->getConfig($data['ext']); //Creates new config if it does not exist in DB

        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminExtensionConfigSave', 'params'=>$data));
        $sql="
            UPDATE extension_meta
            SET meta_value = :config
            WHERE extension = :ext
            AND meta_key = 'config'
            LIMIT 1;
        ";

        $config = json_encode($data);
        $config = $this->di['crypt']->encrypt($config, $this->_getSalt());

        $params = array(
            'ext'        => $data['ext'],
            'config'     => $config,
        );
        $this->di['db']->exec($sql, $params);
        $this->di['events_manager']->fire(array('event'=>'onAfterAdminExtensionConfigSave', 'params'=>$data));
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

        if(!$extensions) {
            $list = array();
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