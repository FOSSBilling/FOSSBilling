<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\StandardDebugBar;
use FOSSBilling\Config;
use FOSSBilling\Http\RequestFactory;
use FOSSBilling\Http\ResponseFactory;
use FOSSBilling\Http\RouteDefinition;
use FOSSBilling\Http\RouteMatcher;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Security\AuthenticationRequiredException;
use FOSSBilling\Security\EmailValidationRequiredException;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Box_App
{
    /** @var RouteDefinition[] */
    protected array $routeDefinitions = [];
    /** @var RouteDefinition[] */
    protected array $sharedRouteDefinitions = [];
    protected ArrayObject $options;
    protected ?Pimple\Container $di = null;
    protected string $ext = 'html.twig';
    protected string $mod = 'index';
    protected string $url = '/';
    protected StandardDebugBar $debugBar;
    protected Request $request;
    private ?ResponseFactory $responseFactory = null;
    private ?RouteMatcher $routeMatcher = null;

    public $uri;

    public function __construct(array|object $options = [], ?StandardDebugBar $debugBar = null)
    {
        $this->options = new ArrayObject($options);

        if (!$debugBar) {
            $this->debugBar = new StandardDebugBar();
        } else {
            $this->debugBar = $debugBar;
        }
    }

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
        $this->request = $di['request'];
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getDebugBar(): StandardDebugBar
    {
        return $this->debugBar;
    }

    protected function registerModule(): void
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

    protected function init(): void
    {
    }

    protected function checkPermission(): ?Response
    {
        return null;
    }

    public function show404(Exception $e): Response
    {
        $this->di['logger']->setChannel('routing')->info($e->getMessage());

        return $this->errorResponse($e, 404);
    }

    public function get(string $url, string $methodName, ?array $conditions = [], ?string $class = null): void
    {
        $this->registerRoute('get', $url, $methodName, $conditions, $class);
    }

    public function post(string $url, string $methodName, ?array $conditions = [], ?string $class = null): void
    {
        $this->registerRoute('post', $url, $methodName, $conditions, $class);
    }

    public function put(string $url, string $methodName, ?array $conditions = [], ?string $class = null): void
    {
        $this->registerRoute('put', $url, $methodName, $conditions, $class);
    }

    public function delete(string $url, string $methodName, ?array $conditions = [], ?string $class = null): void
    {
        $this->registerRoute('delete', $url, $methodName, $conditions, $class);
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    protected function getRequestPath(): string
    {
        return RequestFactory::getRoutePath($this->getRequest());
    }

    protected function responseFactory(): ResponseFactory
    {
        return $this->responseFactory ??= new ResponseFactory();
    }

    private function routeMatcher(): RouteMatcher
    {
        return $this->routeMatcher ??= new RouteMatcher();
    }

    protected function normalizeResponse(mixed $result): Response
    {
        return $this->responseFactory()->normalize($result);
    }

    public function renderResponse(string $fileName, array $variableArray = [], int $statusCode = 200, array $headers = []): Response
    {
        return $this->responseFactory()->html($this->render($fileName, $variableArray), $statusCode, $headers);
    }

    public function errorResponse(Exception $e, ?int $statusCode = null, array $headers = []): Response
    {
        return $this->responseFactory()->error($this->render('error', ['exception' => $e]), $e, $statusCode, $headers);
    }

    public function run(): Response
    {
        /** @var TimeDataCollector $timeCollector */
        $timeCollector = $this->debugBar->getCollector('time');

        try {
            $timeCollector->startMeasure('registerModule', 'Registering module routes');
            $this->registerModule();
            $timeCollector->stopMeasure('registerModule');

            $timeCollector->startMeasure('init', 'Initializing the app');
            $this->init();
            $timeCollector->stopMeasure('init');

            $timeCollector->startMeasure('checkperm', 'Checking access to module');
            $permissionResponse = $this->checkPermission();
            $timeCollector->stopMeasure('checkperm');
            if ($permissionResponse instanceof Response) {
                return $permissionResponse;
            }

            return $this->processRequest();
        } catch (AuthenticationRequiredException $e) {
            if ($e->getArea() === 'admin') {
                $this->di['set_return_uri'];

                return $this->responseFactory()->redirect($this->di['url']->adminLink('staff/login'));
            }

            $this->di['set_return_uri'];

            return $this->responseFactory()->redirect($this->di['url']->link('login'));
        } catch (EmailValidationRequiredException) {
            return $this->responseFactory()->redirect($this->di['url']->link('client/profile'));
        }
    }

    /**
     * @param string $path
     */
    public function redirect($path): RedirectResponse
    {
        return $this->responseFactory()->redirect($this->di['url']->link($path));
    }

    public function permanentRedirect($path): RedirectResponse
    {
        return $this->responseFactory()->redirect($this->di['url']->link($path), 301);
    }

    public function redirectUrl(string $url, int $statusCode = 302): RedirectResponse
    {
        return $this->responseFactory()->redirect($url, $statusCode);
    }

    public function render($fileName, $variableArray = []): string
    {
        return 'Rendering ' . $fileName;
    }

    private function invokeSharedController(string $classname, string $methodName, array $params): mixed
    {
        /** @var TimeDataCollector $timeCollector */
        $timeCollector = $this->debugBar->getCollector('time');

        $timeCollector->startMeasure('executeShared', 'Reflecting module controller (shared mapping)');
        $class = $this->createSharedController($classname);
        $reflection = new ReflectionMethod($class::class, $methodName);
        $args = $this->buildControllerArguments($reflection, $params, true);
        $timeCollector->stopMeasure('executeShared');

        return $reflection->invokeArgs($class, $args);
    }

    private function createSharedController(string $classname): object
    {
        $controller = new $classname();
        if ($controller instanceof InjectionAwareInterface) {
            $controller->setDi($this->di);
        }

        return $controller;
    }

    private function invokeLocalController(string $methodName, array $params): mixed
    {
        /** @var TimeDataCollector $timeCollector */
        $timeCollector = $this->debugBar->getCollector('time');

        $timeCollector->startMeasure('execute', 'Reflecting module controller');

        $reflection = new ReflectionMethod(static::class, $methodName);
        $args = $this->buildControllerArguments($reflection, $params);

        $timeCollector->stopMeasure('execute');

        return $reflection->invokeArgs($this, $args);
    }

    private function buildControllerArguments(ReflectionMethod $reflection, array $params, bool $includeApp = false): array
    {
        $args = $includeApp ? [$this] : [];

        foreach ($reflection->getParameters() as $param) {
            if (isset($params[$param->name])) {
                $args[$param->name] = $params[$param->name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[$param->name] = $param->getDefaultValue();
            }
        }

        return $args;
    }

    private function registerRoute(string $httpMethod, string $url, string $methodName, ?array $conditions = [], ?string $classname = null): void
    {
        if (method_exists($this, $methodName)) {
            $this->routeDefinitions[] = new RouteDefinition($httpMethod, $url, $methodName, $conditions);
        }
        if ($classname !== null) {
            $this->sharedRouteDefinitions[] = new RouteDefinition($httpMethod, $url, $methodName, $conditions, $classname);
        }
    }

    private function stopMeasureIfStarted(TimeDataCollector $timeCollector, string $measureName): void
    {
        if ($timeCollector->hasStartedMeasure($measureName)) {
            $timeCollector->stopMeasure($measureName);
        }
    }

    /**
     * @param RouteDefinition[] $routes
     */
    private function dispatchRouteDefinitions(array $routes, ?string $mappingMeasureName = null): ?Response
    {
        foreach ($routes as $route) {
            $routeMatch = $this->routeMatcher()->match($route->httpMethod, $route->path, $route->conditions, $this->url, $this->getRequest()->getMethod());
            if (!$routeMatch->matched) {
                continue;
            }

            /** @var TimeDataCollector $timeCollector */
            $timeCollector = $this->debugBar->getCollector('time');
            if ($mappingMeasureName !== null) {
                $this->stopMeasureIfStarted($timeCollector, $mappingMeasureName);
            }

            if ($route->controllerClass !== null) {
                return $this->normalizeResponse($this->invokeSharedController($route->controllerClass, $route->methodName, $routeMatch->params));
            }

            return $this->normalizeResponse($this->invokeLocalController($route->methodName, $routeMatch->params));
        }

        return null;
    }

    protected function checkAllowedURLs(): bool
    {
        $requestPath = $this->getRequestPath();
        $allowedURLs = Config::getProperty('maintenance_mode.allowed_urls', []);

        // Allow access to the staff panel all the time
        $adminApiPrefixes = [
            '/api/guest/staff/login',
            '/api/admin',
            'api/admin',
            '/index.php?_url=/api/admin/',
        ];

        foreach ($adminApiPrefixes as $adminApiPrefix) {
            $systemUrl = SYSTEM_URL;
            $realAdminApiUrl = str_ends_with($systemUrl, '/') ? substr($systemUrl, 0, -1) . $adminApiPrefix : $systemUrl . $adminApiPrefix;
            $allowedURLs[] = parse_url($realAdminApiUrl, PHP_URL_PATH);
        }
        foreach ($allowedURLs as $url) {
            if ($this->pathMatchesMaintenancePattern($requestPath, (string) $url)) {
                return false;
            }
        }

        return true;
    }

    protected function pathMatchesMaintenancePattern(string $requestPath, string $allowedPath): bool
    {
        $allowedPath = trim($allowedPath);
        if ($allowedPath === '') {
            return false;
        }

        if (!str_contains($allowedPath, '*')) {
            return $this->pathStartsWith($requestPath, $allowedPath);
        }

        $pattern = str_replace('\*', '.*', preg_quote($allowedPath, '/'));

        return preg_match('/^' . $pattern . '/', $requestPath) === 1;
    }

    private function pathStartsWith(string $requestPath, string $basePath): bool
    {
        return $requestPath === $basePath || str_starts_with($requestPath, rtrim($basePath, '/') . '/');
    }

    protected function checkAllowedIPs(): bool
    {
        $allowedIPs = Config::getProperty('maintenance_mode.allowed_ips', []);
        $visitorIP = $this->di['request']->getClientIp();

        if (is_string($visitorIP) && is_array($allowedIPs) && $this->ipMatchesMaintenanceAllowlist($visitorIP, $allowedIPs)) {
            return false;
        }

        return true;
    }

    protected function ipMatchesMaintenanceAllowlist(string $visitorIP, array $allowedIPs): bool
    {
        $allowedIPs = array_values(array_filter(array_map(
            static fn (mixed $ip): string => trim((string) $ip),
            $allowedIPs,
        )));

        try {
            return $allowedIPs !== [] && IpUtils::checkIp($visitorIP, $allowedIPs);
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    protected function checkAdminPrefix(): bool
    {
        $requestPath = $this->getRequestPath();
        $realAdminUrl = rtrim(SYSTEM_URL, '/') . ADMIN_PREFIX;
        $realAdminPath = parse_url($realAdminUrl, PHP_URL_PATH);

        if ($this->pathStartsWith($requestPath, $realAdminPath)) {
            return false;
        }

        return true;
    }

    protected function processRequest(): Response
    {
        /*
         * Block requests if the system is undergoing maintenance.
         * It will respect any URL/IP whitelisting under the configuration file.
         */
        if (Config::getProperty('maintenance_mode.enabled', false)) {
            // Check the allowlists
            if ($this->checkAdminPrefix() && $this->checkAllowedURLs() && $this->checkAllowedIPs()) {
                if ($this->mod == 'api') {
                    $exc = new FOSSBilling\InformationException('The system is undergoing maintenance. Please try again later', [], 503);
                    $apiController = new Box\Mod\Api\Controller\Client();
                    $apiController->setDi($this->di);

                    return $apiController->renderJson(null, $exc);
                }

                return $this->renderResponse('mod_system_maintenance', [], 503);
            }
        }

        /** @var TimeDataCollector $timeCollector */
        $timeCollector = $this->debugBar->getCollector('time');

        $timeCollector->startMeasure('sharedMapping', 'Checking shared mappings');
        $response = $this->dispatchRouteDefinitions($this->sharedRouteDefinitions, 'sharedMapping');
        if ($response instanceof Response) {
            return $response;
        }
        $this->stopMeasureIfStarted($timeCollector, 'sharedMapping');

        // this class mappings
        $timeCollector->startMeasure('mapping', 'Checking mappings');
        $response = $this->dispatchRouteDefinitions($this->routeDefinitions, 'mapping');
        if ($response instanceof Response) {
            return $response;
        }
        $this->stopMeasureIfStarted($timeCollector, 'mapping');

        $e = new FOSSBilling\InformationException('Page :url not found', [':url' => $this->url], 404);

        return $this->show404($e);
    }
}
