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
 * with this source code in the file LICENSE.
 */

namespace Box\Mod\Servicedownloadable\Controller;

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
        $app->get('/servicedownloadable/get-file/:id', 'get_download', ['id' => '[0-9]+'], static::class);
    }

    public function get_download(\Box_App $app, $id)
    {
        $api = $this->di['api_client'];
        $data = [
            'order_id' => $id,
        ];
        $api->servicedownloadable_send_file($data);
    }
}
