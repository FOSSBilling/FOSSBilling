<?php

/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\News\Controller;

class Client implements \Box\InjectionAwareInterface
{
    protected ?\Pimple\Container $di;

    /**
     * @param \Pimple\Container $di
     * @return void
     */
    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    /**
     * @return \Pimple\Container|null
     */
    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function register(\Box_App &$app)
    {
        $app->get('/news', 'get_news', [], static::class);
        $app->get('/news/:slug', 'get_news_item', ['slug' => '[a-z0-9-]+'], static::class);
    }

    public function get_news(\Box_App $app)
    {
        return $app->render('mod_news_index');
    }

    public function get_news_item(\Box_App $app, $slug)
    {
        $post = $this->di['api_guest']->news_get(['slug' => $slug]);

        return $app->render('mod_news_post', ['post' => $post]);
    }
}
