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

/**
 * This file connects FOSSBilling client area interface and API.
 */

namespace Box\Mod\Api\Controller;

use FOSSBilling\Config;
use FOSSBilling\Environment;
use FOSSBilling\InjectionAwareInterface;
use Symfony\Component\Filesystem\Filesystem;

class Client implements InjectionAwareInterface
{
    private int|float|null $_requests_left = null;
    private $apiConfig;
    private readonly Filesystem $filesystem;
    protected ?\Pimple\Container $di = null;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function register(\Box_App &$app): void
    {
        $allowedRouteRoles = $this->registerAllowedRouteRoles();
        $app->post('/api/:role/:class/:method', 'post_method', $allowedRouteRoles, static::class);
        $app->get('/api/:role/:class/:method', 'get_method', $allowedRouteRoles, static::class);

        // all other requests are error requests
        $app->get('/api/:page', 'show_error', ['page' => '(.?)+'], static::class);
        $app->post('/api/:page', 'show_error', ['page' => '(.?)+'], static::class);
    }

    public function show_error(\Box_App $app, $page): null
    {
        $exc = new \FOSSBilling\Exception('Unknown API call :call', [':call' => $page], 879);

        $this->renderJson(null, $exc);

        return null;
    }

    public function get_method(\Box_App $app, $role, $class, $method): null
    {
        $call = $class . '_' . $method;

        $this->tryCall($role, $class, $call, $_GET);

        return null;
    }

    public function post_method(\Box_App $app, $role, $class, $method): null
    {
        $p = $_POST;

        // adding support for raw post input with json string
        $input = file_get_contents('php://input');
        if (empty($p) && !empty($input)) {
            $p = json_decode($input, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                $exc = new \FOSSBilling\Exception('Malformed JSON input: :error', [':error' => json_last_error_msg()], 400);
                $this->renderJson(null, $exc);

                return null;
            }
        }

        $call = $class . '_' . $method;

        $this->tryCall($role, $class, $call, $p);

        return null;
    }

    /**
     * @param string $call
     */
    private function tryCall($role, $class, $call, $p): void
    {
        try {
            $this->_apiCall($role, $class, $call, $p);
        } catch (\Exception $exc) {
            // Sentry by default only captures unhandled exceptions, so we need to manually capture these.
            \Sentry\captureException($exc);
            $this->renderJson(null, $exc);
        }
    }

    private function _loadConfig(): void
    {
        if (is_null($this->apiConfig)) {
            $this->apiConfig = Config::getProperty('api', []);
        }
    }

    private function checkRateLimit($method = null): bool
    {
        $rateLimitWhitelist = $this->apiConfig['rate_limit_whitelist'] ?? [];
        if (in_array($this->_getIp(), $rateLimitWhitelist)) {
            return true;
        }

        $isLoginMethod = false;

        if ($method == 'staff_login' || $method == 'client_login') {
            $isLoginMethod = true;
            $rate_span = $this->apiConfig['rate_span_login'];
            $rate_limit = $this->apiConfig['rate_limit_login'];

            // 25 to 250ms delay to help prevent email enumeration.
            usleep(random_int(25000, 250000));
        } else {
            $rate_span = $this->apiConfig['rate_span'];
            $rate_limit = $this->apiConfig['rate_limit'];
        }

        $service = $this->di['mod_service']('api');
        $requests = $service->getRequestCount(time() - $rate_span, $this->_getIp(), $isLoginMethod);
        $this->_requests_left = $rate_limit - $requests;
        if ($this->_requests_left <= 0) {
            sleep($this->apiConfig['throttle_delay']);
        }

        return true;
    }

    private function checkHttpReferer(): bool
    {
        // snake oil: check request is from the same domain as FOSSBilling is installed if present
        $check_referer_header = isset($this->apiConfig['require_referrer_header']) && (bool) $this->apiConfig['require_referrer_header'];
        if ($check_referer_header) {
            $url = strtolower(SYSTEM_URL);
            $referer = isset($_SERVER['HTTP_REFERER']) ? strtolower((string) $_SERVER['HTTP_REFERER']) : null;
            if (!$referer || !str_starts_with($referer, $url)) {
                throw new \FOSSBilling\InformationException('Invalid request. Make sure request origin is :from', [':from' => SYSTEM_URL], 1004);
            }
        }

        return true;
    }

    private function checkAllowedIps(): bool
    {
        $ips = $this->apiConfig['allowed_ips'];
        if (!empty($ips) && !in_array($this->_getIp(), $ips)) {
            throw new \FOSSBilling\InformationException('Unauthorized IP', null, 1002);
        }

        return true;
    }

