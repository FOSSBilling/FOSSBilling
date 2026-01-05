<?php

namespace Box\Mod\Auth\Controller;

class Client implements \FOSSBilling\InjectionAwareInterface
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

    public function register(\Box_App &$app): void
    {
        // Custom routes
        $app->get('/auth/login', 'get_login', [], static::class);
        $app->get('/auth/callback', 'get_callback', [], static::class);
    }

    public function get_login(\Box_App $app): never
    {
        // Get the service for 'auth'
        $service = $this->di['mod_service']('auth');

        try {
            $url = $service->getLoginUrl();
            // Critical: Use raw PHP header redirect to avoid framework interference
            // with external URLs (preventing double base URL issues).
            header("Location: " . $url);
            exit;
        } catch (\Exception $e) {
            $this->di['session']->set('flash_error', $e->getMessage());
            $app->redirect('/login');
        }
    }

    public function get_callback(\Box_App $app): never
    {
        $service = $this->di['mod_service']('authentik');
        $params = $this->di['request']->getQuery();

        try {
            $service->callback($params);

            // Login successful
            $this->di['session']->set('flash_success', 'Logged in via Authentik successfully.');
            $app->redirect('/client'); // Go to dashboard
        } catch (\Exception $e) {
            error_log("Authentik Callback Exception: " . $e->getMessage());
            $this->di['session']->set('flash_error', $e->getMessage());
            $app->redirect('/login');
        }
    }
}
