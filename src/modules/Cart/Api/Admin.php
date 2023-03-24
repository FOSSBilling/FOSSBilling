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
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Cart\Api;

/**
 * Shopping cart management.
 */
class Admin extends \Api_Abstract
{
    /**
     * 
     * @param array $data
     * 
     * @param int $data['per_page'] [optional]
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
     * @param int $data['id'] ID of the shopping cart
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
            $this->di['logger']->info('Removed %s expired shopping carts', count($list));
        }

        return true;
    }
}
