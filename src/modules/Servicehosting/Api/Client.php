<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicehosting\Api;

use Box\Mod\Order\Entity\Order;
use Box\Mod\Servicehosting\Entity\ServiceHosting;

/**
 * Hosting service management.
 */
class Client extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Change hosting account username.
     *
     * @return bool
     */
    public function change_username($data)
    {
        [$order, $s] = $this->_getService($data);

        return $this->getService()->changeAccountUsername($order, $s, $data);
    }

    /**
     * Change hosting account domain.
     *
     * @return bool
     */
    public function change_domain($data)
    {
        [$order, $s] = $this->_getService($data);

        return $this->getService()->changeAccountDomain($order, $s, $data);
    }

    /**
     * Change hosting account password.
     *
     * @return bool
     */
    public function change_password($data)
    {
        [$order, $s] = $this->_getService($data);

        return $this->getService()->changeAccountPassword($order, $s, $data);
    }

    /**
     * Get hosting plans pairs. Usually for select box.
     *
     * @return array
     */
    public function hp_get_pairs($data)
    {
        return $this->getService()->getHpPairs();
    }

    /**
     * Returns the login URL for a given order ID.
     * If the associated server manager supports SSO, an SSO link will be given.
     * Will automatically return either a reseller URL or a standard URL depending on the order config.
     *
     * @param array $data An array containing the API request data. Should have a key named `order_id` containing the order's ID.
     */
    public function get_login_url(array $data): string
    {
        [$order, $s] = $this->_getService($data);

        return $this->getService()->generateLoginUrl($s);
    }

    public function _getService($data): array
    {
        if (!isset($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required');
        }
        $identity = $this->getIdentity();
        $order = $this->getDi()['em']->getRepository(Order::class)->findOneBy(['id' => $data['order_id'], 'clientId' => $identity->getId()]);
        if (!$order instanceof Order) {
            throw new \FOSSBilling\InformationException('Order not found');
        }

        $orderService = $this->getDi()['mod_service']('order');
        $orderService->assertOrderUsable($order);
        $s = $orderService->getOrderService($order);
        if ((!$s instanceof ServiceHosting) || $order->getStatus() !== Order::STATUS_ACTIVE) {
            throw new \FOSSBilling\InformationException('Order is not activated');
        }

        return [$order, $s];
    }
}
