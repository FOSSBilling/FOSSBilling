<?php

declare(strict_types=1);

namespace Box\Mod\Totp\Controller;

class Guest implements \FOSSBilling\InjectionAwareInterface
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
        $app->get('/totp/challenge', 'get_challenge', [], static::class);
    }

    public function get_challenge(\Box_App $app): string
    {
        return $app->render('mod_totp_challenge');
    }
}
