<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Security\Controller;

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

    public function register(\Box_App &$app)
    {
        $app->get('/security/iplookup', 'ip_lookup', [], static::class);
    }

    public function ip_lookup(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        $record = [];

        if (isset($_GET['ip']) && filter_var($_GET['ip'], FILTER_VALIDATE_IP)) {
            try {
                $record = $this->di['api']('admin')->Security_IP_Lookup(['ip' => $_GET['ip']]);
            } catch (\Exception) {
            }
        }

        return $app->render('mod_security_iplookup', ['record' => $record]);
    }
}
