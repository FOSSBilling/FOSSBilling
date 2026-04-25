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

use DebugBar\Bridge\Twig\NamespacedTwigProfileCollector;
use DebugBar\StandardDebugBar;
use FOSSBilling\Config;
use FOSSBilling\i18n;
use FOSSBilling\Tools;
use FOSSBilling\Twig\Enum\AppArea;
use FOSSBilling\Twig\Extension\ApiExtension;
use FOSSBilling\Twig\Extension\DebugBarExtension;
use FOSSBilling\Twig\Extension\FOSSBillingExtension;
use FOSSBilling\Twig\Extension\LegacyExtension;
use FOSSBilling\Twig\Markdown\FOSSBillingMarkdown;
use FOSSBilling\Version;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Extension\CoreExtension;
use Twig\Extension\DebugExtension;
use Twig\Extension\ProfilerExtension;
use Twig\Extension\SandboxExtension;
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
    private ?Environment $emailEnvironment = null;
    private ?Environment $adapterEnvironment = null;

    public function __construct(\Pimple\Container $di)
    {
        $this->di = $di;
        $this->baseConfig = Config::getProperty('twig', []);
    }

    /**
     * Create base Twig environment with extensions and configuration.
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
        $twig->addExtension(new AttributeExtension(ApiExtension::class));
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
     * @param StandardDebugBar $debugBar debugbar instance
     */
    public function createAdminEnvironment(StandardDebugBar $debugBar): Environment
    {
        $twig = $this->createBaseEnvironment();

        $service = $this->di['mod_service']('theme');
        $theme = $service->getCurrentAdminAreaTheme();

        $loader = new TwigLoader(AppArea::ADMIN, Path::join(PATH_THEMES, $theme['code']));
        $twig->setLoader($loader);

        $twig->addGlobal('theme', $theme);
        $twig->addGlobal('current_theme', $theme['code']);
        $twig->addGlobal('app_area', AppArea::ADMIN->value);

        if ($this->di['auth']->isAdminLoggedIn()) {
            $twig->addGlobal('admin', $this->di['api_admin']);
        }

        $this->configureDebugging($twig, $debugBar);

        // Set CSRF cookie for browser-facing double-submit pattern.
        $this->configureCsrf();

        return $twig;
    }

    /**
     * Create client-specific Twig environment.
     *
     * @param StandardDebugBar $debugBar debugbar instance
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

        $twig->addGlobal('current_theme', $code);
        $twig->addGlobal('settings', $settings);
        $twig->addGlobal('app_area', AppArea::CLIENT->value);

        if ($this->di['auth']->isClientLoggedIn()) {
            $twig->addGlobal('client', $this->di['api_client']);
        }

        if ($this->di['auth']->isAdminLoggedIn()) {
            $twig->addGlobal('admin', $this->di['api_admin']);
        }

        $this->configureDebugging($twig, $debugBar);

        // Set CSRF cookie for browser-facing double-submit pattern.
        $this->configureCsrf();

        return $twig;
    }

    /**
     * Create sandboxed Twig environment for email template rendering.
     * Used for database-stored templates (email templates, mass mailer).
     */
    public function createEmailEnvironment(): Environment
    {
        if ($this->emailEnvironment !== null) {
            return $this->emailEnvironment;
        }

        // Get internationalisation settings from config
        $locale = i18n::getActiveLocale();
        $timezone = Config::getProperty('i18n.timezone', 'UTC');
        $dateFormat = strtoupper(Config::getProperty('i18n.date_format', 'MEDIUM'));
        $timeFormat = strtoupper(Config::getProperty('i18n.time_format', 'SHORT'));
        $dateTimePattern = Config::getProperty('i18n.datetime_pattern');

        // Create Twig environment with ArrayLoader
        $loader = new ArrayLoader();
        $twig = new Environment($loader, $this->baseConfig);

        // Configure core settings
        $twig->getExtension(CoreExtension::class)->setNumberFormat(2, '.', '');
        $twig->getExtension(CoreExtension::class)->setTimezone($timezone);

        // Add only essential extensions for email templates
        $twig->addExtension(new StringLoaderExtension());
        $twig->addExtension(new MarkdownExtension());

        // Intl extension for date/currency formatting
        $dateFormatter = new \IntlDateFormatter(
            $locale,
            constant("\IntlDateFormatter::$dateFormat"),
            constant("\IntlDateFormatter::$timeFormat"),
            $timezone,
            null,
            $dateTimePattern
        );
        $twig->addExtension(new IntlExtension($dateFormatter));

        // FOSSBilling extensions for email-specific filters
        $twig->addExtension(new AttributeExtension(FOSSBillingExtension::class));
        $twig->addExtension(new AttributeExtension(LegacyExtension::class));

        // Minimal runtime loader for email environment only
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

        // Add sandbox extension with policy, enabled globally
        $policy = EmailPolicy::create();
        $sandbox = new SandboxExtension($policy, true);
        $twig->addExtension($sandbox);

        // Add minimal globals needed for email templates
        // Convert guest to array to prevent object method access in sandbox
        $apiGuest = $this->di['api_guest'];
        $twig->addGlobal('guest', [
            'system_company' => $apiGuest->system_company(),
        ]);
        $twig->addGlobal('FOSSBillingVersion', Version::VERSION);

        $this->emailEnvironment = $twig;

        return $twig;
    }

    public function createAdapterEnvironment(): Environment
    {
        if ($this->adapterEnvironment !== null) {
            return $this->adapterEnvironment;
        }

        $locale = i18n::getActiveLocale();
        $timezone = Config::getProperty('i18n.timezone', 'UTC');
        $dateFormat = strtoupper(Config::getProperty('i18n.date_format', 'MEDIUM'));
        $timeFormat = strtoupper(Config::getProperty('i18n.time_format', 'SHORT'));
        $dateTimePattern = Config::getProperty('i18n.datetime_pattern');

        $loader = new ArrayLoader();
        $twig = new Environment($loader, $this->baseConfig);

        $twig->getExtension(CoreExtension::class)->setNumberFormat(2, '.', '');
        $twig->getExtension(CoreExtension::class)->setTimezone($timezone);

        $twig->addExtension(new StringLoaderExtension());

        $dateFormatter = new \IntlDateFormatter(
            $locale,
            constant("\IntlDateFormatter::$dateFormat"),
            constant("\IntlDateFormatter::$timeFormat"),
            $timezone,
            null,
            $dateTimePattern
        );
        $twig->addExtension(new IntlExtension($dateFormatter));

        $twig->addExtension(new AttributeExtension(FOSSBillingExtension::class));
        $twig->addExtension(new AttributeExtension(LegacyExtension::class));

        $runtimeLoader = new class($this->di) implements RuntimeLoaderInterface {
            private \Pimple\Container $di;

            public function __construct(\Pimple\Container $di)
            {
                $this->di = $di;
            }

            public function load($class)
            {
                return match ($class) {
                    FOSSBillingExtension::class => new FOSSBillingExtension($this->di),
                    LegacyExtension::class => new LegacyExtension($this->di),
                    default => null,
                };
            }
        };
        $twig->addRuntimeLoader($runtimeLoader);

        $policy = AdapterPolicy::create();
        $sandbox = new SandboxExtension($policy, true);
        $twig->addExtension($sandbox);

        $apiGuest = $this->di['api_guest'];
        $twig->addGlobal('guest', [
            'system_company' => $apiGuest->system_company(),
        ]);
        $twig->addGlobal('FOSSBillingVersion', Version::VERSION);

        $this->adapterEnvironment = $twig;

        return $twig;
    }

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
                    ApiExtension::class => new ApiExtension($this->di),
                    FOSSBillingExtension::class => new FOSSBillingExtension($this->di),
                    LegacyExtension::class => new LegacyExtension($this->di),
                    default => null,
                };
            }
        };

        $twig->addRuntimeLoader($runtimeLoader);
    }

    private function configureGlobals(Environment $twig): void
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            $_GET['ajax'] = true;
        }

        $csrfToken = $this->getCsrfToken();

        $session = $this->di['session'];
        $redirectUri = $session->get('redirect_uri');
        if (!empty($redirectUri)) {
            $twig->addGlobal('redirect_uri', $redirectUri);
        }

        $twig->addGlobal('CSRFToken', $csrfToken);
        $twig->addGlobal('request', $_GET);
        $twig->addGlobal('guest', $this->di['api_guest']);
        $twig->addGlobal('FOSSBillingVersion', Version::VERSION);
    }

    /**
     * Set the CSRF cookie for the double-submit pattern.
     * Should only be called in browser-facing contexts (admin/client page loads).
     */
    public function configureCsrf(): void
    {
        $csrfToken = $this->getCsrfToken();
        setcookie('csrf_token', $csrfToken, [
            'expires' => 0,
            'path' => '/',
            'samesite' => 'Strict',
            'secure' => Tools::isHTTPS(),
        ]);
    }

    private function getCsrfToken(): string
    {
        $session = $this->di['session'];
        $csrfToken = $session->get('csrf_token');
        if (empty($csrfToken)) {
            $csrfToken = bin2hex(random_bytes(32));
            $session->set('csrf_token', $csrfToken);
        }

        return $csrfToken;
    }

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
            DebugBarExtension::class => fn () => new DebugBarExtension($debugBar),
        ]));
    }
}
