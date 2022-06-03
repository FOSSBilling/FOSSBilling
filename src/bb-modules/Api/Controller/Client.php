<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * This file connects FOSSBilling client area interface and API.
 */

namespace Box\Mod\Api\Controller;

use Box\InjectionAwareInterface;

class Client implements InjectionAwareInterface
{
    private $_requests_left = null;
    private $_api_config = null;
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public function register(\Box_App &$app)
    {
        $app->post('/api/:role/:class/:method', 'post_method', ['role', 'class', 'method'], get_class($this));
        $app->get('/api/:role/:class/:method', 'get_method', ['role', 'class', 'method'], get_class($this));

        // all other requests are error requests
        $app->get('/api/:page', 'show_error', ['page' => '(.?)+'], get_class($this));
        $app->post('/api/:page', 'show_error', ['page' => '(.?)+'], get_class($this));
    }

    public function show_error(\Box_App $app, $page)
    {
        $exc = new \Box_Exception('Unknown API call :call', [':call' => $page], 879);

        return $this->renderJson(null, $exc);
    }

    public function get_method(\Box_App $app, $role, $class, $method)
    {
        $call = $class.'_'.$method;

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

        $call = $class.'_'.$method;

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
            $this->renderJson(null, $exc);
        }
    }

    private function _loadConfig()
    {
        if (is_null($this->_api_config)) {
            $this->_api_config = $this->di['config']['api'];
        }
    }

    private function checkRateLimit($method = null)
    {
        $isLoginMethod = false;
        if ('staff_login' == $method || 'client_login' == $method) {
            $rate_span = $this->_api_config['rate_span_login'];
            $rate_limit = $this->_api_config['rate_limit_login'];
            $isLoginMethod = true;
        } else {
            $rate_span = $this->_api_config['rate_span'];
            $rate_limit = $this->_api_config['rate_limit'];
        }

        $service = $this->di['mod_service']('api');
        $requests = $service->getRequestCount(time() - $rate_span, $this->_getIp(), $isLoginMethod);
        $requests_left = $rate_limit - $requests;
        $this->_requests_left = $requests_left;
        if ($requests_left < 0) {
            sleep($this->_api_config['throttle_delay']);
        }

        return true;
    }

    private function checkHttpReferer()
    {
        // snake oil: check request is from the same domain as FOSSBilling is installed if present
        $check_referer_header = isset($this->_api_config['require_referrer_header']) ? (bool) $this->_api_config['require_referrer_header'] : false;
        if ($check_referer_header) {
            $url = strtolower(BB_URL);
            $referer = isset($_SERVER['HTTP_REFERER']) ? strtolower($_SERVER['HTTP_REFERER']) : null;
            if (!$referer || $url != substr($referer, 0, strlen($url))) {
                throw new \Box_Exception('Invalid request. Make sure request origin is :from', [':from' => BB_URL], 1004);
            }
        }

        return true;
    }

    private function checkAllowedIps()
    {
        $ips = $this->_api_config['allowed_ips'];
        if (!empty($ips) && !in_array($this->_getIp(), $ips)) {
            throw new \Box_Exception('Unauthorized IP', null, 1002);
        }

        return true;
    }

    private function isRoleLoggedIn($role)
    {
        if ('client' == $role) {
            $this->di['is_client_logged'];
        }
        if ('admin' == $role) {
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
        } catch (\Exception $e) {
            $this->_tryTokenLogin();
        }

        $api = $this->di['api']($role);
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
            throw new \Box_Exception('Authentication Failed', null, 201);
        }

        if (!isset($_SERVER['PHP_AUTH_PW'])) {
            throw new \Box_Exception('Authentication Failed', null, 202);
        }

        if (empty($_SERVER['PHP_AUTH_PW'])) {
            throw new \Box_Exception('Authentication Failed', null, 206);
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
                    throw new \Box_Exception('Authentication Failed', null, 204);
                }
                $this->di['session']->set('client_id', $model->id);
                break;

            case 'admin':
                $model = $this->di['db']->findOne('Admin', 'api_token = ?', [$password]);
                if (!$model instanceof \Model_Admin) {
                    throw new \Box_Exception('Authentication Failed', null, 205);
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
                throw new \Box_Exception('Authentication Failed', null, 203);
        }
    }

    /**
     * @param string $role
     *
     * @throws \Box_Exception
     */
    private function isRoleAllowed($role)
    {
        $allowed = ['guest', 'client', 'admin'];
        if (!in_array($role, $allowed)) {
            throw new \Box_Exception('Unknow API call', null, 701);
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
        header('X-FOSSBilling-Version: '.\Box_Version::VERSION);
        header('X-RateLimit-Span: '.$this->_api_config['rate_span']);
        header('X-RateLimit-Limit: '.$this->_api_config['rate_limit']);
        header('X-RateLimit-Remaining: '.$this->_requests_left);
        if (null !== $e) {
            error_log($e->getMessage().' '.$e->getCode());
            $code = $e->getCode() ? $e->getCode() : 9999;
            $result = ['result' => null, 'error' => ['message' => $e->getMessage(), 'code' => $code]];
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
}
