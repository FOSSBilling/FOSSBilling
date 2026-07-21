<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * This file connects FOSSBilling client area interface and API.
 */

namespace Box\Mod\Api\Controller;

use Box\Mod\Client\Entity\Client as ClientEntity;
use Box\Mod\Staff\Entity\Admin;
use FOSSBilling\Config;
use FOSSBilling\Environment;
use FOSSBilling\Http\ApiResponseFactory;
use FOSSBilling\Http\ResponseFactory;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Security\AuthenticationRequiredException;
use FOSSBilling\Security\EmailValidationRequiredException;
use Symfony\Component\HttpFoundation\Response;

class Client implements InjectionAwareInterface
{
    private ?array $apiConfig = null;
    protected ?\Pimple\Container $di = null;

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

    public function show_error(\Box_App $app, $page): Response
    {
        $exc = new \FOSSBilling\Exception('Unknown API call :call', [':call' => $page], 879);

        return $this->renderJson(null, $exc);
    }

    public function get_method(\Box_App $app, $role, $class, $method): Response
    {
        $call = $class . '_' . $method;

        return $this->tryCall($role, $class, $call, $app->getRequest()->query->all());
    }

    public function post_method(\Box_App $app, $role, $class, $method): Response
    {
        try {
            $p = $app->getRequest()->getPayload()->all();
        } catch (\Symfony\Component\HttpFoundation\Exception\JsonException $e) {
            $message = $e->getPrevious()?->getMessage() ?? $e->getMessage();

            return $this->renderJson(null, new \FOSSBilling\Exception('Malformed JSON input: :error', [':error' => $message], 400));
        }

        $call = $class . '_' . $method;

        return $this->tryCall($role, $class, $call, $p);
    }

    /**
     * @param string $call
     */
    private function tryCall($role, $class, $call, $p): Response
    {
        try {
            return $this->_apiCall($role, $class, $call, $p);
        } catch (AuthenticationRequiredException) {
            return $this->renderJson(null, new \FOSSBilling\InformationException('Authentication Failed', null, 201));
        } catch (EmailValidationRequiredException $exc) {
            return $this->renderJson(null, new \FOSSBilling\InformationException($exc->getMessage(), null, 403));
        } catch (\Exception $exc) {
            // Sentry by default only captures unhandled exceptions, so we need to manually capture these.
            \Sentry\captureException($exc);

            error_log('=== API EXCEPTION TRACE === ' . $exc->getMessage());
            error_log($exc->getTraceAsString());
            error_log('=== END API EXCEPTION TRACE ===');

            return $this->renderJson(null, $exc);
        }
    }

    private function _loadConfig(): void
    {
        if (is_null($this->apiConfig)) {
            $this->apiConfig = Config::getProperty('api', []);
        }
    }

    private function checkUpdateFinalization(string $role, string $class, string $method): void
    {
        if ($role === 'admin' && $this->di['update_finalization']->isRequired() && !$this->di['update_finalization']->isAdminApiCallAllowed($class, $method)) {
            throw new \FOSSBilling\InformationException('FOSSBilling update finalization is pending. Complete finalization before using the admin API.', [], 503);
        }
    }

    private function checkRateLimit(string $role, ?string $method = null, bool $useAuthenticatedSubject = true): bool
    {
        $subject = (string) $this->_getIp();

        if ($method === 'staff_login' || $method === 'client_login') {
            $policy = 'api_login';
        } elseif ($role === 'guest') {
            $policy = 'api_guest';
        } else {
            $policy = 'api_authenticated_ip';

            if ($useAuthenticatedSubject && $role === 'client' && $this->di['session']->get('client_id')) {
                $subject = 'client:' . $this->di['session']->get('client_id');
                $policy = 'api_authenticated_account';
            } elseif ($useAuthenticatedSubject && $role === 'admin' && $this->di['session']->get('admin')) {
                $admin = $this->di['session']->get('admin');
                $subject = 'admin:' . ($admin['id'] ?? '');
                $policy = 'api_authenticated_account';
            }
        }

        $this->di['rate_limiter']->consumeOrThrow($policy, $subject);

        return true;
    }

    private function checkPreAuthRateLimit(string $role, ?string $method = null): bool
    {
        return $this->checkRateLimit($role, $method, false);
    }

