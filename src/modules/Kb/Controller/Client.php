<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Kb\Controller;

class Client implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di;

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
        $app->get('/kb', 'get_kb', [], static::class);
        $app->get('/kb/:category', 'get_kb_category', ['category' => '[a-z0-9-]+'], static::class);
        $app->get('/kb/:category/:slug', 'get_kb_article', ['category' => '[a-z0-9-]+', 'slug' => '[a-z0-9-]+'], static::class);
    }

    public function get_kb(\Box_App $app)
    {
        return $app->render('mod_kb_index');
    }

    public function get_kb_category(\Box_App $app, $category)
    {
        $api = $this->di['api_guest'];
        $data = ['slug' => $category];
        $model = $api->kb_category_get($data);

        return $app->render('mod_kb_category', ['category' => $model]);
    }

    public function get_kb_article(\Box_App $app, $category, $slug)
    {
        $api = $this->di['api_guest'];
        $data = ['slug' => $slug];
        $article = $api->kb_article_get($data);

        return $app->render('mod_kb_article', ['article' => $article]);
    }
}
