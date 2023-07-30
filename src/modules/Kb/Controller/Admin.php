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

class Admin implements \FOSSBilling\InjectionAwareInterface
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

    public function fetchNavigation()
    {
        return [
            'group' => [
                'index' => 501,
                'location' => 'kb',
                'label' => __trans('Knowledge base'),
                'uri' => $this->di['url']->adminLink('kb'),
                'class' => 'info',
                'sprite_class' => 'dark-sprite-icon sprite-docs',
            ],
            'subpages' => [
                [
                    'location' => 'kb',
                    'index' => 800,
                    'label' => __trans('Knowledge base'),
                    'uri' => $this->di['url']->adminLink('kb'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/kb', 'get_index', [], static::class);
        $app->get('/kb/article/:id', 'get_post', ['id' => '[0-9]+'], static::class);
        $app->get('/kb/category/:id', 'get_cat', ['id' => '[0-9]+'], static::class);
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_kb_index');
    }

    public function get_post(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $post = $api->kb_article_get(['id' => $id]);

        return $app->render('mod_kb_article', ['post' => $post]);
    }

    public function get_cat(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $cat = $api->kb_category_get(['id' => $id]);

        return $app->render('mod_kb_category', ['category' => $cat]);
    }
}
