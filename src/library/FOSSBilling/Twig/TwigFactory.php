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

use Box\Mod\Client\Entity\Client;
use Box\Mod\Currency\Entity\Currency;
use Box\Mod\Staff\Entity\Admin;
use DebugBar\Bridge\Twig\NamespacedTwigProfileCollector;
use DebugBar\StandardDebugBar;
use FOSSBilling\Config;
use FOSSBilling\Http\CookieNames;
use FOSSBilling\Http\RequestFactory;
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
use Symfony\Component\Intl\Currencies;
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
use Twig\Markup;
use Twig\Profiler\Profile;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\RuntimeLoader\RuntimeLoaderInterface;
use Twig\Sandbox\SecurityPolicyInterface;

class TwigFactory
{
    private readonly array $baseConfig;
    private ?Environment $adapterEnvironment = null;
    private ?Environment $themeSettingsEnvironment = null;

    public function __construct(private \Pimple\Container $di)
    {
        $this->baseConfig = Config::getProperty('twig', []);
    }

    /**
     * Timezone the current request should be rendered in. Falls back to the
     * `i18n.timezone` config (and ultimately UTC) when the auth service is
     * unavailable or no user is logged in, so this is safe to call during
     * Twig environment construction.
     */
    private function resolveTimezoneForActiveUser(): string
    {
        $clientTimezone = null;
        $adminTimezone = null;

        $auth = $this->di['auth'] ?? null;
        if ($auth instanceof \Box_Authorization) {
            if ($auth->isClientLoggedIn()) {
                $client = $this->di['em']->getRepository(Client::class)->find($this->di['session']->get('client_id'));
                $clientTimezone = $client?->getTimezone() ?? null;
            } elseif ($auth->isAdminLoggedIn()) {
                $admin = $this->di['session']->get('admin');
                if (is_array($admin) && !empty($admin['id'])) {
                    $adminModel = $this->di['em']->getRepository(Admin::class)->find($admin['id']);
                    $adminTimezone = $adminModel?->getTimezone() ?? null;
                }
            }
        }

        return i18n::getActiveTimezone($this->di['request'], $clientTimezone, $adminTimezone, $this->di['cookie_queue']);
    }

    /**
     * Create base Twig environment with extensions and configuration.
     */
    public function createBaseEnvironment(): Environment
    {
        // Get internationalisation settings from config, or use sensible defaults.
        $locale = i18n::getActiveLocale($this->di['request'], true, $this->di['cookie_queue']);
        $timezone = $this->resolveTimezoneForActiveUser();
        $dateFormat = strtoupper((string) Config::getProperty('i18n.date_format', 'MEDIUM'));
        $timeFormat = strtoupper((string) Config::getProperty('i18n.time_format', 'SHORT'));
        $dateTimePattern = Config::getProperty('i18n.datetime_pattern');

        // Create Twig environment with ArrayLoader (will be replaced in specific contexts).
        $loader = new ArrayLoader();
        $twig = new Environment($loader, $this->baseConfig);

        $decimalDigits = $this->getDefaultCurrencyFractionDigits();

        $twig->getExtension(CoreExtension::class)->setNumberFormat($decimalDigits, '.', '');
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
        $twig->addGlobal('admin', $this->di['auth']->isAdminLoggedIn() ? $this->di['api_admin'] : null);
        $twig->addGlobal('client', $this->di['auth']->isClientLoggedIn() ? $this->di['api_client'] : null);

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
        $twig->addGlobal('client', $this->di['auth']->isClientLoggedIn() ? $this->di['api_client'] : null);
        $twig->addGlobal('admin', $this->di['auth']->isAdminLoggedIn() ? $this->di['api_admin'] : null);

        $this->configureDebugging($twig, $debugBar);

        // Set CSRF cookie for browser-facing double-submit pattern.
        $this->configureCsrf();

        return $twig;
    }

    /**
     * Create sandboxed Twig environment for email template rendering.
     * Used for database-stored templates (email templates, mass mailer).
     *
     * The env is built per-call (not memoized) so each email can be rendered
     * with the recipient's timezone.
     *
     * @param string|null $timezone IANA timezone to use for date formatting;
     *                              pass the recipient's timezone so emails
     *                              render in their local time. Defaults to
     *                              the active user's timezone / config.
     */
    public function createEmailEnvironment(?string $timezone = null): Environment
    {
        // Get internationalisation settings from config
        $locale = i18n::getActiveLocale($this->di['request'], true, $this->di['cookie_queue']);
        $timezone ??= $this->resolveTimezoneForActiveUser();
        $dateFormat = strtoupper((string) Config::getProperty('i18n.date_format', 'MEDIUM'));
        $timeFormat = strtoupper((string) Config::getProperty('i18n.time_format', 'SHORT'));
        $dateTimePattern = Config::getProperty('i18n.datetime_pattern');

        // Create Twig environment with ArrayLoader
        $loader = new ArrayLoader();
        $twig = new Environment($loader, $this->baseConfig);

        $decimalDigits = $this->getDefaultCurrencyFractionDigits();

        $twig->getExtension(CoreExtension::class)->setNumberFormat($decimalDigits, '.', '');
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
        $runtimeLoader = new readonly class($this->di) implements RuntimeLoaderInterface {
            public function __construct(private \Pimple\Container $di)
            {
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
            'system_email' => $this->getEmailSettingsForTemplates(),
        ]);
        $twig->addGlobal('default_currency', $this->getDefaultCurrencyCode());
        $twig->addGlobal('FOSSBillingVersion', Version::VERSION);

        return $twig;
    }

