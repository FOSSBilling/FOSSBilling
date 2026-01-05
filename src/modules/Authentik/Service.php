<?php

namespace Box\Mod\Authentik;

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

    /**
     * Retrieves the configuration from the database.
     */
    public function getConfig(): array
    {
        return $this->di['mod_config']('authentik');
    }

    /**
     * Generates the OIDC Authorization URL and redirects the user.
     */
    public function login(): string
    {
        $config = $this->getConfig();
        $this->validateConfig($config);

        $state = bin2hex(random_bytes(16));
        $this->di['session']->set('authentik_state', $state);

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $this->getRedirectUri(),
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $state,
        ];

        $url = rtrim($config['issuer_url'], '/') . '/application/o/authorize/?' . http_build_query($params);

        return $url;
    }

    /**
     * Handles the callback from Authentik.
     */
    public function callback(array $data): void
    {
        $config = $this->getConfig();
        $this->validateConfig($config);

        // state validation
        $state = $this->di['session']->get('authentik_state');
        if (empty($state) || empty($data['state']) || $state !== $data['state']) {
            throw new \FOSSBilling\InformationException('Invalid state parameter');
        }

        if (isset($data['error'])) {
            throw new \FOSSBilling\InformationException('Authentik Error: ' . $data['error']);
        }

        if (empty($data['code'])) {
            throw new \FOSSBilling\InformationException('No authorization code received');
        }

        // Exchange code for token
        $tokenData = $this->exchangeCodeForToken($data['code'], $config);

        // Get User Info
        $userInfo = $this->getUserInfo($tokenData['access_token'], $config);

        // Log in or register user
        $this->loginUser($userInfo);
    }

    private function exchangeCodeForToken(string $code, array $config): array
    {
        $url = rtrim($config['issuer_url'], '/') . '/application/o/token/';

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
        // SSL Verification should be enabled in production.
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log('Authentik Token Error: ' . $response);
            throw new \FOSSBilling\InformationException('Failed to exchange code for token. Check logs.');
        }

        return json_decode($response, true);
    }

    private function getUserInfo(string $accessToken, array $config): array
    {
        $url = rtrim($config['issuer_url'], '/') . '/application/o/userinfo/';

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
            error_log('Authentik UserInfo Error: ' . $response);
            throw new \FOSSBilling\InformationException('Failed to get user info.');
        }

        return json_decode($response, true);
    }

    private function loginUser(array $userInfo): void
    {
        $email = $userInfo['email'] ?? null;
        if (empty($email)) {
            throw new \FOSSBilling\InformationException('No email provided by Authentik user info.');
        }

        $clientService = $this->di['mod_service']('client');

        // Check if client exists
        // We use direct DB access or Service equivalent. 
        // Service::clientAlreadyExists returns bool

        $client = $this->di['db']->findOne('Client', 'email = ?', [$email]);

        if (!$client) {
            // Register new client
            // We need a password. We generate a random one.
            $password = bin2hex(random_bytes(10));

            $data = [
                'email' => $email,
                'first_name' => $userInfo['given_name'] ?? 'Authentik',
                'last_name' => $userInfo['family_name'] ?? 'User',
                'password' => $password,
                'status' => 'active',
                'auth_type' => 'authentik',
            ];

            // Note: Guest API create would validate extensive fields. 
            // We might want to use internal createClient if possible or mock the data.
            // Using internal createClient from Service directly bypasses validators? 
            // The service method is private in modules/Client/Service.php based on view_file!
            // Wait, createClient is private? Let's check. 
            // line 476: private function createClient(array $data)
            // But guestCreateClient is public. line 549.

            // We will use guestCreateClient but we need to satisfy validators if we use the API wrapper.
            // But here we are in a service, we can call guestCreateClient directly on the Client Service instance?
            // Yes, standard PHP method call.

            // We might need dummy values for required fields? 
            // Let's check required fields config.

            try {
                $client = $clientService->guestCreateClient($data);
                $this->di['logger']->info('Created new client via Authentik: ' . $email);
            } catch (\Exception $e) {
                // Fallback if detailed validation fails (e.g. phone number required)
                // We could redirect to a "finish signup" page, but for now we error.
                error_log($e->getMessage());
                throw new \FOSSBilling\InformationException('Could not create account automatically: ' . $e->getMessage());
            }
        }

        // Force Login
        // Based on Client/Api/Guest.php login logic:

        $this->di['events_manager']->fire(['event' => 'onBeforeClientLogin', 'params' => ['id' => $client->id]]);

        session_regenerate_id();
        $this->di['session']->set('client_id', $client->id);

        // Log activity
        $this->di['logger']->info('Client logged in via Authentik: ' . $client->id);

        $this->di['events_manager']->fire(['event' => 'onAfterClientLogin', 'params' => ['id' => $client->id, 'ip' => $this->di['request']->getClientIp()]]);
    }

    private function getRedirectUri(): string
    {
        return $this->di['tools']->url('auth/callback') . '?absolute=1';
    }

    private function validateConfig(?array $config): void
    {
        if (empty($config['client_id']) || empty($config['client_secret']) || empty($config['issuer_url'])) {
            throw new \FOSSBilling\InformationException('Authentik module is not configured.');
        }
    }
}
