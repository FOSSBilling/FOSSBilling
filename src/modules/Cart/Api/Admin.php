<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Cart\Api;

/**
 * Shopping cart management.
 */
class Admin extends \Api_Abstract
{
    /**
     * @param array $data
     *
     * @return array
     */
    public function get_list($data)
    {
        [$sql, $params] = $this->getService()->getSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);

        foreach ($pager['list'] as $key => $cartArr) {
            $cart = $this->di['db']->getExistingModelById('Cart', $cartArr['id'], 'Cart not found');
            $pager['list'][$key] = $this->getService()->toApiArray($cart);
        }

        return $pager;
    }

    /**
     * Get the contents of a shopping cart by ID.
     *
     * @param array $data Data array
     *
     * @return array Contents of the shopping cart
     */
    public function get($data)
    {
        $required = [
            'id' => 'Shopping cart id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $cart = $this->di['db']->getExistingModelById('Cart', $data['id'], 'Shopping cart not found');

        return $this->getService()->toApiArray($cart);
    }

    /**
     * Remove shopping carts that are older than a week and was not ordered.
     *
     * @BOXBILLING_CRON
     *
     * @return bool
     */
    public function batch_expire($data)
    {
        $this->di['logger']->info('Executed action to clear expired shopping carts from database');

        $query = 'SELECT id, created_at FROM `cart` WHERE DATEDIFF(CURDATE(), created_at) > 7;';
        $list = $this->di['db']->getAssoc($query);
        if ($list) {
            foreach ($list as $id => $created_at) {
                $this->di['db']->exec('DELETE FROM `cart_product` WHERE cart_id = :id', [':id' => $id]);
                $this->di['db']->exec('DELETE FROM `cart` WHERE id = :id', [':id' => $id]);
            }
            $this->di['logger']->info('Removed %s expired shopping carts', is_countable($list) ? count($list) : 0);
        }

        return true;
    }
}
