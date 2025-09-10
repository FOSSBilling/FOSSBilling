<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Theme;

use FOSSBilling\InjectionAwareInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private readonly Filesystem $filesystem;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function __construct()
    {
        $this->filesystem = new Filesystem();
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
            return json_decode($meta->meta_value ?? '', true);
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

        $this->filesystem->dumpFile($data_file, json_encode($settings));

        return true;
    }

    public function regenerateThemeCssAndJsFiles(Model\Theme $theme, $preset, $api_admin)
    {
        $assets = $theme->getPathAssets();

        $finder = new Finder();
        $finder->files()->in($assets)->name(['*.css.html.twig', '*.js.html.twig']);

        foreach ($finder as $file) {
            $settings = $this->getThemeSettings($theme, $preset);
            $realFile = Path::join($file->getPath(), Path::getFilenameWithoutExtension($file->getRelativePathname(), '.html.twig'));

            $vars = [];
            $vars['settings'] = $settings;
            $vars['_tpl'] = $file->getContents();
            $systemService = $this->di['mod_service']('system');
            $data = $systemService->renderString($vars['_tpl'], false, $vars);

            $this->filesystem->dumpFile($realFile, $data);
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
        if ($theme == null || !$this->filesystem->exists(Path::join(PATH_THEMES, $theme))) {
            $theme = $default;
        }
        $url = SYSTEM_URL . "themes/{$theme}/";

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

        $finder = new Finder();
        $finder->directories()->in($path)->depth('== 0')->ignoreDotFiles(true);
        foreach ($finder as $file) {
            try {
                if (!$client && str_contains($file->getFilename(), 'admin')) {
                    $list[] = $this->_loadTheme($file->getFilename());
                }

                if ($client && !str_contains($file->getFilename(), 'admin')) {
                    $list[] = $this->_loadTheme($file->getFilename());
                }
            } catch (\Exception $e) {
                error_log($e->getMessage());
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
        if (!$this->filesystem->exists(Path::join($path, $theme))) {
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
        return PATH_THEMES;
    }

    private function _loadTheme($theme, $client = true, $mod = null): array
    {
        $theme_path = Path::join($this->getThemesPath(), $theme);

        if (!$this->filesystem->exists($theme_path)) {
            throw new \FOSSBilling\Exception('Theme was not found in path :path', [':path' => $theme_path]);
        }
        $manifest = Path::join($theme_path, 'manifest.json');

        if ($this->filesystem->exists($manifest)) {
            $config = json_decode($this->filesystem->readFile($manifest) ?? '', true);
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

        $paths = [Path::join($theme_path, 'html')];

        if (isset($config['extends'])) {
            $ext = trim($config['extends'], '/');
            $ext = str_replace('.', '', $ext);

            $config['url'] = SYSTEM_URL . "themes/{$ext}/";
            $paths[] = Path::join($this->getThemesPath(), $ext, 'html');
        } else {
            $config['url'] = SYSTEM_URL . "themes/{$theme}/";
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
            $p = Path::join(PATH_MODS, ucfirst($mod), $client ? 'html_client' : 'html_admin');
            if ($this->filesystem->exists($p)) {
                $paths[] = $p;
            }
        }

        $config['code'] = $theme;
        $config['paths'] = $paths;
        $config['hasSettings'] = false;

        if ($this->filesystem->exists(Path::join($theme_path, 'config'))) {
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

        if (!$this->filesystem->exists($this->getEncoreJsonPath($entrypoint)) && !$this->filesystem->exists($this->getEncoreJsonPath($manifest))) {
            $encoreInfo['is_encore_theme'] = false;
        }

        $encoreInfo[$entrypoint] = $this->getEncoreJsonPath($entrypoint);
        $encoreInfo[$manifest] = $this->getEncoreJsonPath($manifest);

        if ($this->useAdminDefaultEncore()) {
            $encoreInfo['is_encore_theme'] = true;
            $encoreInfo[$entrypoint] = Path::join($this->getThemesPath(), 'admin_default', 'build', "{$entrypoint}.json");
            $encoreInfo[$manifest] = Path::join($this->getThemesPath(), 'admin_default', 'build', "{$manifest}.json");
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
        return Path::join($this->getThemesPath(), $this->getCurrentRouteTheme(), 'build', "{$filename}.json");
    }

    protected function useAdminDefaultEncore()
    {
        $config = $this->getThemeConfig();

        return $config['use_admin_default_encore'] ?? false;
    }
}
