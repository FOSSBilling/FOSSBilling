<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Theme\Controller;

use Symfony\Component\HttpFoundation\Response;

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

    public function register(\Box_App &$app): void
    {
        $app->get('/theme/:theme', 'get_theme', ['theme' => '[a-z0-9-_]+'], static::class);
        $app->post('/theme/:theme', 'save_theme_settings', ['theme' => '[a-z0-9-_]+'], static::class);
    }

    /**
     * Save theme settings.
     */
    public function save_theme_settings(\Box_App $app, $theme): Response
    {
        $body = $app->getRequest()->request->all();
        $this->di['events_manager']->fire(['event' => 'onBeforeThemeSettingsSave', 'params' => $body]);

        $api = $this->di['api_admin'];

        $mod = $this->di['mod']('theme');
        $service = $mod->getService();
        $t = $service->getTheme($theme);

        $isNewPreset = isset($body['save-current-setting']) && (bool) $body['save-current-setting'];
        $preset = $service->getCurrentThemePreset($t);
        if ($isNewPreset && isset($body['save-current-setting-preset']) && !empty($body['save-current-setting-preset'])) {
            $preset = $body['save-current-setting-preset'];
            $preset = str_replace(' ', '', $preset);
            $service->setCurrentThemePreset($t, $preset);
        }

        unset($body['save-current-setting-preset'], $body['save-current-setting']);

        $error = null;

        try {
            if (!$t->isAssetsPathWritable()) {
                throw new \FOSSBilling\Exception('Theme ":name" assets folder is not writable. Files cannot be uploaded and settings cannot be saved. Set folder permissions to 755', [':name' => $t->getName()]);
            }
            $service->updateSettings($t, $preset, $body);
            $service->regenerateThemeCssAndJsFiles($t, $preset, $api);
        } catch (\Exception $e) {
            $error = $e->getMessage();
            error_log($e->getMessage());
        }

        // optional data file
        try {
            $service->regenerateThemeSettingsDataFile($t);
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        $red_url = '/theme/' . $theme;
        if ($error) {
            $red_url .= '?error=' . $error;
        }

        return $app->redirect($red_url);
    }

    public function get_theme(\Box_App $app, $theme): string
    {
        $this->di['is_admin_logged'];

        $mod = $this->di['mod']('theme');
        $service = $mod->getService();
        $t = $service->getTheme($theme);
        $preset = $service->getCurrentThemePreset($t);
        $settings = $service->getThemeSettings($t, $preset);
        $error = $app->getRequest()->query->get('error');

        try {
            $html = $service->renderThemeSettingsPageHtml($t, $settings);
        } catch (\FOSSBilling\InformationException $e) {
            $html = '';
            $error ??= $e->getMessage();
        }

        $info = null;
        if (!$t->isAssetsPathWritable()) {
            $info = __trans('Theme ":name" assets folder is not writable. Set folder :folder permissions to 777', [':name' => $t->getName(), ':folder' => $t->getPathAssets()]);
        }

        if (empty($html) && empty($error)) {
            $info = __trans('Theme ":name" is not configurable', [':name' => $t->getName()]);
        }

        $data = [
            'info' => $info,
            'error' => $error,
            'theme_code' => $t->getName(),
            'settings_html' => new \Twig\Markup($html, 'UTF-8'),
            'uploaded' => $t->getUploadedAssets(),
            'settings' => $settings,
            'current_preset' => $preset,
            'presets' => $service->getThemePresets($t),
            'snippets' => [
                'client-login-form' => 'Client Area Login Form',
                'domain-checker-form' => 'Domain Checker Form',
                'contact-us-form' => 'Contact Us Form',
            ],
        ];

        return $app->render('mod_theme_preset', $data);
    }
}
