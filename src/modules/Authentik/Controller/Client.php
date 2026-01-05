<?php

namespace Box\Mod\Authentik\Controller;

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
        $app->get('/auth/login', 'get_login', [], static::class);
        $app->get('/auth/callback', 'get_callback', [], static::class);
    }

    public function get_login(\Box_App $app): never
    {
        $service = $this->di['mod_service']('authentik');
        $url = $service->login();
        // Use raw header to ensure external redirection works correctly without framework interference
        header("Location: $url");
        exit;
    }

    public function get_callback(\Box_App $app): never
    {
        $service = $this->di['mod_service']('authentik');
        $data = $this->di['request']->getQuery();

        try {
            $service->callback($data);
            $app->redirect('/client'); // Redirect to client dashboard on success
        } catch (\Exception $e) {
            // Log error and redirect to login with error message
            error_log($e->getMessage());
            $this->di['session']->set('flash_error', $e->getMessage());
            $app->redirect('/login');
        }
    }
}
