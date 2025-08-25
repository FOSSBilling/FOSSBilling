<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @author Adam Daley <contact@adly.dev>
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use DebugBar\Bridge\NamespacedTwigProfileCollector;
use DebugBar\StandardDebugBar;
use FOSSBilling\Enums\AppContext;
use FOSSBilling\TwigExtensions\DebugBar;
use Pimple\Container;
use Symfony\Component\HttpFoundation\Request;
use Twig\Extension\ProfilerExtension;
use Twig\Profiler\Profile;

class App
{
    public readonly AppContext $appContext;
    protected string $module = 'index';

    protected array $mappings = [];
    protected array $shared = [];
    protected \ArrayObject $options;

    protected string $ext = 'html.twig';

    /**
     * Constructor for the App class.
     *
     * @param Container             $di       the DI container
     * @param Request               $request  the Symfony Request object
     * @param StandardDebugBar|null $debugBar the debug bar instance
     */
    public function __construct(private Container $di, public readonly Request $request, private ?StandardDebugBar $debugBar)
    {
        // Determine application context based on request path.
        $requestPath = $this->request->getPathInfo() ?: '/';
        if (str_starts_with($requestPath, ADMIN_PREFIX)) {
            $this->appContext = AppContext::ADMIN;
        } elseif (str_starts_with($requestPath, API_PREFIX)) {
            $this->appContext = AppContext::API;
        } else {
            $this->appContext = AppContext::CLIENT;
        }

        // Define application context constant if not already defined.
        if (!defined('APP_CONTEXT')) {
            define('APP_CONTEXT', $this->appContext);
        }

        // Set the debug bar instance, or create a new one if not provided.
        $this->debugBar = (!$debugBar) ? new StandardDebugBar() : $debugBar;

        // TODO: Replace ASAP with a more robust solution rewriting path - workaround for custom pages stored in database.
        if (str_starts_with($requestPath, '/page')) {
            $requestPath = $this->getContextRelativePath();
            $requestPath = substr_replace($requestPath, '/custompages', 0, 5);
        }
    }

    /**
     * Get the request path relative to the application context (ADMIN, API, CLIENT).
     *
     * @return string the context-relative request path
     */
    public function getContextRelativePath(): string
    {
        $requestPath = $this->request->getPathInfo() ?: '/';

        if ($this->appContext === AppContext::ADMIN) {
            $contextRelativePath = substr($requestPath, strlen(ADMIN_PREFIX));
        } elseif ($this->appContext === AppContext::API) {
            $contextRelativePath = substr($requestPath, strlen(API_PREFIX));
        } else {
            $contextRelativePath = $requestPath;
        }

        return $contextRelativePath;
    }

    // PRIVATE FUNCTIONS

    /**
     * Set module name based on the request path.
     *
     * This method normalizes the request path and sets the module name
     * (lower case) based on the first segment of the path.
     */
    private function setModule(): void
    {
        $requestPath = trim($this->getContextRelativePath(), '/');

        if ($requestPath === '') {
            $module = 'index';
        } else {
            $pathSegments = explode('/', $requestPath, 2);
            $module = $pathSegments[0];
        }

        // Normalize and sanitize module name (only allow a-z).
        $module = strtolower($module);
        $module = preg_replace('/[^a-z]/', '', $module);

        // Ensure we always have a valid module.
        $this->module = $module !== '' ? $module : 'index';
    }

    /**
     * Get the debug bar instance.
     *
     * @return StandardDebugBar The debug bar instance
     */
    private function getDebugBar(): StandardDebugBar
    {
        return $this->debugBar;
    }

    private function init(): void
    {
        if ($this->appContext === AppContext::ADMIN) {
            // Register admin module routes.
            $module = $this->di['mod']($this->module);
            $module->registerAdminRoutes($this);
        } elseif ($this->appContext === AppContext::API) {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');

            $api = new Api();
            $api->setDi($this->di);
            $api->registerApiRoutes($this);
        } else {
            // Register client module routes.
            $module = $this->di['mod']($this->module);
            $module->registerClientRoutes($this);

            // Register redirect module routes if the extension is active.
            $extensionService = $this->di['mod_service']('extension');
            if ($extensionService->isExtensionActive('mod', 'redirect')) {
                $module = $this->di['mod']('redirect');
                $module->registerClientRoutes($this);
            }

            // Initialize default routes for the index module.
            $this->get('', 'get_index');
            $this->get('/', 'get_index');

            // Initialize custom pages with a regex to match any valid page name.
            $this->get('/:page', 'get_custom_page', ['page' => '[a-z0-9-/.//]+']);
            $this->post('/:page', 'get_custom_page', ['page' => '[a-z0-9-/.//]+']);
        }
    }

