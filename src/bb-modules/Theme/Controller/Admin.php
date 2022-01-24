<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


namespace Box\Mod\Theme\Controller;

class Admin implements \Box\InjectionAwareInterface
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

    public function register(\Box_App &$app)
    {
        $app->get('/theme/:theme',           'get_theme', array('theme' => '[a-z0-9-_]+'), get_class($this));
        $app->post('/theme/:theme',           'save_theme_settings', array('theme' => '[a-z0-9-_]+'), get_class($this));
    }

    /**
     * Save theme settings
     *
     * @param string $code - client area theme code
     * @return array
     */
    public function save_theme_settings(\Box_App $app, $theme)
    {
        $this->di['events_manager']->fire(array('event' => 'onBeforeThemeSettingsSave', 'params' => $_POST));

        $api = $this->di['api_admin'];

        $mod = $this->di['mod']('theme');
        $service = $mod->getService();
        $t = $service->getTheme($theme);

        $isNewPreset = isset($_POST['save-current-setting']) ? (bool)$_POST['save-current-setting'] : false;
        $preset = $service->getCurrentThemePreset($t);
        if($isNewPreset && isset($_POST['save-current-setting-preset']) && !empty($_POST['save-current-setting-preset'])) {
            $preset = $_POST['save-current-setting-preset'];
            $preset = str_replace(" ", "", $preset);
            $service->setCurrentThemePreset($t, $preset);
        }

        unset($_POST['save-current-setting-preset']);
        unset($_POST['save-current-setting']);


        $error = null;
        try {
            if(!$t->isAssetsPathWritable()) {
                throw new \Box_Exception('Theme ":name" assets folder is not writable. Files can not be uploaded and settings can not be saved. Set folder permissions to 777', array(':name'=>$t->getName()));
            }
            $service->updateSettings($t, $preset, $_POST);
            $service->uploadAssets($t, $_FILES);
            $service->regenerateThemeCssAndJsFiles($t, $preset, $api);
        } catch(\Exception $e) {
            error_log($e);
            $error = $e->getMessage();
        }

        //optional data file
        try {
            $service->regenerateThemeSettingsDataFile($t);
        } catch(\Exception $e) {
            error_log($e);
            $error = $e->getMessage();
        }

        $red_url = '/theme/'.$theme;
        if($error) {
            $red_url .= '?error='.$error;
        }
        $app->redirect($red_url);
    }

    public function get_theme(\Box_App $app, $theme)
    {
        $this->di['is_admin_logged'];

        $mod = $this->di['mod']('theme');
        $service = $mod->getService();
        $t = $service->getTheme($theme);
        $preset = $service->getCurrentThemePreset($t);
        $html = $t->getSettingsPageHtml($theme);

        $info = null;
        if(!$t->isAssetsPathWritable()) {
            $info = __('Theme ":name" assets folder is not writable. Set folder :folder permissions to 777', array(':name'=>$t->getName(), ':folder'=>$t->getPathAssets()));
        }

        if(empty($html)) {
            $info = __('Theme ":name" is not configurable', array(':name'=>$t->getName()));
        }


        $data = array(
            'info'          => $info,
            'error'         => $this->di['array_get']($_GET, 'error', null),
            'theme_code'    => $t->getName(),
            'settings_html' => $html,
            'uploaded'      => $t->getUploadedAssets($theme),
            'settings'      => $service->getThemeSettings($t, $preset),
            'current_preset'=> $preset,
            'presets'       => $service->getThemePresets($t),
            'snippets'      => $t->getSnippets(),
        );

        return $app->render('mod_theme_preset', $data);
    }
}