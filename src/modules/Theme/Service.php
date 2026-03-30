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

    private static ?string $adminThemeCache = null;
    private static ?string $clientThemeCache = null;

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

    public static function clearThemeCache(): void
    {
        self::$adminThemeCache = null;
        self::$clientThemeCache = null;
    }

    public function getTheme($name): Model\Theme
    {
        return new Model\Theme($name);
    }

    public function getCurrentThemePreset(Model\Theme $theme)
    {
        $extensionService = $this->di['mod_service']('extension');
        $current = $extensionService->getMetaValue('mod_theme', $theme->getName(), 'preset', 'current');
        if (empty($current)) {
            $current = $theme->getCurrentPreset();
            $this->setCurrentThemePreset($theme, $current);
        }

        return $current;
    }

    public function setCurrentThemePreset(Model\Theme $theme, $preset): bool
    {
        $extensionService = $this->di['mod_service']('extension');
        $extensionService->setMeta('mod_theme', $theme->getName(), (string) $preset, 'preset', 'current');

        return true;
    }

    public function deletePreset(Model\Theme $theme, $preset): bool
    {
        $extensionService = $this->di['mod_service']('extension');
        $extensionService->deleteMeta('mod_theme', (string) $preset, 'settings', $theme->getName());
        $extensionService->deleteMeta('mod_theme', $theme->getName(), 'preset', 'current');

        return true;
    }

    public function getThemePresets(Model\Theme $theme): array
    {
        $presets = [];
        $extensionService = $this->di['mod_service']('extension');
        $metaRows = $extensionService->findMeta('mod_theme', null, 'settings', $theme->getName(), ['metaKey' => 'ASC']);
        foreach ($metaRows as $meta) {
            $presets[$meta->getMetaKey()] = $meta->getMetaKey();
        }

        if (empty($presets)) {
            $core_presets = $theme->getPresetsFromSettingsDataFile();
            $presets = [];
            foreach ($core_presets as $preset => $params) {
                $presets[$preset] = $preset;
                $this->updateSettings($theme, $preset, $params);
            }
        }

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

        $extensionService = $this->di['mod_service']('extension');
        $meta = $extensionService->getMeta('mod_theme', (string) $preset, 'settings', $theme->getName());
        if ($meta !== null) {
            return json_decode($meta->getMetaValue() ?? '', true) ?? [];
        }

        return $theme->getPresetFromSettingsDataFile($preset);
    }

    public function updateSettings(Model\Theme $theme, $preset, array $params): bool
    {
        $extensionService = $this->di['mod_service']('extension');
        $extensionService->setMeta('mod_theme', (string) $preset, json_encode($params), 'settings', $theme->getName());

        return true;
    }

    public function regenerateThemeSettingsDataFile(Model\Theme $theme): bool
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

    public function regenerateThemeCssAndJsFiles(Model\Theme $theme, $preset, $api_admin): bool
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

    public function getCurrentAdminAreaTheme(): array
    {
        $default = 'admin_default';

        if (self::$adminThemeCache !== null) {
            $theme = !empty(self::$adminThemeCache) && $this->filesystem->exists(Path::join(PATH_THEMES, self::$adminThemeCache))
                ? self::$adminThemeCache
                : $default;
            $url = SYSTEM_URL . "themes/{$theme}/";

            return ['code' => $theme, 'url' => $url];
        }

        $query = 'SELECT value
                FROM setting
                WHERE param = :param
               ';
        $theme = $this->di['db']->getCell($query, ['param' => 'admin_theme']);
        self::$adminThemeCache = $theme ?? '';

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
        if (self::$clientThemeCache !== null) {
            return !empty(self::$clientThemeCache) ? self::$clientThemeCache : 'huraga';
        }

        $theme = $this->di['db']->getCell("SELECT value FROM setting WHERE param = 'theme' ");
        self::$clientThemeCache = $theme ?? '';

        return !empty($theme) ? $theme : 'huraga';
    }

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
            $config = json_decode($this->filesystem->readFile($manifest), true);
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
            $ext = trim((string) $config['extends'], '/');
            $ext = str_replace('.', '', $ext);

            $config['url'] = SYSTEM_URL . "themes/{$ext}/";
            $paths[] = Path::join($this->getThemesPath(), $ext, 'html');
        } else {
            $config['url'] = SYSTEM_URL . "themes/{$theme}/";
        }

        $table = $this->di['mod_service']('extension');
        $list = $table->getCoreAndActiveModules();
        if (!is_null($mod)) {
            $list[] = $mod;
        }
        $list = array_unique($list);
        foreach ($list as $mod) {
            $p = Path::join(PATH_MODS, ucfirst((string) $mod), $client ? 'templates/client' : 'templates/admin');
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
        $isAdmin = defined('ADMIN_AREA') && ADMIN_AREA;
        if ($isAdmin) {
            return $this->getCurrentAdminAreaTheme()['code'];
        }

        return $this->getCurrentClientAreaTheme()->getName();
    }

    public function getDefaultMarkdownAttributes(): array
    {
        $isAdmin = defined('ADMIN_AREA') && ADMIN_AREA;
        if ($isAdmin) {
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
        }

        return [];
    }
}
