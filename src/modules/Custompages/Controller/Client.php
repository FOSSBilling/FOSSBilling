<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * This file connects FOSSBilling client area interface and API
 * Class does not extend any other class.
 */

namespace Box\Mod\Custompages\Controller;

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

    /**
     * Methods maps client areas urls to corresponding methods
     * Always use your module prefix to avoid conflicts with other modules
     * in future.
     *
     * @param \Box_App $app - returned by reference
     */
    public function register(\Box_App &$app)
    {
        $app->get('/custompages/:slug', 'get_page', ['slug' => '[a-z0-9-]+'], static::class);
    }

    public function get_page(\Box_App $app, $slug)
    {
        $service = new \Box\Mod\Custompages\Service();
        $service->setDi($this->di);
        $page = $service->getPage($slug, 'slug');
        if (isset($page['id'])) {
            return $app->render('mod_custompages_content', ['page' => $page]);
        } else {
            exit(header('Location: ' . $this->di['url']->get('')));
        }
    }
}
