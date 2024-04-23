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
 * Products management.
 */

namespace Box\Mod\Product\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of products.
     *
     * @return array
     */
    public function get_list($data)
    {
        $service = $this->getService();

        [$sql, $params] = $service->getProductSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $model = $this->di['db']->getExistingModelById('Product', $item['id'], 'Post not found');
            $pager['list'][$key] = $this->getService()->toApiArray($model, false, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Get product pair. Id -> title values.
     *
     * @return array
     */
    public function get_pairs($data)
    {
        $service = $this->getService();

        return $service->getPairs($data);
    }

    /**
     * Get product details.
     *
     * @return array
     */
    public function get($data)
    {
        $model = $this->_getProduct($data);
        $service = $this->getService();

        return $service->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Get installed product types.
     *
     * @return array
     */
    public function get_types()
    {
        return $this->getService()->getTypes();
    }

    /**
     * Create new product. Set default values depending on type.
     *
     * @optional string $product_category_id - category id
     *
     * @return int - new product id
     *
     * @throws \FOSSBilling\Exception
     */
    public function prepare($data)
    {
        $required = [
            'title' => 'You must specify a title',
            'type' => 'Type is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();
        // allow having only one domain product
        if ($data['type'] == 'domain') {
            $model = $service->getMainDomainProduct();
            if ($model instanceof \Model_Product) {
                throw new \FOSSBilling\InformationException('You have already created domain product.', null, 413);
            }
        }

        $types = $service->getTypes();
        if (!array_key_exists($data['type'], $types)) {
            throw new \FOSSBilling\Exception('Product type :type is not registered', [':type' => $data['type']], 413);
        }

        $categoryId = $data['product_category_id'] ?? null;

        return (int) $service->createProduct($data['title'], $data['type'], $categoryId);
    }

    /**
     * Update product settings.
     *
     * @optional array $pricing - product pricing configuration
     * @optional array $config - product configuration options depending on type
     * @optional array $upgrades - array of upgradable products
     * @optional array $addons - array of addon products
     * @optional int $product_category_id - product category id
     * @optional string $title - product title
     * @optional string $description - detailed product description
     * @optional string $icon_url - product icon
     * @optional string $status - product status
     * @optional string $slug - product slug. Used to create unique link to order page
     * @optional string $setup - product setup option. Define when order must be activated.
     * @optional bool $hidden - product visibility flag
     * @optional bool $stock_control - product stock control flag.
     * @optional bool $allow_quantity_select - client can select product quantity on order form flag
     * @optional bool $quantity_in_stock - quantity available for sale. When out of stock, new order cannot be placed.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function update($data)
    {
        $model = $this->_getProduct($data);
        $service = $this->getService();

        return $service->updateProduct($model, $data);
    }

    /**
     * Change products sorting order.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function update_priority($data)
    {
        if (!isset($data['priority']) || !is_array($data['priority'])) {
            throw new \FOSSBilling\Exception('priority params is missing');
        }

        $service = $this->getService();

        return $service->updatePriority($data);
    }

    /**
     * Convenience method to update product config only.
     *
     * @optional array $config - product config key value array
     *
     * @return bool
     */
    public function update_config($data)
    {
        $model = $this->_getProduct($data);

        $service = $this->getService();

        return $service->updateConfig($model, $data);
    }

    /**
     * Get available addons.
     *
     * @return array
     */
    public function addon_get_pairs($data)
    {
        return $this->getService()->getAddons();
    }

    /**
     * Create new addon.
     *
     * @return int - new addon id
     *
     * @throws \FOSSBilling\Exception
     */
    public function addon_create($data)
    {
        $required = [
            'title' => 'You must specify a title',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $title = $data['title'];
        $status = $data['status'] ?? null;
        $setup = $data['setup'] ?? null;
        $iconUrl = $data['icon_url'] ?? null;
        $description = $data['description'] ?? null;

        $service = $this->getService();

        return $service->createAddon($title, $description, $setup, $status, $iconUrl);
    }

    /**
     * Get addon details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function addon_get($data)
    {
        $required = [
            'id' => 'Addon ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->load('Product', $data['id']);
        if (!$model instanceof \Model_Product || !$model->is_addon) {
            throw new \FOSSBilling\Exception('Addon not found');
        }
        $service = $this->getService();

        return $service->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Addon update.
     *
     * @optional array $pricing - product pricing configuration
     * @optional array $config - product configuration options depending on type
     * @optional array $upgrades - array of upgradable products
     * @optional array $addons - array of addon products
     * @optional int $product_category_id - product category id
     * @optional string $title - product title
     * @optional string $description - detailed product description
     * @optional string $icon_url - product icon
     * @optional string $status - product status
     * @optional string $slug - product slug. Used to create unique link to order page
     * @optional string $setup - product setup option. Define when order must be activated.
     * @optional bool $hidden - product visibility flag
     * @optional bool $stock_control - product stock control flag.
     * @optional bool $allow_quantity_select - client can select product quantity on order form flag
     * @optional bool $quantity_in_stock - quantity available for sale. When out of stock, new order cannot be placed.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function addon_update($data)
    {
        $required = [
            'id' => 'Addon ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->load('Product', $data['id']);
        if (!$model instanceof \Model_Product || !$model->is_addon) {
            throw new \FOSSBilling\Exception('Addon not found');
        }
        $this->di['logger']->info('Updated addon #%s', $model->id);

        return $this->update($data);
    }

    /**
     * Remove addon.
     *
     * @return bool
     */
    public function addon_delete($data)
    {
        return $this->delete($data);
    }

    /**
     * Remove product.
     *
     * @return bool
     */
    public function delete($data)
    {
        $model = $this->_getProduct($data);
        $service = $this->getService();

        return $service->deleteProduct($model);
    }

    /**
     * Get product category pairs.
     *
     * @return array
     */
    public function category_get_pairs($data)
    {
        return $this->getService()->getProductCategoryPairs($data);
    }

    /**
     * Method to update category.
     *
     * @optional string $title - category title
     * @optional string $icon_url - icon url
     * @optional string $description - description
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function category_update($data)
    {
        $required = [
            'id' => 'Category ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ProductCategory', $data['id'], 'Category not found');

        $title = $data['title'] ?? null;
        $description = $data['description'] ?? null;
        $icon_url = $data['icon_url'] ?? null;

        $service = $this->getService();

        return $service->updateCategory($model, $title, $description, $icon_url);
    }

    /**
     * Get category details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function category_get($data)
    {
        $required = [
            'id' => 'Category ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ProductCategory', $data['id'], 'Category not found');

        return $this->getService()->toProductCategoryApiArray($model);
    }

    /**
     * Create new product category.
     *
     * @optional string $icon_url - icon url
     * @optional string $description - description
     *
     * @return int - new category id
     *
     * @throws \FOSSBilling\Exception
     */
    public function category_create($data)
    {
        $required = [
            'title' => 'Category title is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();

        $title = $data['title'] ?? null;
        $description = $data['description'] ?? null;
        $icon_url = $data['icon_url'] ?? null;

        return (int) $service->createCategory($title, $description, $icon_url);
    }

    /**
     * Remove product category.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function category_delete($data)
    {
        $required = [
            'id' => 'Category ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ProductCategory', $data['id'], 'Category not found');
        $service = $this->getService();

        return $service->removeProductCategory($model);
    }

    /**
     * Get product promo codes list.
     *
     * @return array
     */
    public function promo_get_list($data)
    {
        $service = $this->getService();
        [$sql, $params] = $service->getPromoSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $model = $this->di['db']->getExistingModelById('Promo', $item['id'], 'Promo not found');
            $pager['list'][$key] = $this->getService()->toPromoApiArray($model);
        }

        return $pager;
    }

    /**
     * Create new promo code.
     *
     * @optional array $products - list of product ids for which this promo code applies
     * @optional array $periods - list of period codes
     * @optional bool $active - flag to enable/disable promo code
     * @optional bool $freesetup - flag to enable/disable free setup price
     * @optional bool $once_per_client - flag to enable/disable promo code usage once per client
     * @optional bool $recurring - is available for all recurring orders not for first order only
     * @optional int $maxuses - how many times this promo code can be used
     * @optional string $start_at - date (Y-m-d) when will this promo code be active
     * @optional string $end_at - date (Y-m-d) when this promo code expires
     *
     * @return int - new promo code id
     *
     * @throws \FOSSBilling\Exception
     */
    public function promo_create($data)
    {
        $required = [
            'code' => 'Promo code is missing',
            'type' => 'Promo type is missing',
            'value' => 'Promo value is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $products = [];
        if (isset($data['products']) && is_array($data['products'])) {
            $products = $data['products'];
        }
        $periods = [];
        if (isset($data['periods']) && is_array($data['periods'])) {
            $periods = $data['periods'];
        }

        $clientGroups = [];
        if (isset($data['client_groups']) && is_array($data['client_groups'])) {
            $clientGroups = $data['client_groups'];
        }
        $service = $this->getService();

        return (int) $service->createPromo($data['code'], $data['type'], $data['value'], $products, $periods, $clientGroups, $data);
    }

    /**
     * Get promo code details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function promo_get($data)
    {
        $required = [
            'id' => 'Promo ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Promo', $data['id'], 'Promo not found');

        return $this->getService()->toPromoApiArray($model, true, $this->getIdentity());
    }

    /**
     * Promo code update.
     *
     * @optional string $code - promo code
     * @optional string $type - promo code type: percentage|absolute
     * @optional string $value - promo code value. Percents or discount amount in currency
     * @optional array $products - list of product ids for which this promo code applies
     * @optional array $periods - list of period codes
     * @optional bool $active - flag to enable/disable promo code
     * @optional bool $freesetup - flag to enable/disable free setup price
     * @optional bool $once_per_client - flag to enable/disable promo code usage once per client
     * @optional bool $recurring - is available for all recurring orders not for first order only
     * @optional int $maxuses - how many times this promo code can be used
     * @optional string $start_at - date (Y-m-d) when will this promo code be active
     * @optional string $end_at - date (Y-m-d) when this promo code expires
     * @optional int $used - how many times this promo code was already used
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function promo_update($data)
    {
        $required = [
            'id' => 'Promo ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Promo', $data['id'], 'Promo not found');

        $service = $this->getService();

        return $service->updatePromo($model, $data);
    }

    /**
     * Delete promo code.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function promo_delete($data)
    {
        $required = [
            'id' => 'Promo ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Promo', $data['id'], 'Promo not found');

        return $this->getService()->deletePromo($model);
    }

    private function _getProduct($data)
    {
        $required = [
            'id' => 'Product ID not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->di['db']->getExistingModelById('Product', $data['id'], 'Product not found');
    }
}