    /**
     * @return array{signature: Markup}
     */
    private function getEmailSettingsForTemplates(): array
    {
        $emailConfig = $this->di['mod']('email')->getConfig();
        $signature = trim((string) ($emailConfig['signature'] ?? ''));

        if ($signature === '') {
            $signature = (string) ($this->di['api_guest']->system_company()['signature'] ?? '');
        }

        return [
            'signature' => new Markup(Tools::sanitizeContent($signature), 'UTF-8'),
        ];
    }

    public function createAdapterEnvironment(): Environment
    {
        if ($this->adapterEnvironment !== null) {
            return $this->adapterEnvironment;
        }

        $twig = $this->createSandboxedFragmentEnvironment(AdapterPolicy::create());

        $apiGuest = $this->di['api_guest'];
        $twig->addGlobal('guest', [
            'system_company' => $apiGuest->system_company(),
        ]);

        $this->adapterEnvironment = $twig;

        return $twig;
    }

    public function createThemeSettingsEnvironment(): Environment
    {
        if ($this->themeSettingsEnvironment !== null) {
            return $this->themeSettingsEnvironment;
        }

        $twig = $this->createSandboxedFragmentEnvironment(AdapterPolicy::create());

        $this->themeSettingsEnvironment = $twig;

        return $twig;
    }

    private function createSandboxedFragmentEnvironment(SecurityPolicyInterface $policy): Environment
    {
        $locale = i18n::getActiveLocale($this->di['request'], true, $this->di['cookie_queue']);
        $timezone = Config::getProperty('i18n.timezone', 'UTC');
        $dateFormat = strtoupper((string) Config::getProperty('i18n.date_format', 'MEDIUM'));
        $timeFormat = strtoupper((string) Config::getProperty('i18n.time_format', 'SHORT'));
        $dateTimePattern = Config::getProperty('i18n.datetime_pattern');

        $twig = new Environment(new ArrayLoader(), $this->baseConfig);

        $decimalDigits = $this->getDefaultCurrencyFractionDigits();

        $twig->getExtension(CoreExtension::class)->setNumberFormat($decimalDigits, '.', '');
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
        $twig->addRuntimeLoader($this->createSandboxedFragmentRuntimeLoader());
        $twig->addExtension(new SandboxExtension($policy, true));
        $twig->addGlobal('FOSSBillingVersion', Version::VERSION);

        return $twig;
    }

    private function createSandboxedFragmentRuntimeLoader(): RuntimeLoaderInterface
    {
        return new readonly class($this->di) implements RuntimeLoaderInterface {
            public function __construct(private \Pimple\Container $di)
            {
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
    }

    private function configureRuntimeLoaders(Environment $twig): void
    {
        $runtimeLoader = new readonly class($this->di) implements RuntimeLoaderInterface {
            public function __construct(private \Pimple\Container $di)
            {
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
        if (!$this->di->offsetExists('request') || !$this->di['request'] instanceof \Symfony\Component\HttpFoundation\Request) {
            throw new \LogicException('TwigFactory requires a Symfony request in the container.');
        }

        $csrfToken = $this->getCsrfToken();
        $request = $this->di['request'];
        $requestData = $request->query->all();
        unset($requestData['_url']);

        $requestQuery = $requestData;
        $requestPath = \Box_Url::normalizeLinkPath(RequestFactory::getRoutePath($request));
        $requestHasFilters = count(array_diff_key($requestData, [
            'page' => true,
            'search' => true,
        ])) > 0;

        if ($request->isXmlHttpRequest()) {
            $requestData['ajax'] = true;
        }

        $session = $this->di['session'];
        $redirectUri = $session->get('redirect_uri');
        if (!empty($redirectUri)) {
            $twig->addGlobal('redirect_uri', $redirectUri);
        }

        $twig->addGlobal('default_currency', $this->getDefaultCurrencyCode());

        $twig->addGlobal('CSRFToken', $csrfToken);
        $twig->addGlobal('flashes', $session->get('flashes') ?? []);
        $twig->addGlobal('request', new RequestDataView($requestData));
        $twig->addGlobal('request_query', $requestQuery);
        $twig->addGlobal('request_path', $requestPath);
        $twig->addGlobal('request_has_filters', $requestHasFilters);
        $twig->addGlobal('guest', $this->di['api_guest']);
        $twig->addGlobal('FOSSBillingVersion', Version::VERSION);
    }

    private function getDefaultCurrencyCode(): ?string
    {
        if (!$this->di->offsetExists('em')) {
            return null;
        }

        $repository = $this->di['em']->getRepository(Currency::class);
        $currency = $repository->findDefault();

        return $currency instanceof Currency ? $currency->getCode() : null;
    }

    private function getDefaultCurrencyFractionDigits(): int
    {
        $code = $this->getDefaultCurrencyCode();

        if ($code !== null && Currencies::exists($code)) {
            return Currencies::getFractionDigits($code);
        }

        return 2;
    }

    /**
     * Set the CSRF cookie for the double-submit pattern.
     * Should only be called in browser-facing contexts (admin/client page loads).
     */
    public function configureCsrf(): void
    {
        $csrfToken = $this->getCsrfToken();
        $request = $this->di['request'];
        $secure = $request->isSecure();
        $this->di['cookie_queue']->queue(
            CookieNames::CSRF,
            $csrfToken,
            0,
            '/',
            null,
            $secure,
            false,
            'Strict',
        );

        if ($request->cookies->has(CookieNames::LEGACY_CSRF)) {
            $this->di['cookie_queue']->queue(
                CookieNames::LEGACY_CSRF,
                '',
                time() - 3600,
                '/',
                null,
                $secure,
                false,
                'Strict',
            );
        }
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
            DebugBarExtension::class => fn (): DebugBarExtension => new DebugBarExtension($debugBar),
        ]));
    }
}
