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

namespace Box\Mod\Theme;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public function getTheme($name)
    {
        $theme = new \Box\Mod\Theme\Model\Theme($name);
        return $theme;
    }

    public function getCurrentThemePreset(\Box\Mod\Theme\Model\Theme $theme)
    {
        $current = $this->di['db']->getCell("SELECT meta_value
        FROM extension_meta
        WHERE 1
        AND extension = 'mod_theme'
        AND rel_id = 'current'
        AND rel_type = 'preset'
        AND meta_key = :theme",
            array(':theme'=>$theme->getName()));
        if(empty($current)) {
            $current = $theme->getCurrentPreset();
            $this->setCurrentThemePreset($theme, $current);
        }
        return $current;
    }

    public function setCurrentThemePreset(\Box\Mod\Theme\Model\Theme $theme, $preset)
    {
        $params = array('theme'=>$theme->getName(), 'preset'=>$preset);
        $updated = $this->di['db']->exec("
            UPDATE extension_meta
            SET meta_value = :preset
            WHERE 1
            AND extension = 'mod_theme'
            AND rel_type = 'preset'
            AND rel_id = 'current'
            AND meta_key = :theme
            LIMIT 1
            ", $params);

        if(!$updated) {
            $updated = $this->di['db']->exec("
            INSERT INTO extension_meta (
                extension,
                rel_type,
                rel_id,
                meta_value,
                meta_key,
                created_at,
                updated_at
            )
            VALUES (
                'mod_theme',
                'preset',
                'current',
                :preset,
                :theme,
                NOW(),
                NOW()
            )
            ", $params);
        }

        return true;
    }

    public function deletePreset(\Box\Mod\Theme\Model\Theme $theme, $preset)
    {
        //delete settings
        $this->di['db']->exec("DELETE FROM extension_meta
            WHERE extension = 'mod_theme'
            AND rel_type = 'settings'
            AND rel_id = :theme
            AND meta_key = :preset",
            array('theme'=>$theme->getName(), 'preset'=>$preset));

        //delete default preset
        $this->di['db']->exec("DELETE FROM extension_meta
            WHERE extension = 'mod_theme'
            AND rel_type = 'preset'
            AND rel_id = 'current'
            AND meta_key = :theme",
            array('theme'=>$theme->getName()));
        return true;
    }

    public function getThemePresets(\Box\Mod\Theme\Model\Theme $theme)
    {
        $presets = $this->di['db']->getAssoc("SELECT meta_key FROM extension_meta WHERE extension = 'mod_theme' AND rel_type = 'settings' AND rel_id = :key",
            array('key'=>$theme->getName()));

        //insert default presets to database
        if(empty($presets)) {
            $core_presets = $theme->getPresetsFromSettingsDataFile();
            $presets = array();
            foreach($core_presets as $preset=>$params) {
                $presets[$preset] = $preset;
                $this->updateSettings($theme, $preset, $params);
            }
        }

        //if theme does not have settings data file
        if(empty($presets)) {
            $presets = array('Default'=>'Default');
        }

        return $presets;
    }

    public function getThemeSettings(\Box\Mod\Theme\Model\Theme $theme, $preset = null)
    {
        if(is_null($preset)) {
            $preset = $this->getCurrentThemePreset($theme);
        }

        $meta = $this->di['db']->findOne('ExtensionMeta',
            "extension = 'mod_theme' AND rel_type = 'settings' AND rel_id = :theme AND meta_key = :preset",
            array('theme'=>$theme->getName(), 'preset'=>$preset));
        if($meta) {
            return json_decode($meta->meta_value, 1);
        } else {
            return $theme->getPresetFromSettingsDataFile($preset);
        }
    }

    public function uploadAssets(\Box\Mod\Theme\Model\Theme $theme, array $files)
    {
        $dest = $theme->getPathAssets() . DIRECTORY_SEPARATOR;

        foreach($files as $filename=>$f) {

            if($f['error'] == UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $filename = str_replace('_', '.', $filename);
            if ($f["error"] != UPLOAD_ERR_OK) {
                throw new \Box_Exception("Error uploading file :file Error code: :error", array(":file"=>$filename, ':error'=>$f['error']));
            }

            move_uploaded_file($f["tmp_name"], $dest.$filename);
        }

    }

    public function updateSettings(\Box\Mod\Theme\Model\Theme $theme, $preset, array $params)
    {
        $meta = $this->di['db']->findOne('ExtensionMeta',
            "extension = 'mod_theme' AND rel_type = 'settings' AND rel_id = :theme AND meta_key = :preset",
            array('theme'=>$theme->getName(), 'preset'=>$preset));

        if(!$meta) {
            $meta = $this->di['db']->dispense('ExtensionMeta');
            $meta->extension = 'mod_theme';
            $meta->rel_type = 'settings';
            $meta->rel_id = $theme->getName();
            $meta->meta_key = $preset;
            $meta->created_at = date('Y-m-d H:i:s');
        }

        $meta->meta_value = json_encode($params);
        $meta->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($meta);

        return true;
    }

    public function regenerateThemeSettingsDataFile(\Box\Mod\Theme\Model\Theme $theme)
    {
        $settings = array();
        $presets = $this->getThemePresets($theme);
        foreach($presets as $preset) {
            $settings['presets'][$preset] = $this->getThemeSettings($theme, $preset);
        }
        $settings['current'] = $this->getCurrentThemePreset($theme);
        $data_file = $theme->getPathSettingsDataFile();

        $this->di['tools']->file_put_contents(json_encode($settings), $data_file);

        return true;
    }

    public function regenerateThemeCssAndJsFiles(\Box\Mod\Theme\Model\Theme $theme, $preset, $api_admin)
    {
        $assets = $theme->getPathAssets() . DIRECTORY_SEPARATOR;

        $css_files = $this->di['tools']->glob($assets . '*.css.phtml');
        $js_files = $this->di['tools']->glob($assets . '*.js.phtml');
        $files = array_merge($css_files, $js_files);

        foreach($files as $file) {
            $settings = $this->getThemeSettings($theme, $preset);
            $real_file = pathinfo($file, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR .pathinfo($file, PATHINFO_FILENAME);

            $vars = array();

            $vars['settings'] = $settings;
            $vars['_tpl'] = $this->di['tools']->file_get_contents($file);
            $systemService = $this->di['mod_service']('system');
            $data = $systemService->renderString($vars['_tpl'], false, $vars);

            $this->di['tools']->file_put_contents($data, $real_file);
        }
        return true;
    }

    public function getCurrentAdminAreaTheme()
    {
        $query = "SELECT value
                FROM setting
                WHERE param = :param
               ";
        $default = 'admin_default';
        $theme = $this->di['db']->getCell($query, array('param'=>'admin_theme'));
        $path = BB_PATH_THEMES . DIRECTORY_SEPARATOR . $theme;
        if(null == $theme || !file_exists($path.$theme)){
            $theme = $default;
        }
        $url = $this->di['config']['url'].'bb-themes/'.$theme.'/';
        return array('code'=>$theme, 'url'=>$url);
    }

    public function getCurrentClientAreaTheme()
    {
        $code = $this->getCurrentClientAreaThemeCode();
        return $this->getTheme($code);
    }

    public function getCurrentClientAreaThemeCode()
    {
        if(defined('BB_THEME_CLIENT')) {
            $theme = BB_THEME_CLIENT;
        } else {
            $theme = $this->di['db']->getCell("SELECT value FROM setting WHERE param = 'theme' ");
        }

        return !empty($theme) ? $theme : 'boxbilling';
    }

    public function getThemes($client = true)
    {
        $list = array();
        $path = $this->getThemesPath();
        if ($handle = opendir($path)) {
            while (false !== ($file = readdir($handle))) {
                if (is_dir($path . DIRECTORY_SEPARATOR . $file) && $file[0] != '.') {
                    try {
                        if (!$client && strpos($file, 'admin') !== false) {
                            $list[] = $this->_loadTheme($file);
                        }

                        if ($client && strpos($file, 'admin') === false) {
                            $list[] = $this->_loadTheme($file);
                        }
                    } catch (\Exception $e) {
                        error_log($e->getMessage());
                    }
                }
            }
        }

        return $list;
    }


    public function getThemeConfig($client = true, $mod = null)
    {
        if ($client) {
            $theme   = $this->getCurrentClientAreaThemeCode();
        } else {
            $default = 'admin_default';
            $systemService = $this->di['mod_service']('system');
            $theme   = $systemService->getParamValue('admin_theme', $default);
        }

        $path = $this->getThemesPath();
        if (!file_exists($path . $theme)) {
            $theme = $default;
        }

        return $this->_loadTheme($theme, $client, $mod);
    }

    public function loadTheme($code, $client = true, $mod = null)
    {
        return $this->_loadTheme($code, $client, $mod);
    }

    public function getThemesPath()
    {
        return BB_PATH_THEMES . DIRECTORY_SEPARATOR;
    }

    private function _loadTheme($theme, $client = true, $mod = null)
    {
        $theme_path = $this->getThemesPath() . $theme;

        if (!file_exists($theme_path)) {
            throw new \Box_Exception('Theme was not found in path :path', array(':path' => $theme_path));
        }
        $manifest = $theme_path . '/manifest.json';

        if (file_exists($manifest)) {
            $config = json_decode(file_get_contents($manifest), true);
        } else {
            $config = array(
                'name'        => $theme,
                'version'     => '1.0',
                'description' => 'Theme',
                'author'      => 'BoxBilling',
                'author_url'  => 'http://www.boxbilling.com'
            );
        }

        if (!is_array($config)) {
            throw new \Box_Exception('Unable to decode theme manifest file :file', array(':file' => $manifest));
        }

        $paths = array($theme_path . '/html');

        if (isset($config['extends'])) {
            $ext = trim($config['extends'], '/');
            $ext = str_replace('.', '', $ext);

            $config['url'] = BB_URL . 'bb-themes/' . $ext . '/';
            array_push($paths, $this->getThemesPath() . $ext . '/html');
        } else {
            $config['url'] = BB_URL . 'bb-themes/' . $theme . '/';
        }

        //add installed modules paths
        $table = $this->di['mod_service']('extension');
        $list  = $table->getCoreAndActiveModules();
        //add module folder to look for template
        if (!is_null($mod)) {
            $list[] = $mod;
        }
        $list = array_unique($list);
        foreach ($list as $mod) {
            $p = BB_PATH_MODS . DIRECTORY_SEPARATOR . ucfirst($mod) . DIRECTORY_SEPARATOR;
            $p .= $client ? 'html_client' : 'html_admin';
            if (file_exists($p)) {
                array_push($paths, $p);
            }
        }

        $config['code']  = $theme;
        $config['paths'] = $paths;

        return $config;
    }
}
