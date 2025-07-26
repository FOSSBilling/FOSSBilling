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

namespace FOSSBilling;

use Pimple\Container;

class Api implements InjectionAwareInterface
{
    protected ?Container $di = null;

    private array $apiConfig;
    private int|null $requestsLeft = null;

    public function __construct()
    {
        $this->apiConfig = \FOSSBilling\Config::getProperty('api', []);
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function registerApiRoutes(App $app): void
    {
        $app->post('/api/:role/:class/:method', 'post_method', ['role', 'class', 'method'], self::class);
        $app->get('/api/:role/:class/:method', 'get_method', ['role', 'class', 'method'], self::class);
        $app->get('/api/:page', 'show_error', ['page' => '(.?)+'], self::class);
        $app->post('/api/:page', 'show_error', ['page' => '(.?)+'], self::class);
    }

    public function show_error(App $app, $page)
    {
        $exc = new \FOSSBilling\Exception('Unknown API call :call', [':call' => $page], 879);

        return $this->renderJson(null, $exc);
    }

    public function get_method(App $app, $role, $class, $method)
    {
        $call = $class . '_' . $method;

        return $this->tryCall($role, $call, $_GET);
    }

    public function post_method(App $app, $role, $class, $method)
    {
        $p = $_POST;
        $input = file_get_contents('php://input');
        if (empty($p) && !empty($input)) {
            $p = @json_decode($input, true);
        }
        $call = $class . '_' . $method;

        return $this->tryCall($role, $call, $p);
    }

    private function tryCall($role, $call, $p)
    {
        try {
            return $this->_apiCall($role, $call, $p);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            $this->renderJson(null, $e);
        }
    }

    private function validateRequest($role, $method)
    {
        $clientIp = $this->di['request']->getClientIp();

        // Check allowed IPs.
        $allowedIps = $this->apiConfig['allowed_ips'] ?? [];
        if (!empty($allowedIps) && !in_array($clientIp, $allowedIps)) {
            throw new \FOSSBilling\InformationException('Unauthorized IP', null, 1002);
        }

        // Check rate limit.
        $this->checkRateLimit($method);
        $this->checkHttpReferer();

        // Check if role is allowed.
        $roleAllowed = ['guest', 'client', 'admin'];
        if (!in_array($role, $roleAllowed)) {
            throw new \FOSSBilling\InformationException('Unknown API call :call', [':call' => $role], 701);
        }
    }

    private function checkRateLimit($method = null)
    {
        $clientIp = $this->di['request']->getClientIp();
        if (in_array($clientIp, $this->apiConfig['rate_limit_whitelist'] ?? [])) {
            return true;
        }

        $isLoginMethod = in_array($method, ['staff_login', 'client_login']);
        $rate_span = $isLoginMethod ? ($this->apiConfig['rate_span_login'] ?? 3600) : ($this->apiConfig['rate_span'] ?? 3600);
        $rate_limit = $isLoginMethod ? ($this->apiConfig['rate_limit_login'] ?? 10) : ($this->apiConfig['rate_limit'] ?? 1000);

        if ($isLoginMethod) {
            usleep(random_int(25000, 250000));
        }

        $since = time() - $rate_span;
        $sinceIso = date('Y-m-d H:i:s', $since);
        $values = ['since' => $sinceIso];
        $sql = 'SELECT COUNT(id) as cc FROM api_request WHERE created_at > :since';

        if ($clientIp != null) {
            $sql .= ' AND ip = :ip';
            $values['ip'] = $clientIp;
        }

        $requests = (int) $this->di['db']->getCell($sql, $values);
        $this->requestsLeft = $rate_limit - $requests;

        if ($this->requestsLeft <= 0) {
            sleep($this->apiConfig['throttle_delay'] ?? 1);
        }

        return true;
    }

    private function checkHttpReferer()
    {
        $check_referer_header = isset($this->apiConfig['require_referrer_header']) && (bool) $this->apiConfig['require_referrer_header'];
        if ($check_referer_header) {
            $url = strtolower(SYSTEM_URL);
            $referer = isset($_SERVER['HTTP_REFERER']) ? strtolower($_SERVER['HTTP_REFERER']) : null;
            if (!$referer || !str_starts_with($referer, $url)) {
                throw new \FOSSBilling\InformationException('Invalid request. Make sure request origin is :from', [':from' => SYSTEM_URL], 1004);
            }
        }

        return true;
    }

    private function _apiCall($role, $method, $params)
    {
        $this->validateRequest($role, $method);
        $request = $this->di['request'];
        $sql = 'INSERT INTO api_request (ip, request, created_at) VALUES(:ip, :request, NOW())';
        $values = [
            'ip' => $request->getClientIp(),
            'request' => $_SERVER['REQUEST_URI'] ?? null,
        ];
        $this->di['db']->exec($sql, $values);
        try {
            if ($role == 'client') {
                $this->di['is_client_logged'];
            }
            if ($role == 'admin') {
                $this->di['is_admin_logged'];
            }

            if ($role == 'client' || $role == 'admin') {
                $this->_checkCSRFToken();
            }
        } catch (\Exception $exc) {
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
            case 'guest':
            default:
                throw new \FOSSBilling\InformationException('Authentication Failed', null, 203);
        }
    }

    public function renderJson($data = null, ?\Exception $e = null)
    {
        if (headers_sent()) {
            return;
        }
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Content-type: application/json; charset=utf-8');
        header('X-FOSSBilling-Version: ' . \FOSSBilling\Version::VERSION);
        header('X-RateLimit-Span: ' . ($this->apiConfig['rate_span'] ?? 3600));
        header('X-RateLimit-Limit: ' . ($this->apiConfig['rate_limit'] ?? 1000));
        header('X-RateLimit-Remaining: ' . ($this->requestsLeft ?? 0));
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

    public function _checkCSRFToken()
    {
        $csrfPrevention = $this->apiConfig['CSRFPrevention'] ?? true;
        if (!$csrfPrevention || \FOSSBilling\Environment::isCLI()) {
            return true;
        }
        $input = file_get_contents('php://input') ?? '';
        $data = json_decode($input);
        if (!is_object($data)) {
            $data = new \stdClass();
        }
        $token = $data->CSRFToken ?? $_POST['CSRFToken'] ?? $_GET['CSRFToken'] ?? null;
        $expectedToken = session_status() !== PHP_SESSION_ACTIVE
            ? (!empty($_COOKIE['PHPSESSID']) ? hash('md5', $_COOKIE['PHPSESSID']) : null)
            : hash('md5', session_id());
        if (str_contains($_SERVER['REQUEST_URI'], '/api/client/cart/checkout')) {
            return true;
        }
        if (!is_null($expectedToken) && $expectedToken !== $token) {
            throw new \FOSSBilling\InformationException('CSRF token invalid', null, 403);
        }
    }
}
