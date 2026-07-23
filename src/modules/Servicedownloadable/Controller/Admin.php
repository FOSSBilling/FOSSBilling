<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicedownloadable\Controller;

use Symfony\Component\HttpFoundation\Response;

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

    public function register(\Box_App &$app): void
    {
        $app->get('/servicedownloadable/get-file/:id/:fileId', 'get_download', ['id' => '[0-9]+', 'fileId' => '[a-f0-9]{32}'], static::class);
        $app->get('/servicedownloadable/order-file/:orderId/:fileId', 'get_order_download', ['orderId' => '[0-9]+', 'fileId' => '[0-9]+'], static::class);
    }

    public function get_download(\Box_App $app, $id, $fileId): Response
    {
        $this->di['is_admin_logged'];

        $api = $this->di['api_admin'];
        $data = [
            'id' => $id,
            'file_id' => $fileId,
        ];

        return $api->servicedownloadable_send_file($data);
    }

    public function get_order_download(\Box_App $app, $orderId, $fileId): Response
    {
        $this->di['is_admin_logged'];

        return $this->di['api_admin']->servicedownloadable_send_order_file([
            'order_id' => $orderId,
            'file_id' => $fileId,
        ]);
    }
}
