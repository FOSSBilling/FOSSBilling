<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\AlertPlus\Controller;

class Admin implements \FOSSBilling\InjectionAwareInterface
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

    public function fetchNavigation()
    {
        return [
            "subpages" => [
                [
                    "location" => "extensions",
                    "label" => __trans("Alert Plus"),
                    "index" => 2000,
                    "uri" => $this->di["url"]->adminLink("alertplus"),
                    "class" => "",
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get("/alertplus", "get_index", [], static::class);
    }

    public function get_index(\Box_App $app)
    {
        $this->di["is_admin_logged"];

        return $app->render("mod_alertplus_index");
    }
}