    protected function isRoleLoggedIn($role): bool
    {
        if ($role == 'client') {
            return (bool) ($this->di['is_client_logged'] ?? false);
        }
        if ($role == 'admin') {
            return (bool) ($this->di['is_admin_logged'] ?? false);
        }

        return true;
    }

    private function _apiCall($role, $class, $method, $params): null
    {
        $this->_loadConfig();
        $this->checkAllowedIps();

        $service = $this->di['mod_service']('api');
        $service->logRequest();
        $this->checkRateLimit($method);
        $this->checkHttpReferer();
        $this->isRoleAllowed($role);

        if ($role !== 'guest') {
            if ($this->shouldUseTokenLogin($role)) {
                $this->_tryTokenLogin($role);
            } else {
                $this->requireSessionAuth($role);
            }
        }

        $api = $this->di['api']($role);
        unset($params['CSRFToken']);
        $result = $api->$method($params);

        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $isLoginMethod = ($method === 'login');
        $isStaffLogin = ($class === 'staff');
        $isClientLogin = ($class === 'client');

        if ($isLoginMethod && !$isAjax && ($isStaffLogin || $isClientLogin)) {
            if ($isStaffLogin) {
                $redirectUrl = $this->di['url']->adminLink('');
            } elseif ($isClientLogin) {
                $redirectUrl = $this->di['url']->link('');
            } else {
                $redirectUrl = '/';
            }

            header('Location: ' . $redirectUrl);
            exit;
        }

        $this->renderJson($result);

        return null;
    }

    private function getAuth(): array
    {
        if (!isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $parsedAuth = $this->tryParseBasicAuthHeader();
            if ($parsedAuth === null) {
                throw new \FOSSBilling\InformationException('Authentication Failed', null, 201);
            }

            $_SERVER['PHP_AUTH_USER'] = $parsedAuth['username'];
            $_SERVER['PHP_AUTH_PW'] = $parsedAuth['password'];
        }

        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            throw new \FOSSBilling\InformationException('Authentication Failed', null, 201);
        }

        if (!isset($_SERVER['PHP_AUTH_PW'])) {
            throw new \FOSSBilling\InformationException('Authentication Failed', null, 202);
        }

        if (empty($_SERVER['PHP_AUTH_PW'])) {
            throw new \FOSSBilling\InformationException('Authentication Failed', null, 206);
        }

        return [$_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']];
    }

    protected function _tryTokenLogin(string $routeRole): void
    {
        [$username, $password] = $this->getAuth();
        if ($username !== $routeRole) {
            throw new \FOSSBilling\InformationException('Authentication Failed', null, 203);
        }

        switch ($routeRole) {
            case 'client':
                $model = $this->di['db']->findOne('Client', 'api_token = ? AND status = ?', [$password, \Model_Client::ACTIVE]);
                if (!$model instanceof \Model_Client) {
                    throw new \FOSSBilling\InformationException('Authentication Failed', null, 204);
                }
                $this->di['session']->set('client_id', $model->id);

                break;

            case 'admin':
                $model = $this->di['db']->findOne('Admin', 'api_token = ? AND status = ?', [$password, \Model_Admin::STATUS_ACTIVE]);
                if (!$model instanceof \Model_Admin) {
                    throw new \FOSSBilling\InformationException('Authentication Failed', null, 205);
                }

                $cronAdmin = $this->di['mod_service']('staff')->getCronAdmin();
                if ($cronAdmin instanceof \Model_Admin && (int) $model->id === (int) $cronAdmin->id) {
                    throw new \FOSSBilling\InformationException('Authentication Failed', null, 205);
                }

                $sessionAdminArray = [
                    'id' => $model->id,
                    'email' => $model->email,
                    'name' => $model->name,
                    'role' => $model->role,
                ];
                $this->di['session']->set('admin', $sessionAdminArray);

                break;

            default:
                throw new \FOSSBilling\InformationException('Authentication Failed', null, 203);
        }
    }

    private function registerAllowedRouteRoles(): array
    {
        return [
            'role' => 'guest|client|admin',
            'class' => '[a-zA-Z0-9_]+',
            'method' => '[a-zA-Z0-9_]+',
        ];
    }

    protected function shouldUseTokenLogin(string $routeRole): bool
    {
        if (!in_array($routeRole, ['client', 'admin'], true)) {
            return false;
        }

        $username = $this->getProvidedBasicAuthUsername();
        if ($username === null) {
            return false;
        }

        if ($username !== $routeRole) {
            throw new \FOSSBilling\InformationException('Authentication Failed', null, 203);
        }

        return true;
    }