    private function checkPermission(): void
    {
        if ($this->appContext === AppContext::ADMIN) {
            $service = $this->di['mod_service']('staff');

            if ($this->module !== 'extension' && $this->di['auth']->isAdminLoggedIn() && !$service->hasPermission(null, $this->module)) {
                http_response_code(403);
                $e = new InformationException('You do not have permission to access the :mod: module', [':mod:' => $this->module], 403);
                echo $this->render('error', ['exception' => $e]);
                exit;
            }
        }
    }

    public function show404(\Exception $e): string
    {
        $this->di['logger']->setChannel('routing')->info($e->getMessage());
        http_response_code(404);

        return $this->render('error', ['exception' => $e]);
    }

    public function get(string $url, string $methodName, ?array $conditions = [], ?string $class = null): void
    {
        $this->event('get', $url, $methodName, $conditions, $class);
    }

    public function post(string $url, string $methodName, ?array $conditions = [], ?string $class = null): void
    {
        $this->event('post', $url, $methodName, $conditions, $class);
    }

    public function put(string $url, string $methodName, ?array $conditions = [], ?string $class = null): void
    {
        $this->event('put', $url, $methodName, $conditions, $class);
    }

    public function delete(string $url, string $methodName, ?array $conditions = [], ?string $class = null): void
    {
        $this->event('delete', $url, $methodName, $conditions, $class);
    }

    public function run(): string
    {
        $this->debugBar['time']->startMeasure('detectModule', 'Detecting module...');
        $this->setModule();
        $this->debugBar['time']->stopMeasure('detectModule');

        $this->debugBar['time']->startMeasure('init', 'Initializing the app');
        $this->init();
        $this->debugBar['time']->stopMeasure('init');

        $this->debugBar['time']->startMeasure('checkPermission', 'Checking access to module');
        $this->checkPermission();
        $this->debugBar['time']->stopMeasure('checkPermission');

        if (Config::getProperty('maintenance_mode.enabled', false)) {
            return $this->handleMaintenanceMode();
        }

        return $this->handleNormalRequest();
    }

    /**
     * @param string $path
     */
    public function redirect($path): never
    {
        if ($this->appContext === AppContext::ADMIN) {
            $location = $this->di['url']->adminLink($path);
        } else {
            $location = $this->di['url']->link($path);
        }
        header("Location: $location");
        exit;
    }

    public function render($fileName, $variableArray = [], $ext = null): string
    {
        $ext ??= 'html.twig';

        if ($this->appContext === AppContext::CLIENT) {
            try {
                $template = $this->getTwig()->load($fileName . '.' . $ext);
            } catch (\Twig\Error\LoaderError $e) {
                $this->di['logger']->setChannel('routing')->info($e->getMessage());
                http_response_code(404);

                throw new InformationException('Page not found', null, 404);
            }

            if ($fileName . '.' . $ext == 'mod_page_sitemap.xml') {
                header('Content-Type: application/xml');
            }
        } else {
            $template = $this->getTwig()->load($fileName . '.' . $ext);
        }

        return $template->render($variableArray);
    }

    public function sendFile($filename, $contentType, $path): false|int
    {
        header("Content-type: $contentType");
        header("Content-Disposition: attachment; filename=$filename");

        return readfile($path);
    }

    public function sendDownload($filename, $path): false|int
    {
        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream');
        header('Content-Type: application/download');
        header('Content-Description: File Transfer');
        header("Content-Disposition: attachment; filename=$filename" . ';');
        header('Content-Transfer-Encoding: binary');

        return readfile($path);
    }

