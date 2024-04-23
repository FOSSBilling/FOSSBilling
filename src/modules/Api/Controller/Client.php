<?php

/**
 * Copyright 2022-2024 FOSSBilling
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

class Client implements InjectionAwareInterface
{
    private int|float|null $_requests_left = null;
    private $_api_config;
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function register(\Box_App &$app)
    {
        $app->post('/api/:role/:class/:method', 'post_method', ['role', 'class', 'method'], static::class);
        $app->get('/api/:role/:class/:method', 'get_method', ['role', 'class', 'method'], static::class);

        // all other requests are error requests
        $app->get('/api/:page', 'show_error', ['page' => '(.?)+'], static::class);
        $app->post('/api/:page', 'show_error', ['page' => '(.?)+'], static::class);
    }

    public function show_error(\Box_App $app, $page)
    {
        $exc = new \FOSSBilling\Exception('Unknown API call :call', [':call' => $page], 879);

        return $this->renderJson(null, $exc);
    }

    public function get_method(\Box_App $app, $role, $class, $method)
    {
        $call = $class . '_' . $method;

        return $this->tryCall($role, $call, $_GET);
    }

    public function post_method(\Box_App $app, $role, $class, $method)
    {
        $p = $_POST;

        // adding support for raw post input with json string
        $input = file_get_contents('php://input');
        if (empty($p) && !empty($input)) {
            $p = @json_decode($input, 1);
        }

        $call = $class . '_' . $method;

        return $this->tryCall($role, $call, $p);
    }

    /**
     * @param string $call
     */
    private function tryCall($role, $call, $p)
    {
        try {
            $this->_apiCall($role, $call, $p);
        } catch (\Exception $exc) {
            // Sentry by default only captures unhandled exceptions, so we need to manually capture these.
            \Sentry\captureException($exc);
            $this->renderJson(null, $exc);
        }
    }

    private function _loadConfig()
    {
        if (is_null($this->_api_config)) {
            $this->_api_config = Config::getProperty('api', []);
        }
    }

    private function checkRateLimit($method = null)
    {
        if (in_array($this->_getIp(), $this->_api_config['rate_limit_whitelist'])) {
            return true;
        }

        $isLoginMethod = false;
        if ($method == 'staff_login' || $method == 'client_login') {
            $rate_span = $this->_api_config['rate_span_login'];
            $rate_limit = $this->_api_config['rate_limit_login'];
            $isLoginMethod = true;
        } else {
            $rate_span = $this->_api_config['rate_span'];
            $rate_limit = $this->_api_config['rate_limit'];
        }

        $service = $this->di['mod_service']('api');
        $requests = $service->getRequestCount(time() - $rate_span, $this->_getIp(), $isLoginMethod);
        $this->_requests_left = $rate_limit - $requests;
        if ($this->_requests_left <= 0) {
            sleep($this->_api_config['throttle_delay']);
        }

        return true;
    }

    private function checkHttpReferer()
    {
        // snake oil: check request is from the same domain as FOSSBilling is installed if present
        $check_referer_header = isset($this->_api_config['require_referrer_header']) && (bool) $this->_api_config['require_referrer_header'];
        if ($check_referer_header) {
            $url = strtolower(SYSTEM_URL);
            $referer = isset($_SERVER['HTTP_REFERER']) ? strtolower($_SERVER['HTTP_REFERER']) : null;
            if (!$referer || !str_starts_with($referer, $url)) {
                throw new \FOSSBilling\InformationException('Invalid request. Make sure request origin is :from', [':from' => SYSTEM_URL], 1004);
            }
        }

        return true;
    }

    private function checkAllowedIps()
    {
        $ips = $this->_api_config['allowed_ips'];
        if (!empty($ips) && !in_array($this->_getIp(), $ips)) {
            throw new \FOSSBilling\InformationException('Unauthorized IP', null, 1002);
        }

        return true;
    }

    private function isRoleLoggedIn($role)
    {
        if ($role == 'client') {
            $this->di['is_client_logged'];
        }
        if ($role == 'admin') {
            $this->di['is_admin_logged'];
        }

        return true;
    }

    private function _apiCall($role, $method, $params)
    {
        $this->_loadConfig();
        $this->checkAllowedIps();

        $service = $this->di['mod_service']('api');
        $service->logRequest();
        $this->checkRateLimit($method);
        $this->checkHttpReferer();
        $this->isRoleAllowed($role);

        try {
            $this->isRoleLoggedIn($role);
            if ($role == 'client' || $role == 'admin') {
                $this->_checkCSRFToken();
            }
        } catch (\Exception) {
            $this->_tryTokenLogin();
        }

        $api = $this->di['api']($role);
        unset($params['CSRFToken']);
        $result = $api->$method($params);

        return $this->renderJson($result);
    }

    private function getAuth()
    {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_params = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
            $_SERVER['PHP_AUTH_USER'] = $auth_params[0];
            unset($auth_params[0]);
            $_SERVER['PHP_AUTH_PW'] = implode('', $auth_params);
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

    private function _tryTokenLogin()
    {
        [$username, $password] = $this->getAuth();

        switch ($username) {
            case 'client':
                $model = $this->di['db']->findOne('Client', 'api_token = ?', [$password]);
                if (!$model instanceof \Model_Client) {
                    throw new \FOSSBilling\InformationException('Authentication Failed', null, 204);
                }
                $this->di['session']->set('client_id', $model->id);

                break;

            case 'admin':
                $model = $this->di['db']->findOne('Admin', 'api_token = ?', [$password]);
                if (!$model instanceof \Model_Admin) {
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

            case 'guest': // do not allow at the moment
            default:
                throw new \FOSSBilling\InformationException('Authentication Failed', null, 203);
        }
    }

    /**
     * @param string $role
     *
     * @throws \FOSSBilling\Exception
     */
    private function isRoleAllowed($role)
    {
        $allowed = ['guest', 'client', 'admin'];
        if (!in_array($role, $allowed)) {
            new \FOSSBilling\Exception('Unknown API call :call', [':call' => ''], 701);
        }

        return true;
    }

    public function renderJson($data = null, \Exception $e = null)
    {
        // do not emit response if headers already sent
        if (headers_sent()) {
            return;
        }

        $this->_loadConfig();

        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Content-type: application/json; charset=utf-8');
        header('X-FOSSBilling-Version: ' . \FOSSBilling\Version::VERSION);
        header('X-RateLimit-Span: ' . $this->_api_config['rate_span']);
        header('X-RateLimit-Limit: ' . $this->_api_config['rate_limit']);
        header('X-RateLimit-Remaining: ' . $this->_requests_left);
        if ($e instanceof \Exception) {
            error_log($e->getMessage() . ' ' . $e->getCode());
            $code = $e->getCode() ?: 9999;
            $result = ['result' => null, 'error' => ['message' => $e->getMessage(), 'code' => $code]];
            $authFailed = [201, 202, 206, 204, 205, 203, 403, 1004, 1002];

            if (in_array($code, $authFailed)) {
                header('HTTP/1.1 401 Unauthorized');
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
        return $this->di['request']->getClientAddress();
    }

    /**
     * Checks if the CSRF token provided is valid.
     *
     * @throws \FOSSBilling\InformationException
     */
    public function _checkCSRFToken()
    {
        $this->_loadConfig();
        $csrfPrevention = $this->_api_config['CSRFPrevention'] ?? true;
        if (!$csrfPrevention || Environment::isCLI()) {
            return true;
        }

        $input = file_get_contents('php://input') ?? '';
        $data = json_decode($input);
        if (!is_object($data)) {
            $data = new \stdClass();
        }

        $token = $data->CSRFToken ?? $_POST['CSRFToken'] ?? $_GET['CSRFToken'] ?? null;
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $expectedToken = (!is_null($_COOKIE['PHPSESSID'])) ? hash('md5', $_COOKIE['PHPSESSID']) : null;
        } else {
            $expectedToken = hash('md5', session_id());
        }

        /* Due to the way the cart works, it creates a new session which causes issues with the CSRF token system.
         * Due to this, we whitelist the checkout URL.
         */
        if (str_contains($_SERVER['REQUEST_URI'], '/api/client/cart/checkout')) {
            return true;
        }

        if (!is_null($expectedToken) && $expectedToken !== $token) {
            throw new \FOSSBilling\InformationException('CSRF token invalid', null, 403);
        }
    }
}