    private function getProvidedBasicAuthUsername(): ?string
    {
        if (isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) && in_array($_SERVER['PHP_AUTH_USER'], ['client', 'admin'], true)) {
            return (string) $_SERVER['PHP_AUTH_USER'];
        }

        $parsedAuth = $this->tryParseBasicAuthHeader();
        if ($parsedAuth === null) {
            return null;
        }

        $username = $parsedAuth['username'];
        if (!in_array($username, ['client', 'admin'], true)) {
            return null;
        }

        return $username;
    }

    /**
     * @return array{username: string, password: string}|null
     */
    private function tryParseBasicAuthHeader(): ?array
    {
        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return null;
        }

        $authorization = trim((string) $_SERVER['HTTP_AUTHORIZATION']);
        if (stripos($authorization, 'Basic ') !== 0) {
            return null;
        }

        $decoded = base64_decode(substr($authorization, 6), true);
        if ($decoded === false || !str_contains($decoded, ':')) {
            return null;
        }

        [$username, $password] = explode(':', $decoded, 2);

        return [
            'username' => $username,
            'password' => $password,
        ];
    }

    private function requireSessionAuth(string $role): void
    {
        try {
            $this->isRoleLoggedIn($role);
            if ($role === 'client' || $role === 'admin') {
                $this->_checkCSRFToken();
            }
        } catch (\FOSSBilling\InformationException $exception) {
            throw $exception;
        } catch (\Exception) {
            throw new \FOSSBilling\InformationException('Authentication Failed', null, 201);
        }
    }

    /**
     * @param string $role
     *
     * @throws \FOSSBilling\Exception
     */
    private function isRoleAllowed($role): bool
    {
        $allowed = ['guest', 'client', 'admin'];
        if (!in_array($role, $allowed, true)) {
            throw new \FOSSBilling\Exception('Unknown API call :call', [':call' => (string) $role], 701);
        }

        return true;
    }

    public function renderJson($data = null, ?\Exception $e = null): void
    {
        // do not emit response if headers already sent
        if (headers_sent()) {
            return;
        }

        $this->_loadConfig();

        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Content-type: application/json; charset=utf-8');
        if ($this->di['mod_service']('system')->shouldExposeVersion()) {
            header('X-FOSSBilling-Version: ' . \FOSSBilling\Version::VERSION);
        }
        header('X-RateLimit-Span: ' . $this->apiConfig['rate_span']);
        header('X-RateLimit-Limit: ' . $this->apiConfig['rate_limit']);
        header('X-RateLimit-Remaining: ' . $this->_requests_left);
        if ($e instanceof \Exception) {
            error_log("{$e->getMessage()} {$e->getCode()}.");
            $code = $e->getCode() ?: 9999;
            $result = ['result' => null, 'error' => ['message' => $e->getMessage(), 'code' => $code]];
            $authFailed = [201, 202, 206, 204, 205, 203, 1004, 1002];

            if (in_array($code, $authFailed)) {
                header('HTTP/1.1 401 Unauthorized');
            } elseif ($code == 403) {
                header('HTTP/1.1 403 Forbidden');
            } elseif ($code == 701 || $code == 879) {
                header('HTTP/1.1 400 Bad Request');
            }
        } else {
            $result = ['result' => $data, 'error' => null];
        }
        echo json_encode($result);
        exit;
    }

    private function _getIp()
    {
        return $this->di['request']->getClientIp();
    }

    /**
     * Checks if the CSRF token provided is valid.
     *
     * @throws \FOSSBilling\InformationException
     */
    public function _checkCSRFToken()
    {
        $this->_loadConfig();
        $csrfPrevention = $this->apiConfig['CSRFPrevention'] ?? true;
        if (!$csrfPrevention || Environment::isCLI()) {
            return true;
        }

        $input = $this->filesystem->readFile('php://input');
        $data = json_decode($input);
        if (!is_object($data)) {
            $data = new \stdClass();
        }

        $cookieToken = $_COOKIE['csrf_token'] ?? null;
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        $token = $data->CSRFToken ?? $_POST['CSRFToken'] ?? $_GET['CSRFToken'] ?? $headerToken ?? null;

        $sessionToken = $this->di['session']->get('csrf_token');

        $validTokens = array_filter([$cookieToken, $headerToken, $sessionToken]);

        if (empty($validTokens) || !in_array($token, $validTokens, true)) {
            throw new \FOSSBilling\InformationException('CSRF token invalid', null, 403);
        }
    }
}
