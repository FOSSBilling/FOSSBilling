<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\News\Controller;

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
