<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Twig;

use DebugBar\Bridge\NamespacedTwigProfileCollector;
use DebugBar\StandardDebugBar;
use FOSSBilling\Config;
use FOSSBilling\i18n;
use FOSSBilling\Version;
use FOSSBilling\Twig\Enum\AppArea;
use FOSSBilling\Twig\Extension\DebugBarExtension;
use FOSSBilling\Twig\Extension\FOSSBillingExtension;
use FOSSBilling\Twig\Extension\LegacyExtension;
use FOSSBilling\Twig\Markdown\FOSSBillingMarkdown;
use Lcharette\WebpackEncoreTwig\EntrypointsTwigExtension;
use Lcharette\WebpackEncoreTwig\JsonManifest;
use Lcharette\WebpackEncoreTwig\TagRenderer;
use Lcharette\WebpackEncoreTwig\VersionedAssetsTwigExtension;
use Symfony\Component\Filesystem\Path;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookup;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Extension\CoreExtension;
use Twig\Extension\DebugExtension;
use Twig\Extension\ProfilerExtension;
use Twig\Extension\StringLoaderExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extra\Markdown\MarkdownExtension;
use Twig\Extra\Markdown\MarkdownRuntime;
use Twig\Loader\ArrayLoader;
use Twig\Profiler\Profile;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

class TwigFactory
{
    private \Pimple\Container $di;
    private array $baseConfig;

    /**
     * TwigFactory constructor.
     *
     * @param \Pimple\Container $di Dependency injection container.
     */
    public function __construct(\Pimple\Container $di)
    {
        $this->di = $di;
        $this->baseConfig = Config::getProperty('twig', []);
    }

    /**
     * Create base Twig environment with extensions and configuration.
     *
     * @return Environment
     */
    public function createBaseEnvironment(): Environment
    {
        // Get internationalisation settings from config, or use sensible defaults.
        $locale = i18n::getActiveLocale();
        $timezone = Config::getProperty('i18n.timezone', 'UTC');
        $dateFormat = strtoupper(Config::getProperty('i18n.date_format', 'MEDIUM'));
        $timeFormat = strtoupper(Config::getProperty('i18n.time_format', 'SHORT'));
        $dateTimePattern = Config::getProperty('i18n.datetime_pattern');

        // Create Twig environment with ArrayLoader (will be replaced in specific contexts).
        $loader = new ArrayLoader();
        $twig = new Environment($loader, $this->baseConfig);

        // Configure Webpack Encore integration.
        $this->configureWebpackEncore($twig);

        // Configure core and register bundled Twig extensions.
        $twig->getExtension(CoreExtension::class)->setNumberFormat(2, '.', '');
        $twig->getExtension(CoreExtension::class)->setTimezone($timezone);
        $twig->addExtension(new DebugExtension());
        $twig->addExtension(new MarkdownExtension());
        $twig->addExtension(new StringLoaderExtension());

        // Configure internationalization and register IntlExtension.
        $dateFormatter = new \IntlDateFormatter(
            $locale,
            constant("\IntlDateFormatter::$dateFormat"),
            constant("\IntlDateFormatter::$timeFormat"),
            $timezone,
            null,
            $dateTimePattern
        );
        $twig->addExtension(new IntlExtension($dateFormatter));

        // Register custom extensions.
        $twig->addExtension(new AttributeExtension(FOSSBillingExtension::class));
        $twig->addExtension(new AttributeExtension(LegacyExtension::class));

        // Configure and register runtime loaders.
        $this->configureRuntimeLoaders($twig);

        // Configure global Twig variables.
        $this->configureGlobals($twig);

        return $twig;
    }

    /**
     * Create admin-specific Twig environment.
     *
     * @param StandardDebugBar $debugBar Debugbar instance.
     *
     * @return Environment
     */
    public function createAdminEnvironment(StandardDebugBar $debugBar): Environment
    {
        $twig = $this->createBaseEnvironment();

        $service = $this->di['mod_service']('theme');
        $theme = $service->getCurrentAdminAreaTheme();

        $loader = new TwigLoader(AppArea::ADMIN, Path::join(PATH_THEMES, $theme['code']));
        $twig->setLoader($loader);

        // Add admin-specific globals.
        $twig->addGlobal('theme', $theme);
        $twig->addGlobal('app_area', AppArea::ADMIN->value);

        if ($this->di['auth']->isAdminLoggedIn()) {
            $twig->addGlobal('admin', $this->di['api_admin']);
        }

        // Configure Debugbar and profiling.
        $this->configureDebugging($twig, $debugBar);

        return $twig;
    }

