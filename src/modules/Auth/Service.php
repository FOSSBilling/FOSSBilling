<?php

namespace Box\Mod\Auth;

use FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function install(): bool
    {
        return true;
    }

    public function uninstall(): bool
    {
        return true;
    }

    public function update(array $manifest): bool
    {
        return true;
    }

    public function getModuleConfig()
    {
        return $this->di['mod_config']('auth');
    }

    /**
     * Generates the OIDC Authorization URL.
     */
    public function getLoginUrl(): string
    {
        $config = $this->getModuleConfig();
        $this->validateConfig($config);

        $state = bin2hex(random_bytes(16));
        $this->di['session']->set('auth_state', $state);

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $this->getRedirectUri(),
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $state,
        ];

        // Ensure issuer_url doesn't end with slash for cleaner concatenation
        $issuer = rtrim($config['issuer_url'], '/');

        return $issuer . '/application/o/authorize/?' . http_build_query($params);
    }

    /**
     * Handles the callback from Authentik.
     */
    public function callback(array $data): void
    {
        $config = $this->getModuleConfig();
        $this->validateConfig($config);

        // State validation
        $storedState = $this->di['session']->get('auth_state');
        if (empty($storedState) || empty($data['state']) || $storedState !== $data['state']) {
            throw new \FOSSBilling\InformationException('Invalid state parameter. Please try again.');
        }

        if (isset($data['error'])) {
            throw new \FOSSBilling\InformationException('Auth Error: ' . ($data['error_description'] ?? $data['error']));
        }

        if (empty($data['code'])) {
            throw new \FOSSBilling\InformationException('No authorization code given.');
        }

        // 1. Exchange Code for Token
        $tokenData = $this->exchangeCodeForToken($data['code'], $config);

        // 2. Get User Info
        $userInfo = $this->getUserInfo($tokenData['access_token'], $config);

        // 3. Login or Register in FOSSBilling
        $this->authenticateUser($userInfo);
    }

    private function exchangeCodeForToken(string $code, array $config): array
    {
        $issuer = rtrim($config['issuer_url'], '/');
        $url = $issuer . '/application/o/token/';

        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->getRedirectUri(),
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Auth Token Exchange Error [$httpCode]: " . $response . " Curl Error: " . $error);
            throw new \FOSSBilling\InformationException('Failed to exchange code for token. Check system logs.');
        }

        return json_decode($response, true);
    }

    private function getUserInfo(string $accessToken, array $config): array
    {
        $issuer = rtrim($config['issuer_url'], '/');
        $url = $issuer . '/application/o/userinfo/';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Auth UserInfo Error [$httpCode]: " . $response);
            throw new \FOSSBilling\InformationException('Failed to retrieve user information.');
        }

        return json_decode($response, true);
    }

    private function authenticateUser(array $userInfo): void
    {
        $email = $userInfo['email'] ?? null;
        if (empty($email)) {
            throw new \FOSSBilling\InformationException('Auth module did not provide an email address.');
        }

        $clientService = $this->di['mod_service']('client');

        // Check if client exists by email
        $client = $this->di['db']->findOne('Client', 'email = ?', [$email]);

        if (!$client) {
            // Register new client
            $password = bin2hex(random_bytes(12));

            $newClientData = [
                'email' => $email,
                'first_name' => $userInfo['given_name'] ?? 'Authentik',
                'last_name' => $userInfo['family_name'] ?? 'User',
                'password' => $password,
                'password_confirm' => $password,
                'auth_type' => 'auth',
            ];

            try {
                $client = $clientService->guestCreateClient($newClientData);
                $this->di['logger']->info('New client created via Auth SSO: ' . $email);
            } catch (\Exception $e) {
                error_log("Auth Auto-Registration Failed: " . $e->getMessage());
                throw new \FOSSBilling\InformationException('Could not create account automatically: ' . $e->getMessage());
            }
        }

        $this->di['events_manager']->fire(['event' => 'onBeforeClientLogin', 'params' => ['id' => $client->id]]);

        session_regenerate_id();
        $this->di['session']->set('client_id', $client->id);

        $this->di['events_manager']->fire(['event' => 'onAfterClientLogin', 'params' => ['id' => $client->id, 'ip' => $this->di['request']->getClientIp()]]);

        $this->di['logger']->info('Client logged in via Auth: ' . $client->id);
    }

    public function getRedirectUri(): string
    {
        return $this->di['tools']->url('auth/callback') . '?absolute=1';
    }

    private function validateConfig(?array $config): void
    {
        if (empty($config['client_id']) || empty($config['client_secret']) || empty($config['issuer_url'])) {
            throw new \FOSSBilling\InformationException('Auth module is not configured. Please check settings.');
        }
    }
}