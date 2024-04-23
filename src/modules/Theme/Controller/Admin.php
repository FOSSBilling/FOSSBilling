<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Theme\Controller;

class Admin implements \FOSSBilling\InjectionAwareInterface
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

    public function register(\Box_App &$app)
    {
        $app->get('/theme/:theme', 'get_theme', ['theme' => '[a-z0-9-_]+'], static::class);
        $app->post('/theme/:theme', 'save_theme_settings', ['theme' => '[a-z0-9-_]+'], static::class);
    }

    /**
     * Save theme settings.
     */
    public function save_theme_settings(\Box_App $app, $theme)
    {
        $this->di['events_manager']->fire(['event' => 'onBeforeThemeSettingsSave', 'params' => $_POST]);

        $api = $this->di['api_admin'];

        $mod = $this->di['mod']('theme');
        $service = $mod->getService();
        $t = $service->getTheme($theme);

        $isNewPreset = isset($_POST['save-current-setting']) && (bool) $_POST['save-current-setting'];
        $preset = $service->getCurrentThemePreset($t);
        if ($isNewPreset && isset($_POST['save-current-setting-preset']) && !empty($_POST['save-current-setting-preset'])) {
            $preset = $_POST['save-current-setting-preset'];
            $preset = str_replace(' ', '', $preset);
            $service->setCurrentThemePreset($t, $preset);
        }

        unset($_POST['save-current-setting-preset']);
        unset($_POST['save-current-setting']);

        $error = null;

        try {
            if (!$t->isAssetsPathWritable()) {
                throw new \FOSSBilling\Exception('Theme ":name" assets folder is not writable. Files cannot be uploaded and settings cannot be saved. Set folder permissions to 755', [':name' => $t->getName()]);
            }
            $service->updateSettings($t, $preset, $_POST);
            $service->regenerateThemeCssAndJsFiles($t, $preset, $api);
        } catch (\Exception $e) {
            error_log($e);
            $error = $e->getMessage();
        }

        // optional data file
        try {
            $service->regenerateThemeSettingsDataFile($t);
        } catch (\Exception $e) {
            error_log($e);
            $error = $e->getMessage();
        }

        $red_url = '/theme/' . $theme;
        if ($error) {
            $red_url .= '?error=' . $error;
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
        if (!$t->isAssetsPathWritable()) {
            $info = __trans('Theme ":name" assets folder is not writable. Set folder :folder permissions to 777', [':name' => $t->getName(), ':folder' => $t->getPathAssets()]);
        }

        if (empty($html)) {
            $info = __trans('Theme ":name" is not configurable', [':name' => $t->getName()]);
        }

        $data = [
            'info' => $info,
            'error' => $_GET['error'] ?? null,
            'theme_code' => $t->getName(),
            'settings_html' => $html,
            'uploaded' => $t->getUploadedAssets($theme),
            'settings' => $service->getThemeSettings($t, $preset),
            'current_preset' => $preset,
            'presets' => $service->getThemePresets($t),
            'snippets' => $t->getSnippets(),
        ];

        return $app->render('mod_theme_preset', $data);
    }
}