    private function checkHttpReferer(): bool
    {
        // snake oil: check request is from the same domain as FOSSBilling is installed if present
        $check_referer_header = isset($this->apiConfig['require_referrer_header']) && (bool) $this->apiConfig['require_referrer_header'];
        if ($check_referer_header) {
            $url = strtolower(SYSTEM_URL);
            $referer = $this->di['request']->headers->get('Referer');
            $referer = is_string($referer) ? strtolower($referer) : null;
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

    private function _apiCall($role, $class, $method, $params): Response
    {
        $this->_loadConfig();
        $this->checkAllowedIps();

        $this->checkHttpReferer();
        $this->isRoleAllowed($role);

        if ($role !== 'guest') {
            $this->checkPreAuthRateLimit($role, $method);

            if ($this->shouldPreferSessionAuth($method) && $this->hasAuthenticatedSession($role)) {
                $this->requireSessionAuth($role);
            } elseif ($this->shouldUseTokenLogin($role)) {
                $this->_tryTokenLogin($role);
            } else {
                $this->requireSessionAuth($role);
            }
        }

        $this->checkUpdateFinalization($role, $class, $method);
        $this->checkRateLimit($role, $method);

        $api = $this->di['api_identity']($role);
        unset($params['CSRFToken']);
        $result = $this->di['api_dispatcher']->dispatch($api->getIdentity(), $method, $params);

        if ($result instanceof Response) {
            return $this->sendResponse($result);
        }

        $isAjax = $this->di['request']->isXmlHttpRequest();
        $isLoginMethod = ($method === 'login');
        $isStaffLogin = ($class === 'staff');
        $isClientLogin = ($class === 'client');

        if ($isLoginMethod && !$isAjax && ($isStaffLogin || $isClientLogin)) {
            if ($isStaffLogin) {
                $redirectUrl = $this->di['url']->adminLink('');
            } else {
                $redirectUrl = $this->di['url']->link('');
            }

            return (new ResponseFactory())->redirect($redirectUrl);
        }

        return $this->renderJson($result);
    }

    private function getAuth(): array
    {
        $server = $this->di['request']->server;

        if (!$server->has('PHP_AUTH_USER') && $this->di['request']->headers->has('Authorization')) {
            $parsedAuth = $this->tryParseBasicAuthHeader();
            if ($parsedAuth === null) {
                throw new \FOSSBilling\InformationException('Authentication Failed', null, 201);
            }

            $server->set('PHP_AUTH_USER', $parsedAuth['username']);
            $server->set('PHP_AUTH_PW', $parsedAuth['password']);
        }

        if (!$server->has('PHP_AUTH_USER')) {
            throw new \FOSSBilling\InformationException('Authentication Failed', null, 201);
        }

        if (!$server->has('PHP_AUTH_PW')) {
            throw new \FOSSBilling\InformationException('Authentication Failed', null, 202);
        }

        if ($server->get('PHP_AUTH_PW') === '') {
            throw new \FOSSBilling\InformationException('Authentication Failed', null, 206);
        }

        return [(string) $server->get('PHP_AUTH_USER'), (string) $server->get('PHP_AUTH_PW')];
    }

    protected function _tryTokenLogin(string $routeRole): void
    {
        [$username, $password] = $this->getAuth();
        if ($username !== $routeRole) {
            throw new \FOSSBilling\InformationException('Authentication Failed', null, 203);
        }

        switch ($routeRole) {
            case 'client':
                $model = $this->di['em']->getRepository(ClientEntity::class)->findOneBy(['apiToken' => $password, 'status' => ClientEntity::ACTIVE]);
                if (!$model instanceof ClientEntity) {
                    throw new \FOSSBilling\InformationException('Authentication Failed', null, 204);
                }
                $this->di['session']->set('client_id', $model->getId());

                break;

            case 'admin':
                $admins = $this->di['em']->getRepository(Admin::class)->findBy(['apiToken' => $password, 'status' => Admin::STATUS_ACTIVE]);
                $model = null;
                foreach ($admins as $admin) {
                    if (!$admin->isCron()) {
                        $model = $admin;
                        break;
                    }
                }
                if (!$model instanceof Admin) {
                    throw new \FOSSBilling\InformationException('Authentication Failed', null, 205);
                }

                $cronAdmin = $this->di['mod_service']('staff')->getCronAdmin();
                if ($cronAdmin instanceof Admin && (int) $model->getId() === (int) $cronAdmin->getId()) {
                    throw new \FOSSBilling\InformationException('Authentication Failed', null, 205);
                }

                $sessionAdminArray = [
                    'id' => $model->getId(),
                    'email' => $model->getEmail(),
                    'name' => $model->getName(),
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

    private function shouldPreferSessionAuth(string $method): bool
    {
        return in_array($method, [
            'profile_api_key_get',
            'profile_api_key_reset',
            'profile_generate_api_key',
        ], true);
    }

    private function hasAuthenticatedSession(string $role): bool
    {
        try {
            return $this->isRoleLoggedIn($role);
        } catch (\Exception) {
            return false;
        }
    }

    private function getProvidedBasicAuthUsername(): ?string
    {
        $server = $this->di['request']->server;
        $username = $server->get('PHP_AUTH_USER');
        $password = $server->get('PHP_AUTH_PW');
        if (is_string($username) && $password !== null && in_array($username, ['client', 'admin'], true)) {
            return $username;
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
        $authorization = $this->di['request']->headers->get('Authorization');
        if ($authorization === null) {
            return null;
        }

        $authorization = trim($authorization);
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
        } catch (AuthenticationRequiredException|\Exception) {
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

    public function renderJson($data = null, ?\Exception $e = null): Response
    {
        $this->_loadConfig();

        if ($e instanceof \Exception) {
            error_log("{$e->getMessage()} {$e->getCode()}.");
        }

        return (new ApiResponseFactory())->create($data, $e);
    }

    protected function sendResponse(Response $response): Response
    {
        return $response;
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

        $input = $this->di['request']->getContent();
        $data = json_decode((string) $input);
        if (!is_object($data)) {
            $data = new \stdClass();
        }

        $cookieToken = $this->di['request']->cookies->get('csrf_token');
        $headerToken = $this->di['request']->headers->get('X-CSRF-TOKEN');

        $token = $data->CSRFToken
            ?? $this->di['request']->request->get('CSRFToken')
            ?? $this->di['request']->query->get('CSRFToken')
            ?? $headerToken
            ?? null;

        $sessionToken = $this->di['session']->get('csrf_token');

        if (!is_string($token) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            throw new \FOSSBilling\InformationException('CSRF token invalid', null, 403);
        }

        if (is_string($cookieToken) && $cookieToken !== '' && !hash_equals($sessionToken, $cookieToken)) {
            throw new \FOSSBilling\InformationException('CSRF token invalid', null, 403);
        }
    }
}