    protected function executeShared($classname, $methodName, $params): string
    {
        $this->debugBar['time']->startMeasure('executeShared', 'Reflecting module controller (shared mapping)');
        $class = new $classname();
        if ($class instanceof InjectionAwareInterface) {
            $class->setDi($this->di);
        }
        $reflection = new \ReflectionMethod($class::class, $methodName);
        $args = [];
        $args[] = $this; // first param always app instance

        foreach ($reflection->getParameters() as $param) {
            if (isset($params[$param->name])) {
                $args[$param->name] = $params[$param->name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[$param->name] = $param->getDefaultValue();
            }
        }
        $this->debugBar['time']->stopMeasure('executeShared');

        return $reflection->invokeArgs($class, $args);
    }

    protected function execute($methodName, $params, $classname = null): string
    {
        $this->debugBar['time']->startMeasure('execute', 'Reflecting module controller');

        $reflection = new \ReflectionMethod(static::class, $methodName);
        $args = [];

        foreach ($reflection->getParameters() as $param) {
            if (isset($params[$param->name])) {
                $args[$param->name] = $params[$param->name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[$param->name] = $param->getDefaultValue();
            }
        }

        $this->debugBar['time']->stopMeasure('execute');

        return $reflection->invokeArgs($this, $args);
    }

    protected function event(string $httpMethod, string $url, string $methodName, ?array $conditions = [], ?string $classname = null): void
    {
        if (method_exists($this, $methodName)) {
            $this->mappings[] = [$httpMethod, $url, $methodName, $conditions];
        }
        if ($classname !== null) {
            $this->shared[] = [$httpMethod, $url, $methodName, $conditions, $classname];
        }
    }

    protected function checkAllowedURLs(): bool
    {
        $REQUEST_URI = $_SERVER['REQUEST_URI'] ?? null;

        $allowedURLs = Config::getProperty('maintenance_mode.allowed_urls', []);

        // Allow access to the staff panel all the time
        $adminApiPrefixes = [
            '/api/guest/staff/login',
            '/api/admin',
            'api/admin',
            '/index.php?_url=/api/admin/',
        ];

        foreach ($adminApiPrefixes as $adminApiPrefix) {
            $realAdminApiUrl = SYSTEM_URL[-1] === '/' ? substr(SYSTEM_URL, 0, -1) . $adminApiPrefix : SYSTEM_URL . $adminApiPrefix;
            $allowedURLs[] = parse_url($realAdminApiUrl)['path'];
        }
        foreach ($allowedURLs as $url) {
            if (preg_match('/^' . str_replace('/', '\/', $url) . '(.*)/', $REQUEST_URI) !== 0) {
                return false;
            }
        }

        return true;
    }

    protected function isRequestAllowedByUrls(): bool
    {
        $requestUri = $this->di['request']->getRequestUri();
        $allowedURLs = Config::getProperty('maintenance_mode.allowed_urls', []);
        $adminApiPrefixes = [
            '/api/guest/staff/login',
            '/api/admin',
            'api/admin',
            '/index.php?_url=/api/admin/',
        ];
        foreach ($adminApiPrefixes as $adminApiPrefix) {
            $realAdminApiUrl = SYSTEM_URL[-1] === '/' ? substr(SYSTEM_URL, 0, -1) . $adminApiPrefix : SYSTEM_URL . $adminApiPrefix;
            $allowedURLs[] = parse_url($realAdminApiUrl)['path'];
        }
        foreach ($allowedURLs as $url) {
            if (preg_match('/^' . str_replace('/', '\/', $url) . '(.*)/', $requestUri) !== 0) {
                return true;
            }
        }

        return false;
    }

    private function isRequestAllowedByIp(): bool
    {
        $visitorIP = $this->request->getClientIp();
        $allowedIPs = Config::getProperty('maintenance_mode.allowed_ips', []);
        foreach ($allowedIPs as $network) {
            if (!str_contains($network, '/')) {
                $network .= '/32';
            }
            [$network, $netmask] = explode('/', $network, 2);
            $network_decimal = ip2long($network);
            $ip_decimal = ip2long($visitorIP);
            $wildcard_decimal = 2 ** (32 - (int) $netmask) - 1;
            $netmask_decimal = ~$wildcard_decimal;
            if (($ip_decimal & $netmask_decimal) == ($network_decimal & $netmask_decimal)) {
                return true;
            }
        }

        return false;
    }

    private function handleMaintenanceMode(): string
    {
        $adminPrefix = Config::getProperty('maintenance_mode.admin_prefix', ADMIN_PREFIX);

        if ($this->appContext == AppContext::ADMIN || $this->isRequestAllowedByUrls() || $this->isRequestAllowedByIp()) {
            return $this->handleNormalRequest();
        }
        header('HTTP/1.0 503 Service Unavailable');
        if ($this->appContext == AppContext::API) {
            $exc = new InformationException('The system is undergoing maintenance. Please try again later', [], 503);
            $api = new Api();
            $api->setDi($this->di);
            $api->renderJson(null, $exc);

            return '';
        }

        return $this->render('mod_system_maintenance');
    }

    private function handleNormalRequest(): string
    {
        $requestPath = $this->getContextRelativePath();

        $this->debugBar['time']->startMeasure('sharedMapping', 'Checking shared mappings');

        $sharedCount = count($this->shared);
        for ($i = 0; $i < $sharedCount; ++$i) {
            $mapping = $this->shared[$i];
            $url = new \Box_UrlHelper($mapping[0], $mapping[1], $mapping[3], $requestPath);
            if ($url->match) {
                $this->debugBar['time']->stopMeasure('sharedMapping');

                return $this->executeShared($mapping[4], $mapping[2], $url->params);
            }
        }
        $this->debugBar['time']->stopMeasure('sharedMapping');

        // this class mappings
        $this->debugBar['time']->startMeasure('mapping', 'Checking mappings');
        $mappingsCount = count($this->mappings);
        for ($i = 0; $i < $mappingsCount; ++$i) {
            $mapping = $this->mappings[$i];
            $url = new \Box_UrlHelper($mapping[0], $mapping[1], $mapping[3], $requestPath);
            if ($url->match) {
                $this->debugBar['time']->stopMeasure('mapping');

                return $this->execute($mapping[2], $url->params);
            }
        }
        $this->debugBar['time']->stopMeasure('mapping');

        $e = new InformationException('Page :url not found', [':url' => $requestPath], 404);

        return $this->show404($e);
    }

    // Client-specific methods (only available when appContext is AppContext::CLIENT)
    public function get_index(): string
    {
        return $this->render('mod_index_dashboard');
    }

    public function get_custom_page($page): string
    {
        $ext = $this->ext;
        if (str_contains($page, '.')) {
            $ext = substr($page, strpos($page, '.') + 1);
            $page = substr($page, 0, strpos($page, '.'));
        }
        $page = str_replace('/', '_', $page);
        $tpl = 'mod_page_' . $page;

        try {
            return $this->render($tpl, ['post' => $_POST], $ext);
        } catch (\Exception $e) {
            if (DEBUG) {
                error_log($e->getMessage());
            }
        }
        $requestPath = $this->getContextRelativePath();
        $e = new InformationException('Page :url not found', [':url' => $requestPath], 404);

        $this->di['logger']->setChannel('routing')->info($e->getMessage());
        http_response_code(404);

        return $this->render('error', ['exception' => $e]);
    }

    protected function getTwig(): \Twig\Environment
    {
        $twig = $this->di['twig'];

        if ($this->appContext === AppContext::ADMIN) {
            $this->configureAdminTwig($twig);
        } else {
            $this->configureClientTwig($twig);
        }

        $this->addDebugBarExtensions($twig);

        return $twig;
    }

    private function configureAdminTwig(\Twig\Environment $twig): void
    {
        $service = $this->di['mod_service']('theme');
        $theme = $service->getCurrentAdminAreaTheme();

        $loader = new \Box_TwigLoader(
            [
                'mods' => PATH_MODS,
                'theme' => PATH_THEMES . DIRECTORY_SEPARATOR . $theme['code'],
                'type' => 'admin',
            ]
        );

        $twig->setLoader($loader);
        $twig->addGlobal('theme', $theme);

        if ($this->di['auth']->isAdminLoggedIn()) {
            $twig->addGlobal('admin', $this->di['api_admin']);
        }
    }

    private function configureClientTwig(\Twig\Environment $twig): void
    {
        $service = $this->di['mod_service']('theme');
        $name = $service->getCurrentClientAreaTheme()->getName();
        $theme = $service->getTheme($name);
        $settings = $service->getThemeSettings($theme);

        $loader = new \Box_TwigLoader(
            [
                'mods' => PATH_MODS,
                'theme' => PATH_THEMES . DIRECTORY_SEPARATOR . $name,
                'type' => 'client',
            ]
        );

        $twig->setLoader($loader);
        $twig->addGlobal('current_theme', $name);
        $twig->addGlobal('settings', $settings);

        if ($this->di['auth']->isClientLoggedIn()) {
            $twig->addGlobal('client', $this->di['api_client']);
        }

        if ($this->di['auth']->isAdminLoggedIn()) {
            $twig->addGlobal('admin', $this->di['api_admin']);
        }
    }

    private function addDebugBarExtensions(\Twig\Environment $twig): void
    {
        if (Environment::isDevelopment()) {
            $profile = new Profile();
            $twig->addExtension(new ProfilerExtension($profile));
            $collector = new NamespacedTwigProfileCollector($profile);
            if (!$this->debugBar->hasCollector($collector->getName())) {
                $this->debugBar->addCollector($collector);
            }
        }

        $twig->addExtension(new DebugBar($this->getDebugBar()));
    }
}
