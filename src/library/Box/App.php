<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\StandardDebugBar;
use FOSSBilling\Config;
use FOSSBilling\Http\HttpResponseException;
use FOSSBilling\Http\RequestFactory;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Security\AuthenticationRequiredException;
use FOSSBilling\Security\EmailValidationRequiredException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Box_App
{
    protected array $mappings = [];
    protected array $shared = [];
    protected ArrayObject $options;
    protected ?Pimple\Container $di = null;
    protected string $ext = 'html.twig';
    protected string $mod = 'index';
    protected string $url = '/';
    protected StandardDebugBar $debugBar;
    protected Request $request;

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

    protected function checkPermission(): void
    {
    }

    public function show404(Exception $e): Response
    {
        $this->di['logger']->setChannel('routing')->info($e->getMessage());

        return $this->errorResponse($e, 404);
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

    public function getRequest(): Request
    {
        return $this->request;
    }

    protected function getRequestPath(): string
    {
        return RequestFactory::getRoutePath($this->getRequest());
    }

    protected function normalizeResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        return new Response((string) ($result ?? ''));
    }

    public function renderResponse(string $fileName, array $variableArray = [], int $statusCode = 200, array $headers = []): Response
    {
        $response = new Response($this->render($fileName, $variableArray), $statusCode);
        $response->headers->add($headers);

        return $response;
    }

    public function errorResponse(Exception $e, ?int $statusCode = null, array $headers = []): Response
    {
        $statusCode ??= $e->getCode() > 0 ? $e->getCode() : 500;

        return $this->renderResponse('error', ['exception' => $e], $statusCode, $headers);
    }

    public function abortWithResponse(Response $response): never
    {
        throw new HttpResponseException($response);
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
            $this->checkPermission();
            $timeCollector->stopMeasure('checkperm');

            return $this->processRequest();
        } catch (AuthenticationRequiredException $e) {
            if ($e->getArea() === 'admin') {
                $this->di['set_return_uri'];

                return new RedirectResponse($this->di['url']->adminLink('staff/login'));
            }

            $this->di['set_return_uri'];

            return new RedirectResponse($this->di['url']->link('login'));
        } catch (EmailValidationRequiredException) {
            return new RedirectResponse($this->di['url']->link('client/profile'));
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        }
    }

    /**
     * @param string $path
     */
    public function redirect($path): never
    {
        $location = $this->di['url']->link($path);
        $this->abortWithResponse(new RedirectResponse($location));
    }

    public function permanentRedirect($path): never
    {
        $location = $this->di['url']->link($path);
        $this->abortWithResponse(new RedirectResponse($location, 301));
    }

    public function redirectUrl(string $url, int $statusCode = 302): never
    {
        $this->abortWithResponse(new RedirectResponse($url, $statusCode));
    }

    public function render($fileName, $variableArray = []): string
    {
        return 'Rendering ' . $fileName;
    }

    public function sendFile($filename, $contentType, $path): never
    {
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $contentType);
        $response->setContentDisposition('attachment', $filename);

        $this->abortWithResponse($response);
    }

    public function sendDownload($filename, $path): never
    {
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Description', 'File Transfer');
        $response->setContentDisposition('attachment', $filename);

        $this->abortWithResponse($response);
    }

    protected function executeShared($classname, $methodName, $params): mixed
    {
        /** @var TimeDataCollector $timeCollector */
        $timeCollector = $this->debugBar->getCollector('time');

        $timeCollector->startMeasure('executeShared', 'Reflecting module controller (shared mapping)');
        $class = new $classname();
        if ($class instanceof InjectionAwareInterface) {
            $class->setDi($this->di);
        }
        $reflection = new ReflectionMethod($class::class, $methodName);
        $args = [];
        $args[] = $this; // first param always app instance

        foreach ($reflection->getParameters() as $param) {
            if (isset($params[$param->name])) {
                $args[$param->name] = $params[$param->name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[$param->name] = $param->getDefaultValue();
            }
        }
        $timeCollector->stopMeasure('executeShared');

        return $reflection->invokeArgs($class, $args);
    }

    protected function execute($methodName, $params, $classname = null): mixed
    {
        /** @var TimeDataCollector $timeCollector */
        $timeCollector = $this->debugBar->getCollector('time');

        $timeCollector->startMeasure('execute', 'Reflecting module controller');

        $reflection = new ReflectionMethod(static::class, $methodName);
        $args = [];

        foreach ($reflection->getParameters() as $param) {
            if (isset($params[$param->name])) {
                $args[$param->name] = $params[$param->name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[$param->name] = $param->getDefaultValue();
            }
        }

        $timeCollector->stopMeasure('execute');

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
            $realAdminApiUrl = SYSTEM_URL[-1] === '/' ? substr(SYSTEM_URL, 0, -1) . $adminApiPrefix : SYSTEM_URL . $adminApiPrefix;
            $allowedURLs[] = parse_url($realAdminApiUrl)['path'];
        }
        foreach ($allowedURLs as $url) {
            if (preg_match('/^' . str_replace('/', '\/', $url) . '(.*)/', $requestPath) !== 0) {
                return false;
            }
        }

        return true;
    }

    protected function checkAllowedIPs(): bool
    {
        $allowedIPs = Config::getProperty('maintenance_mode.allowed_ips', []);
        $visitorIP = $this->di['request']->getClientIp();

        // Check if the visitor is in using of the allowed IPs/networks
        foreach ($allowedIPs as $network) {
            if (!str_contains((string) $network, '/')) {
                $network .= '/32';
            }
            [$network, $netmask] = explode('/', (string) $network, 2);
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

    protected function checkAdminPrefix(): bool
    {
        $requestPath = $this->getRequestPath();
        $realAdminUrl = SYSTEM_URL[-1] === '/' ? substr(SYSTEM_URL, 0, -1) . ADMIN_PREFIX : SYSTEM_URL . ADMIN_PREFIX;
        $realAdminPath = parse_url($realAdminUrl)['path'];

        if (preg_match('/^' . str_replace('/', '\/', $realAdminPath) . '(.*)/', $requestPath) !== 0) {
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
        $sharedCount = count($this->shared);
        for ($i = 0; $i < $sharedCount; ++$i) {
            $mapping = $this->shared[$i];
            $url = new Box_UrlHelper($mapping[0], $mapping[1], $mapping[3], $this->url, $this->getRequest()->getMethod());
            if ($url->match) {
                $timeCollector->stopMeasure('sharedMapping');

                return $this->normalizeResponse($this->executeShared($mapping[4], $mapping[2], $url->params));
            }
        }
        $timeCollector->stopMeasure('sharedMapping');

        // this class mappings
        $timeCollector->startMeasure('mapping', 'Checking mappings');
        $mappingsCount = count($this->mappings);
        for ($i = 0; $i < $mappingsCount; ++$i) {
            $mapping = $this->mappings[$i];
            $url = new Box_UrlHelper($mapping[0], $mapping[1], $mapping[3], $this->url, $this->getRequest()->getMethod());
            if ($url->match) {
                $timeCollector->stopMeasure('mapping');

                return $this->normalizeResponse($this->execute($mapping[2], $url->params));
            }
        }
        $timeCollector->stopMeasure('mapping');

        $e = new FOSSBilling\InformationException('Page :url not found', [':url' => $this->url], 404);

        return $this->show404($e);
    }
}
