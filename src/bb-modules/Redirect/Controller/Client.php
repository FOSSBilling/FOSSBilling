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

namespace Box\Mod\Redirect\Controller;

class Client implements \Box\InjectionAwareInterface
{
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
        $app->get('/me', 'get_profile', [], '\Box\Mod\Client\Controller\Client');
        $app->get('/balance', 'get_balance', [], '\Box\Mod\Client\Controller\Client');
        $app->get('/reset-password-confirm/:hash', 'get_reset_password_confirm', ['hash' => '[a-z0-9]+'], '\Box\Mod\Client\Controller\Client');
        $app->get('/emails', 'get_emails', [], '\Box\Mod\Email\Controller\Client');
        $app->get('/banklink/:hash/:id', 'get_banklink', ['id' => '[0-9]+', 'hash' => '[a-z0-9]+'], '\Box\Mod\Invoice\Controller\Client');
        $app->get('/blog', 'get_news', [], '\Box\Mod\News\Controller\Client');
        $app->get('/blog/:slug', 'get_news_item', ['slug' => '[a-z0-9-]+'], '\Box\Mod\News\Controller\Client');
        $app->get('/service', 'get_orders', [], '\Box\Mod\Order\Controller\Client');
        $app->get('/service/manage/:id', 'get_order', ['id' => '[0-9]+'], '\Box\Mod\Order\Controller\Client');
        $app->get('/contact-us', 'get_contact_us', [], '\Box\Mod\Support\Controller\Client');
        $app->get('/contact-us/conversation/:hash', 'get_contact_us_conversation', ['hash' => '[a-z0-9]+'], '\Box\Mod\Support\Controller\Client');

        $service = $this->di['mod_service']('redirect');
        $redirects = $service->getRedirects();
        foreach ($redirects as $redirect) {
            $app->get('/'.$redirect['path'], 'do_redirect', [], get_class($this));
        }
    }

    public function do_redirect(\Box_App $app)
    {
        $service = $this->di['mod_service']('redirect');
        $target = $service->getRedirectByPath($app->uri);
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: '.$target);
        exit;
    }
}
