<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Theme;

use FOSSBilling\InjectionAwareInterface;

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

    public function getTheme($name)
    {
        return new Model\Theme($name);
    }

    public function getCurrentThemePreset(Model\Theme $theme)
    {
        $current = $this->di['db']->getCell(
            "SELECT meta_value
        FROM extension_meta
        WHERE 1
        AND extension = 'mod_theme'
        AND rel_id = 'current'
        AND rel_type = 'preset'
        AND meta_key = :theme",
            [':theme' => $theme->getName()]
        );
        if (empty($current)) {
            $current = $theme->getCurrentPreset();
            $this->setCurrentThemePreset($theme, $current);
        }

        return $current;
    }

    public function setCurrentThemePreset(Model\Theme $theme, $preset)
    {
        $params = ['theme' => $theme->getName(), 'preset' => $preset];
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

        if (!$updated) {
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

    public function deletePreset(Model\Theme $theme, $preset)
    {
        // delete settings
        $this->di['db']->exec(
            "DELETE FROM extension_meta
            WHERE extension = 'mod_theme'
            AND rel_type = 'settings'
            AND rel_id = :theme
            AND meta_key = :preset",
            ['theme' => $theme->getName(), 'preset' => $preset]
        );

        // delete default preset
        $this->di['db']->exec(
            "DELETE FROM extension_meta
            WHERE extension = 'mod_theme'
            AND rel_type = 'preset'
            AND rel_id = 'current'
            AND meta_key = :theme",
            ['theme' => $theme->getName()]
        );

        return true;
    }

    public function getThemePresets(Model\Theme $theme)
    {
        $presets = $this->di['db']->getAssoc(
            "SELECT meta_key FROM extension_meta WHERE extension = 'mod_theme' AND rel_type = 'settings' AND rel_id = :key",
            ['key' => $theme->getName()]
        );

        // insert default presets to database
        if (empty($presets)) {
            $core_presets = $theme->getPresetsFromSettingsDataFile();
            $presets = [];
            foreach ($core_presets as $preset => $params) {
                $presets[$preset] = $preset;
                $this->updateSettings($theme, $preset, $params);
            }
        }

        // if theme does not have settings data file
        if (empty($presets)) {
            $presets = ['Default' => 'Default'];
        }

        return $presets;
    }

    public function getThemeSettings(Model\Theme $theme, $preset = null)
    {
        if (is_null($preset)) {
            $preset = $this->getCurrentThemePreset($theme);
        }

        $meta = $this->di['db']->findOne(
            'ExtensionMeta',
            "extension = 'mod_theme' AND rel_type = 'settings' AND rel_id = :theme AND meta_key = :preset",
            ['theme' => $theme->getName(), 'preset' => $preset]
        );
        if ($meta) {
            return json_decode($meta->meta_value, 1);
        } else {
            return $theme->getPresetFromSettingsDataFile($preset);
        }
    }

    public function updateSettings(Model\Theme $theme, $preset, array $params)
    {
        $meta = $this->di['db']->findOne(
            'ExtensionMeta',
            "extension = 'mod_theme' AND rel_type = 'settings' AND rel_id = :theme AND meta_key = :preset",
            ['theme' => $theme->getName(), 'preset' => $preset]
        );

        if (!$meta) {
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

    public function regenerateThemeSettingsDataFile(Model\Theme $theme)
    {
        $settings = [];
        $presets = $this->getThemePresets($theme);
        foreach ($presets as $preset) {
            $settings['presets'][$preset] = $this->getThemeSettings($theme, $preset);
        }
        $settings['current'] = $this->getCurrentThemePreset($theme);
        $data_file = $theme->getPathSettingsDataFile();

        file_put_contents($data_file, json_encode($settings));

        return true;
    }

    public function regenerateThemeCssAndJsFiles(Model\Theme $theme, $preset, $api_admin)
    {
        $assets = $theme->getPathAssets() . DIRECTORY_SEPARATOR;

        $css_files = glob($assets . '*.css.html.twig');
        $js_files = glob($assets . '*.js.html.twig');
        $files = array_merge($css_files, $js_files);

        foreach ($files as $file) {
            $settings = $this->getThemeSettings($theme, $preset);
            $real_file = pathinfo($file, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . pathinfo($file, PATHINFO_FILENAME);

            $vars = [];

            $vars['settings'] = $settings;
            $vars['_tpl'] = file_get_contents($file);
            $systemService = $this->di['mod_service']('system');
            $data = $systemService->renderString($vars['_tpl'], false, $vars);

            file_put_contents($real_file, $data);
        }

        return true;
    }

    public function getCurrentAdminAreaTheme()
    {
        $query = 'SELECT value
                FROM setting
                WHERE param = :param
               ';
        $default = 'admin_default';
        $theme = $this->di['db']->getCell($query, ['param' => 'admin_theme']);
        $path = PATH_THEMES . DIRECTORY_SEPARATOR;
        if ($theme == null || !file_exists($path . $theme)) {
            $theme = $default;
        }
        $url = SYSTEM_URL . 'themes' . DIRECTORY_SEPARATOR . $theme . DIRECTORY_SEPARATOR;

        return ['code' => $theme, 'url' => $url];
    }

    public function getCurrentClientAreaTheme()
    {
        $code = $this->getCurrentClientAreaThemeCode();

        return $this->getTheme($code);
    }

    public function getCurrentClientAreaThemeCode()
    {
        $theme = $this->di['db']->getCell("SELECT value FROM setting WHERE param = 'theme' ");

        return !empty($theme) ? $theme : 'huraga';
    }

    /**
     * @return mixed[]
     */
    public function getThemes($client = true): array
    {
        $list = [];
        $path = $this->getThemesPath();
        if ($handle = opendir($path)) {
            while (false !== ($file = readdir($handle))) {
                if (is_dir($path . DIRECTORY_SEPARATOR . $file) && $file[0] != '.') {
                    try {
                        if (!$client && str_contains($file, 'admin')) {
                            $list[] = $this->_loadTheme($file);
                        }

                        if ($client && !str_contains($file, 'admin')) {
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
            $default = 'huraga';
            $theme = $this->getCurrentClientAreaThemeCode();
        } else {
            $default = 'admin_default';
            $systemService = $this->di['mod_service']('system');
            $theme = $systemService->getParamValue('admin_theme', $default);
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
        return PATH_THEMES . DIRECTORY_SEPARATOR;
    }

    private function _loadTheme($theme, $client = true, $mod = null): array
    {
        $theme_path = $this->getThemesPath() . $theme;

        if (!file_exists($theme_path)) {
            throw new \FOSSBilling\Exception('Theme was not found in path :path', [':path' => $theme_path]);
        }
        $manifest = $theme_path . '/manifest.json';

        if (file_exists($manifest)) {
            $config = json_decode(file_get_contents($manifest), true);
        } else {
            $config = [
                'name' => $theme,
                'version' => '1.0',
                'description' => 'Theme',
                'author' => 'FOSSBilling',
                'author_url' => 'https://www.fossbilling.org',
            ];
        }

        if (!is_array($config)) {
            throw new \FOSSBilling\Exception('Unable to decode theme manifest file :file', [':file' => $manifest]);
        }

        $paths = [$theme_path . '/html'];

        if (isset($config['extends'])) {
            $ext = trim($config['extends'], '/');
            $ext = str_replace('.', '', $ext);

            $config['url'] = SYSTEM_URL . 'themes/' . $ext . '/';
            $paths[] = $this->getThemesPath() . $ext . '/html';
        } else {
            $config['url'] = SYSTEM_URL . 'themes/' . $theme . '/';
        }

        // add installed modules paths
        $table = $this->di['mod_service']('extension');
        $list = $table->getCoreAndActiveModules();
        // add module folder to look for template
        if (!is_null($mod)) {
            $list[] = $mod;
        }
        $list = array_unique($list);
        foreach ($list as $mod) {
            $p = PATH_MODS . DIRECTORY_SEPARATOR . ucfirst($mod) . DIRECTORY_SEPARATOR;
            $p .= $client ? 'html_client' : 'html_admin';
            if (file_exists($p)) {
                $paths[] = $p;
            }
        }

        $config['code'] = $theme;
        $config['paths'] = $paths;
        $config['hasSettings'] = false;

        if (is_dir($theme_path . '/config')) {
            $config['hasSettings'] = true;
        }

        return $config;
    }

    public function getCurrentRouteTheme(): string
    {
        if (defined('ADMIN_AREA') && ADMIN_AREA == true) {
            return $this->getCurrentAdminAreaTheme()['code'];
        }

        return $this->getCurrentClientAreaTheme()->getName();
    }

    public function getEncoreInfo(): array
    {
        $encoreInfo = [];
        $entrypoint = 'entrypoints';
        $manifest = 'manifest';
        $encoreInfo['is_encore_theme'] = true;

        if (!file_exists($this->getEncoreJsonPath($entrypoint)) && !file_exists($this->getEncoreJsonPath($manifest))) {
            $encoreInfo['is_encore_theme'] = false;
        }

        $encoreInfo[$entrypoint] = $this->getEncoreJsonPath($entrypoint);
        $encoreInfo[$manifest] = $this->getEncoreJsonPath($manifest);

        if ($this->useAdminDefaultEncore()) {
            $encoreInfo['is_encore_theme'] = true;
            $encoreInfo[$entrypoint] = $this->getThemesPath() . 'admin_default' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . "{$entrypoint}.json";
            $encoreInfo[$manifest] = $this->getThemesPath() . 'admin_default' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . "{$manifest}.json";
        }

        return $encoreInfo;
    }

    public function getDefaultMarkdownAttributes(): array
    {
        if (defined('ADMIN_AREA') && ADMIN_AREA == true) {
            $config = $this->getThemeConfig(false);
        } else {
            $config = $this->getThemeConfig(true);
        }

        if (isset($config['markdown_attributes']) && is_array($config['markdown_attributes'])) {
            $attributes = $config['markdown_attributes'];
            foreach ($attributes as $class => $defaults) {
                if (!class_exists($class)) {
                    unset($attributes[$class]);
                }
            }

            return $attributes;
        } else {
            return [];
        }
    }

    protected function getEncoreJsonPath($filename): string
    {
        return $this->getThemesPath() . $this->getCurrentRouteTheme() . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . "{$filename}.json";
    }

    protected function useAdminDefaultEncore()
    {
        $config = $this->getThemeConfig();

        return $config['use_admin_default_encore'] ?? false;
    }
}
