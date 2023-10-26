<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Box_Mod
{
    private ?string $mod = null;

    private ?\Pimple\Container $di = null;

    private array $core = array(
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
    );

    /**
     * @param string $mod
     */
    public function __construct($mod)
    {
        if(!preg_match('#[a-zA-Z]#', $mod)) {
            throw new \FOSSBilling\Exception('Invalid module name');
        }

        $this->mod = strtolower($mod);
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function hasManifest()
    {
        return file_exists($this->_getModPath() . 'manifest.json');
    }

    public function getManifest()
    {
        if(!$this->hasManifest()) {
            throw new \FOSSBilling\Exception('Module :mod manifest file is missing', array(':mod'=>$this->mod), 5897);
        }
        $file = $this->_getModPath() . 'manifest.json';
        $json = json_decode(file_get_contents($file), true);
        if(empty ($json)) {
            throw new \FOSSBilling\Exception('Module :mod manifest file is invalid. Check file syntax and permissions.', array(':mod'=>$this->mod));
        }

        //default module info if some fields are missing
        $info = array(
            'id'            => $this->mod,
            'type'          => 'mod',
            'name'          => $this->mod,
            'description'   => NULL,
            'homepage_url'  => 'https://fossbilling.org/',
            'author'        => 'FOSSBilling',
            'author_url'    => 'https://extensions.fossbilling.org/',
            'license'       => 'N/A',
            'version'       => '1.0',
            'icon_url'      => NULL,
            'download_url'  => NULL,
            'project_url'   => 'https://extensions.fossbilling.org/',
            "minimum_boxbilling_version" => NULL,
            "maximum_boxbilling_version" => NULL,
        );

        $info = array_merge($info, $json);
        $info['id'] = $this->mod;
        $info['type'] = 'mod';

        if(!empty($info['icon_url'])) {
            $info['icon_url'] = '/modules/'.ucfirst($this->mod).'/'.$info['icon_url'];
        }

        return $info;
    }

    public function hasService($sub = '')
    {
        $filename = sprintf('Service%s.php', ucfirst($sub));
        return file_exists($this->_getModPath() . $filename);
    }

    public function getService($sub = '')
    {
        if(!$this->hasService($sub)) {
            throw new \FOSSBilling\Exception('Module :mod does not have service class', array(':mod'=>$this->mod), 5898);
        }
        $class = 'Box\\Mod\\'.ucfirst($this->mod).'\\Service'.ucfirst($sub);
    	$service = new $class();
        if(method_exists($service, 'setDi')) {
            $service->setDi($this->di);
        }
        return $service;
    }

    public function hasClientController()
    {
        return file_exists($this->_getModPath() . 'Controller/Client.php');
    }

    public function getClientController()
    {
        if(!$this->hasClientController()) {
            throw new \FOSSBilling\Exception('Module :mod Client controller class was not found', array(':mod'=>$this->mod));
        }

        $class = 'Box\\Mod\\'.ucfirst($this->mod).'\\Controller\\Client';
        $service = new $class();
        if(method_exists($service, 'setDi')) {
            $service->setDi($this->di);
        }
    	return $service;
    }

    public function hasSettingsPage()
    {
        return file_exists($this->_getModPath() . 'html_admin/mod_'.$this->mod.'_settings.html.twig');
    }

    public function hasAdminController()
    {
        return file_exists($this->_getModPath() . 'Controller/Admin.php');
    }

    public function getAdminController()
    {
        if(!$this->hasAdminController()) {
            return null;
        }
        $class = 'Box\\Mod\\'.ucfirst($this->mod).'\\Controller\\Admin';
        $service = new $class();
        if(method_exists($service, 'setDi')) {
            $service->setDi($this->di);
        }
    	return $service;
    }

    public function install()
    {
        if($this->isCore()) {
            return true;
        }

        if($this->hasService()) {
            $s = $this->getService();
            if(method_exists($s, 'install')) {
                $s->install();
                return true;
            }
        }

        return false;
    }

    public function uninstall()
    {
        if($this->isCore()) {
            return true;
        }

        if($this->hasService()) {
            $s = $this->getService();
            if(method_exists($s, 'uninstall')) {
                $s->uninstall();
                return true;
            }
        }
        return false;
    }

    public function update()
    {
        if($this->isCore()) {
            throw new \FOSSBilling\InformationException('Core module can not be updated');
        }

        if($this->hasService()) {
            $s = $this->getService();
            if(method_exists($s, 'update')) {
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
        $bb_config = $this->di['config'];
        $config = array();

        $modName = 'mod_'.strtolower($this->mod);
        $c = $db->findOne('extension_meta', 'extension = :ext AND meta_key = :key', array(':ext'=>$modName, ':key'=>'config'));
        if($c) {
            $config = $this->di['crypt']->decrypt($c->meta_value, $bb_config['info']['salt']);
            $config = $this->di['tools']->decodeJ($config);
        }
        return $config;
    }

    public function getName()
    {
        return $this->mod;
    }

    public function registerClientRoutes(Box_App &$app)
    {
        if($this->hasClientController()) {
            $cc = $this->getClientController();
            if(method_exists($cc, 'register')) {
                $cc->register($app);
                return true;
            }
        }
        return false;
    }

    private function _getModPath()
    {
        return PATH_MODS . DIRECTORY_SEPARATOR . ucfirst($this->mod) . DIRECTORY_SEPARATOR;
    }
}
