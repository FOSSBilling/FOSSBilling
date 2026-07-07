<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Cart\Api;

use FOSSBilling\PaginationOptions;
use FOSSBilling\Validation\Api\RequiredParams;

/**
 * Shopping cart management.
 */
class Admin extends \FOSSBilling\Api\AbstractApi
{
    /**
     * @param array $data
     *
     * @return array
     */
    public function get_list($data)
    {
        [$sql, $params] = $this->getService()->getSearchQuery($data);
        $pager = $this->getDi()['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));

        foreach ($pager['list'] as $key => $cartArr) {
            $cart = $this->getDi()['db']->getExistingModelById('Cart', $cartArr['id'], 'Cart not found');
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
    #[RequiredParams(['id' => 'Shopping cart ID is missing'])]
    public function get($data)
    {
        $cart = $this->getDi()['db']->getExistingModelById('Cart', $data['id'], 'Shopping cart not found');

        return $this->getService()->toApiArray($cart);
    }

    /**
     * Remove shopping carts that are older than a week and was not ordered.
     *
     * @BOXBILLING_CRON
     */
    public function batch_expire($data): bool
    {
        $this->getDi()['logger']->info('Executed action to clear expired shopping carts from database');

        $query = 'SELECT id, created_at FROM `cart` WHERE DATEDIFF(CURDATE(), created_at) > 7;';
        $list = $this->getDi()['db']->getAssoc($query);
        if ($list) {
            foreach ($list as $id => $created_at) {
                $this->getDi()['db']->exec('DELETE FROM `cart_product` WHERE cart_id = :id', [':id' => $id]);
                $this->getDi()['db']->exec('DELETE FROM `cart` WHERE id = :id', [':id' => $id]);
            }
            $this->getDi()['logger']->info('Removed %s expired shopping carts', \FOSSBilling\Tools::safeCount($list));
        }

        return true;
    }
}
