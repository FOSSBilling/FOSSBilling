<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicecustom\Api;

use FOSSBilling\Validation\Api\RequiredParams;

/**
 * Custom product management.
 */
class Client extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Call a method from the service's plugin.
     * Pass any additional params and they will be passed to the plugin method.
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['order_id' => 'Order ID is required', 'method' => 'Method is required'])]
    public function call($data)
    {
        if (!isset($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required');
        }

        if (!isset($data['method'])) {
            throw new \FOSSBilling\Exception('Method is required');
        }

        $identity = $this->getIdentity();
        $model = $this->getService()->getServiceCustomByOrderId($data['order_id'], $identity->id);

        return $this->getService()->customCall($model, $data['method'], $data);
    }
}