    /**
     * Create client-specific Twig environment.
     *
     * @param StandardDebugBar $debugBar Debugbar instance.
     *
     * @return Environment
     */
    public function createClientEnvironment(StandardDebugBar $debugBar): Environment
    {
        $twig = $this->createBaseEnvironment();

        $service = $this->di['mod_service']('theme');
        $code = $service->getCurrentClientAreaThemeCode();
        $theme = $service->getTheme($code);
        $settings = $service->getThemeSettings($theme);

        $loader = new TwigLoader(AppArea::CLIENT, Path::join(PATH_THEMES, $code));
        $twig->setLoader($loader);

        // Add client-specific globals.
        $twig->addGlobal('current_theme', $code);
        $twig->addGlobal('settings', $settings);
        $twig->addGlobal('app_area', AppArea::CLIENT->value);

        if ($this->di['auth']->isClientLoggedIn()) {
            $twig->addGlobal('client', $this->di['api_client']);
        }

        if ($this->di['auth']->isAdminLoggedIn()) {
            $twig->addGlobal('admin', $this->di['api_admin']);
        }

        // Configure Debugbar and profiling.
        $this->configureDebugging($twig, $debugBar);

        return $twig;
    }

    /**
     * Configure Webpack Encore integration.
     *
     * @param Environment $twig Twig environment.
     */
    private function configureWebpackEncore(Environment $twig): void
    {
        if (isset($this->di['encore_info']) && $this->di['encore_info']['is_encore_theme']) {
            $entryPoints = new EntrypointLookup($this->di['encore_info']['entrypoints']);
            $tagRenderer = new TagRenderer($entryPoints);
            $encoreExtensions = new EntrypointsTwigExtension($entryPoints, $tagRenderer);
            $twig->addExtension($encoreExtensions);
            $twig->addExtension(new VersionedAssetsTwigExtension(new JsonManifest($this->di['encore_info']['manifest'])));
        }
    }

    /**
     * Configure and register runtime loaders.
     *
     * @param Environment $twig Twig environment.
     */
    private function configureRuntimeLoaders(Environment $twig): void
    {
        $runtimeLoader = new class($this->di) implements RuntimeLoaderInterface {
            private \Pimple\Container $di;

            public function __construct(\Pimple\Container $di)
            {
                $this->di = $di;
            }

            public function load($class)
            {
                return match ($class) {
                    MarkdownRuntime::class => new MarkdownRuntime(new FOSSBillingMarkdown($this->di)),
                    FOSSBillingExtension::class => new FOSSBillingExtension($this->di),
                    LegacyExtension::class => new LegacyExtension($this->di),
                    default => null,
                };
            }
        };

        $twig->addRuntimeLoader($runtimeLoader);
    }

    /**
     * Configure global Twig variables.
     *
     * @param Environment $twig Twig environment.
     */
    private function configureGlobals(Environment $twig): void
    {
        // Handle AJAX requests.
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            $_GET['ajax'] = true;
        }

        // CSRF token.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $token = hash('md5', $_COOKIE['PHPSESSID'] ?? '');
        } else {
            $token = hash('md5', session_id());
        }

        if (!empty($_SESSION['redirect_uri'])) {
            $twig->addGlobal('redirect_uri', $_SESSION['redirect_uri']);
        }

        $twig->addGlobal('CSRFToken', $token);
        $twig->addGlobal('request', $_GET);
        $twig->addGlobal('guest', $this->di['api_guest']);
        $twig->addGlobal('FOSSBillingVersion', Version::VERSION);
    }

    /**
     * Configure debugging and profiling.
     *
     * @param Environment       $twig       Twig environment.
     * @param StandardDebugBar  $debugBar   DebugBar instance.
     */
    private function configureDebugging(Environment $twig, StandardDebugBar $debugBar): void
    {
        if (\FOSSBilling\Environment::isDevelopment()) {
            $profile = new Profile();
            $twig->addExtension(new ProfilerExtension($profile));
            $collector = new NamespacedTwigProfileCollector($profile);
            if (!$debugBar->hasCollector($collector->getName())) {
                $debugBar->addCollector($collector);
            }
        }

        $twig->addExtension(new AttributeExtension(DebugBarExtension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            DebugBarExtension::class => fn() => new DebugBarExtension($debugBar),
        ]));
    }
}
