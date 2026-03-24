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

use Box\Mod\Extension\Entity\ExtensionMeta;
use Box\Mod\Extension\Repository\ExtensionMetaRepository;
use FOSSBilling\InjectionAwareInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private ?ExtensionMetaRepository $extensionMetaRepository = null;
    private readonly Filesystem $filesystem;

    /**
     * In-request cache for the current admin theme name.
     * This cache is used to store theme information during a single request.
     * It is cleared whenever theme settings are changed by calling clearThemeCache().
     */
    private static ?string $adminThemeCache = null;
    /**
     * In-request cache for the current client theme name.
     * This cache is used to avoid repeated lookups during a single request.
     * It is cleared whenever theme settings are changed by calling clearThemeCache().
     */
    private static ?string $clientThemeCache = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->extensionMetaRepository = isset($this->di['em'])
            ? $this->di['em']->getRepository(ExtensionMeta::class)
            : null;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * Clear the theme cache. Call this method when theme settings are updated.
     */
    public static function clearThemeCache(): void
    {
        self::$adminThemeCache = null;
        self::$clientThemeCache = null;
    }

    public function getExtensionMetaRepository(): ExtensionMetaRepository
    {
        if ($this->extensionMetaRepository === null) {
            if ($this->di === null) {
                throw new \FOSSBilling\Exception('The dependency injection container has not been set.');
            }

            $this->extensionMetaRepository = $this->di['em']->getRepository(ExtensionMeta::class);
        }

        return $this->extensionMetaRepository;
    }

    public function getTheme($name): Model\Theme
    {
        return new Model\Theme($name);
    }

    public function getCurrentThemePreset(Model\Theme $theme)
    {
        $current = $this->getExtensionMetaRepository()
            ->findOneByExtensionAndScope('mod_theme', $theme->getName(), 'preset', 'current')
            ?->getMetaValue();
        if (empty($current)) {
            $current = $theme->getCurrentPreset();
            $this->setCurrentThemePreset($theme, $current);
        }

        return $current;
    }

    public function setCurrentThemePreset(Model\Theme $theme, $preset): bool
    {
        $meta = $this->getExtensionMetaRepository()->findOneByExtensionAndScope('mod_theme', $theme->getName(), 'preset', 'current');

        if (!$meta instanceof ExtensionMeta) {
            $meta = (new ExtensionMeta())
                ->setExtension('mod_theme')
                ->setRelType('preset')
                ->setRelId('current')
                ->setMetaKey($theme->getName());

            $this->di['em']->persist($meta);
        }

        $meta->setMetaValue((string) $preset);
        $this->di['em']->flush();

        return true;
    }

    public function deletePreset(Model\Theme $theme, $preset): bool
    {
        $this->getExtensionMetaRepository()->deleteByExtensionAndScope('mod_theme', (string) $preset, 'settings', $theme->getName());
        $this->getExtensionMetaRepository()->deleteByExtensionAndScope('mod_theme', $theme->getName(), 'preset', 'current');

        return true;
    }

    public function getThemePresets(Model\Theme $theme)
    {
        $presets = [];
        $metaRows = $this->getExtensionMetaRepository()->findByExtensionAndScope('mod_theme', null, 'settings', $theme->getName(), ['metaKey' => 'ASC']);
        foreach ($metaRows as $meta) {
            $presets[$meta->getMetaKey()] = $meta->getMetaKey();
        }

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

        $meta = $this->getExtensionMetaRepository()->findOneByExtensionAndScope('mod_theme', (string) $preset, 'settings', $theme->getName());
        if ($meta instanceof ExtensionMeta) {
            return json_decode($meta->getMetaValue() ?? '', true) ?? [];
        }

        return $theme->getPresetFromSettingsDataFile($preset);
    }

    public function updateSettings(Model\Theme $theme, $preset, array $params): bool
    {
        $meta = $this->getExtensionMetaRepository()->findOneByExtensionAndScope('mod_theme', (string) $preset, 'settings', $theme->getName());

        if (!$meta instanceof ExtensionMeta) {
            $meta = (new ExtensionMeta())
                ->setExtension('mod_theme')
                ->setRelType('settings')
                ->setRelId($theme->getName())
                ->setMetaKey((string) $preset);

            $this->di['em']->persist($meta);
        }

        $meta->setMetaValue(json_encode($params));
        $this->di['em']->flush();

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
            $data = $systemService->renderTplString($vars['_tpl'], false, $vars);

            $this->filesystem->dumpFile($realFile, $data);
        }

        return true;
    }

    public function getCurrentAdminAreaTheme(): array
    {
        $default = 'admin_default';

        if (self::$adminThemeCache !== null) {
            // Apply default logic when returning from cache
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
        // Cache the raw database value (use empty string instead of null to mark as cached)
        self::$adminThemeCache = $theme ?? '';

        // Apply default logic for the return value
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
            // Apply default logic when returning from cache
            return !empty(self::$clientThemeCache) ? self::$clientThemeCache : 'huraga';
        }

        $theme = $this->di['db']->getCell("SELECT value FROM setting WHERE param = 'theme' ");
        // Cache the raw database value (use empty string instead of null to mark as cached)
        self::$clientThemeCache = $theme ?? '';

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

        // add installed modules paths
        $table = $this->di['mod_service']('extension');
        $list = $table->getCoreAndActiveModules();
        // add module folder to look for template
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
        // Runtime check for admin area - uses index.php defined constant
        $isAdmin = defined('ADMIN_AREA') && ADMIN_AREA;
        if ($isAdmin) {
            return $this->getCurrentAdminAreaTheme()['code'];
        }

        return $this->getCurrentClientAreaTheme()->getName();
    }

    public function getDefaultMarkdownAttributes(): array
    {
        // Runtime check for admin area - uses index.php defined constant
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
