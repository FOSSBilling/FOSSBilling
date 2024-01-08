<?php
declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use DebugBar\Bridge\NamespacedTwigProfileCollector;
use DebugBar\StandardDebugBar;
use FOSSBilling\Enums\AppContext;
use FOSSBilling\TwigExtensions\DebugBar;
use Symfony\Component\HttpFoundation\Request;
use Twig\Profiler\Profile;
use Twig\Error\LoaderError;
use Twig\Extension\ProfilerExtension;

class App implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    protected AppContext $context;
    protected array $mappings = [];
    protected array $shared = [];
    protected Request $request;
    protected StandardDebugBar $debugBar;
    protected string $mod = 'index';
    protected string $url = '/';
    protected string $path;

    public $uri;

    public function __construct(null|StandardDebugBar $debugBar = null)
    {
        $this->request = Request::createFromGlobals();
        $this->path = (!$this->request->getPathInfo()) ? '/' : $this->request->getPathInfo();
        $this->context = $this->detectContext($this->path);
        $this->debugBar = (!$debugBar) ? new StandardDebugBar : $debugBar;
    }

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Detect the current application context from given path.
     *
     * @param string $path The path requested.
     *
     * @return AppContext The detected application context.
     */
    protected function detectContext(string $path): AppContext
    {
        $adminPrefix = ADMIN_PREFIX;
        $apiPrefix = '/api';
        $installPrefix = '/install';

        if (strncasecmp($path, $adminPrefix, strlen($adminPrefix)) === 0) {
            $this->context = AppContext::ADMIN;
        } elseif (strncasecmp($path, $apiPrefix, strlen($apiPrefix)) === 0) {
            $this->context = AppContext::API;
        } elseif (strncasecmp($path, $installPrefix, strlen($installPrefix)) === 0) {
            $this->context = AppContext::INSTALL;
        } else {
            $this->context = AppContext::CLIENT;
        }

        return $this->context;
    }















    /* OLD FUNCTIONS/TBD BELOW HERE */

    public function setUrl(string $url)
    {
        $this->url = $url;
    }

    protected function init()
    {
        $module = $this->di['mod']($this->mod);

        if ($this->context == AppContext::ADMIN) {
            $controller = $module->getAdminController();

            if (!is_null($controller)) {
                $controller->register($this);
            }
        } else {
            $module->registerClientRoutes($this);

            if ('api' == $this->mod) {
                define('API_MODE', true);

                // Prevent errors from being displayed in API mode as it can cause invalid JSON to be returned.
                ini_set('display_errors', '0');
                ini_set('display_startup_errors', '0');
            } else {
                $extensionService = $this->di['mod_service']('extension');
                if ($extensionService->isExtensionActive('mod', 'redirect')) {
                    $module = $this->di['mod']('redirect');
                    $module->registerClientRoutes($this);
                }

                // init index module manually
                $this->get('', 'get_index');
                $this->get('/', 'get_index');

                // init custom methods for undefined pages
                $this->get('/:page', 'get_custom_page', ['page' => '[a-z0-9-/.//]+']);
                $this->post('/:page', 'get_custom_page', ['page' => '[a-z0-9-/.//]+']);
            }
        }
    }

    protected function checkPermission()
    {
        if ($this->context == AppContext::ADMIN) {
            $service = $this->di['mod_service']('Staff');

            if ($this->mod !== 'extension' && $this->di['auth']->isAdminLoggedIn() && !$service->hasPermission(null, $this->mod)) {
                http_response_code(403);
                $e = new InformationException('You do not have permission to access the :mod: module', [':mod:' => $this->mod], 403);
                echo $this->render('error', ['exception' => $e]);
                exit;
            }
        }
    }

    protected function registerModule()
    {
        // bind module urls and process
        // determine module and bind urls
        $requestUri = $this->url;
        if (empty($requestUri)) {
            $requestUri = '/';
        }
        if ($requestUri == '/') {
            $mod = 'index';
        } else {
            $requestUri = trim($requestUri, '/');
            if (!str_contains($requestUri, '/')) {
                $mod = $requestUri;
            } else {
                [$mod] = explode('/', $requestUri);
            }
        }
        $mod = htmlspecialchars($mod);

        $this->mod = $mod;
        $this->uri = $requestUri;
    }

    public function show404(Exception $e)
    {
        $this->di['logger']->setChannel('routing')->info($e->getMessage());
        http_response_code(404);

        return $this->render('error', ['exception' => $e]);
    }

    /**
     * @param string $url
     * @param string $methodName
     * @param string $class
     */
    public function get($url, $methodName, $conditions = [], $class = null)
    {
        $this->event('get', $url, $methodName, $conditions, $class);
    }

    /**
     * @param string $url
     * @param string $methodName
     * @param string $class
     */
    public function post($url, $methodName, $conditions = [], $class = null)
    {
        $this->event('post', $url, $methodName, $conditions, $class);
    }

    public function put($url, $methodName, $conditions = [], $class = null)
    {
        $this->event('put', $url, $methodName, $conditions, $class);
    }

    public function delete($url, $methodName, $conditions = [], $class = null)
    {
        $this->event('delete', $url, $methodName, $conditions, $class);
    }

    public function run()
    {
        $this->debugBar['time']->startMeasure('registerModule', 'Registering module routes');
        $this->registerModule();
        $this->debugBar['time']->stopMeasure('registerModule');

        $this->debugBar['time']->startMeasure('init', 'Initializing the app');
        $this->init();
        $this->debugBar['time']->stopMeasure('init');

        $this->debugBar['time']->startMeasure('checkperm', 'Checking access to module');
        $this->checkPermission();
        $this->debugBar['time']->stopMeasure('checkperm');

        return $this->processRequest();
    }

    /**
     * @param string $path
     */
    public function redirect($path): never
    {
        if ($this->context == AppContext::ADMIN) {
            $location = $this->di['url']->adminLink($path);
        } else {
            $location = $this->di['url']->link($path);
        }

        header("Location: $location");
        exit;
    }

    /**
     * @param string $fileName
     */
    public function render(string $fileName, array $variableArray = [])
    {
        try {
            $template = $this->getTwig()->load($fileName . '.html.twig');
        } catch (LoaderError $e) {
            $this->di['logger']->setChannel('routing')->info($e->getMessage());
            http_response_code(404);
            throw new InformationException('Page not found', null, 404);
        }

        return $template->render($variableArray);
    }

    protected function executeShared($classname, $methodName, $params)
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

    protected function execute($methodName, $params, $classname = null)
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

        $response = $reflection->invokeArgs($this, $args);

        return $response;
    }

    /**
     * @param string $httpMethod
     */
    protected function event($httpMethod, $url, $methodName, $conditions = [], $classname = null)
    {
        if (method_exists($this, $methodName)) {
            $this->mappings[] = [$httpMethod, $url, $methodName, $conditions];
        }
        if ($classname !== null) {
            $this->shared[] = [$httpMethod, $url, $methodName, $conditions, $classname];
        }
    }

    /**
     * Check if the requested URL is in the allowlist.
     *
     * @since 4.22.0
     */
    protected function checkAllowedURLs()
    {
        $REQUEST_URI = $_SERVER['REQUEST_URI'] ?? null;

        $allowedURLs = $this->di['config']['maintenance_mode']['allowed_urls'];

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

    /**
     * Check if the visitor IP is in the allowlist.
     *
     * @since 4.22.0
     */
    protected function checkAllowedIPs()
    {
        $allowedIPs = $this->di['config']['maintenance_mode']['allowed_ips'];
        $visitorIP = $this->di['request']->getClientAddress();

        // Check if the visitor is in using of the allowed IPs/networks
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
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the requested URL is a part of the admin area.
     *
     * @since 4.22.0
     */
    protected function checkAdminPrefix()
    {
        $REQUEST_URI = $_SERVER['REQUEST_URI'] ?? null;

        $realAdminUrl = SYSTEM_URL[-1] === '/' ? substr(SYSTEM_URL, 0, -1) . ADMIN_PREFIX : SYSTEM_URL . ADMIN_PREFIX;
        $realAdminPath = parse_url($realAdminUrl)['path'];

        if (preg_match('/^' . str_replace('/', '\/', $realAdminPath) . '(.*)/', $REQUEST_URI) !== 0) {
            return false;
        }

        return true;
    }

    protected function processRequest()
    {
        /**
         * Block requests if the system is undergoing maintenance.
         * It will respect any URL/IP whitelisting under the configuration file.
         */
        $maintmode = $this->di['config']['maintenance_mode']['enabled'] ?? false;
        if ($maintmode) {
            // Check the allowlists
            if ($this->checkAdminPrefix() && $this->checkAllowedURLs() && $this->checkAllowedIPs()) {
                // Set response code to 503.
                header('HTTP/1.0 503 Service Unavailable');

                if ('api' == $this->mod) {
                    $exc = new InformationException('The system is undergoing maintenance. Please try again later', [], 503);
                    $apiController = new \Box\Mod\Api\Controller\Client;
                    $apiController->setDi($this->di);

                    return $apiController->renderJson(null, $exc);
                } else {
                    return $this->render('mod_system_maintenance');
                }
            }
        }

        $this->debugBar['time']->startMeasure('sharedMapping', 'Checking shared mappings');
        $sharedCount = count($this->shared);
        for ($i = 0; $i < $sharedCount; ++$i) {
            $mapping = $this->shared[$i];
            $url = new \Box_UrlHelper($mapping[0], $mapping[1], $mapping[3], $this->url);
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
            $url = new \Box_UrlHelper($mapping[0], $mapping[1], $mapping[3], $this->url);
            if ($url->match) {
                $this->debugBar['time']->stopMeasure('mapping');

                return $this->execute($mapping[2], $url->params);
            }
        }
        $this->debugBar['time']->stopMeasure('mapping');

        $e = new FOSSBilling\InformationException('Page :url not found', [':url' => $this->url], 404);

        return $this->show404($e);
    }

    protected function getTwig()
    {
        $service = $this->di['mod_service']('theme');
        $twig = $this->di['twig'];

        if ($this->context == AppContext::ADMIN) {
            $theme = $service->getCurrentAdminAreaTheme();
            $loader = new \Box_TwigLoader(
                [
                    'mods' => PATH_MODS,
                    'theme' => PATH_THEMES . DIRECTORY_SEPARATOR . $theme['code'],
                    'type' => 'admin',
                ]
            );

            $twig->addGlobal('theme', $theme);
        } else {
            $theme = $service->getCurrentClientAreaTheme();
            $settings = $service->getThemeSettings($theme);
            $loader = new \Box_TwigLoader(
                [
                    'mods' => PATH_MODS,
                    'theme' => PATH_THEMES . DIRECTORY_SEPARATOR . $theme->getName(),
                    'type' => 'client',
                ]
            );

            $twig->addGlobal('current_theme', $theme->getName());
            $twig->addGlobal('settings', $settings);
        }

        if (Environment::isDevelopment()) {
            $profile = new Profile();
            $twig->addExtension(new ProfilerExtension($profile));
            $collector = new NamespacedTwigProfileCollector($profile);
            if (!$this->debugBar->hasCollector($collector->getName())) {
                $this->debugBar->addCollector($collector);
            }
        }

        $twig->setLoader($loader);
        $twig->addExtension(new DebugBar($this->debugBar));

        if ($this->di['auth']->isClientLoggedIn()) {
            $twig->addGlobal('client', $this->di['api_client']);
        }

        if ($this->di['auth']->isAdminLoggedIn()) {
            $twig->addGlobal('admin', $this->di['api_admin']);
        }

        return $twig;
    }

    public function get_index()
    {
        return $this->render('mod_index_dashboard');
    }

    public function get_custom_page($page)
    {
        if (str_contains($page, '.')) {
            $page = substr($page, 0, strpos($page, '.'));
        }
        $page = str_replace('/', '_', $page);
        $tpl = 'mod_page_' . $page;
        try {
            return $this->render($tpl, ['post' => $_POST]);
        } catch (Exception $e) {
            if (DEBUG) {
                error_log($e->getMessage());
            }
        }
        $e = new InformationException('Page :url not found', [':url' => $this->url], 404);

        $this->di['logger']->setChannel('routing')->info($e->getMessage());
        http_response_code(404);

        return $this->render('error', ['exception' => $e]);
    }

    public function sendFile($filename, $contentType, $path)
    {
        header("Content-type: $contentType");
        header("Content-Disposition: attachment; filename=$filename");

        return readfile($path);
    }

    public function sendDownload($filename, $path)
    {
        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream');
        header('Content-Type: application/download');
        header('Content-Description: File Transfer');
        header("Content-Disposition: attachment; filename=$filename" . ';');
        header('Content-Transfer-Encoding: binary');

        return readfile($path);
    }
}
