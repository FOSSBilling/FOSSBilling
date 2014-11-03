<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * Products management 
 */

namespace Box\Mod\Product\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of products
     * 
     * @return array
     */
    public function get_list($data)
    {
        $service = $this->getService();

        list($sql, $params) = $service->getProductSearchQuery($data);
        $per_page = isset($data['per_page']) ? $data['per_page'] : $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $model               = $this->di['db']->getExistingModelById('Product', $item['id'], 'Post not found');
            $pager['list'][$key] = $this->getService()->toApiArray($model, false, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Get product pair. Id -> title values
     * 
     * @return array
     */
    public function get_pairs($data)
    {
        $service = $this->getService();
        return $service->getPairs($data);
    }
    
    /**
     * Get product details
     * 
     * @param int $id - product id
     * @return type 
     */
    public function get($data)
    {
        $model = $this->_getProduct($data);
        $service = $this->getService();
        return $service->toApiArray($model, true, $this->getIdentity());
    }
    
    /**
     * Get installed product types
     * 
     * @return array 
     */
    public function get_types()
    {
        return $this->getService()->getTypes();
    }
    
    /**
     * Create new product. Set default values depending on type
     * 
     * @param string $title - product title
     * @param string $type - product type
     *
     * @optional string $product_category_id - category id
     *
     * @return int - new product id
     * @throws Box_Exception 
     */
    public function prepare($data)
    {
        if(!isset($data['title'])) {
            throw new \Box_Exception('Title is required');
        }

        if(!isset($data['type'])) {
            throw new \Box_Exception('type');
        }

        $service = $this->getService();
        //allow having only one domain product
        if($data['type'] == 'domain') {

            if($service->getMainDomainProduct() instanceof \Model_ProductDomain) {
                throw new \Box_Exception('You have already created domain product.', null, 413);
            }
        }

        $types = $service->getTypes();
        if(!array_key_exists($data['type'], $types)) {
            throw new \Box_Exception('Product type :type is not registered', array(':type'=>$data['type']), 413);
        }

        $categoryId = isset($data['product_category_id']) ? $data['product_category_id'] : null;

        return (int) $service->createProduct($data['title'], $data['type'], $categoryId);
    }
    
    /**
     * Update prodcut settings.
     * 
     * @param int $id - product id
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
     * 
     * @optional bool $stock_control - product stock control flag.
     * @optional bool $allow_quantity_select - client can select product quantity on order form flag
     * @optional bool $quantity_in_stock - quantity available for sale. When out of stock, new order can not be placed.
     * 
     * @return boolean
     * @throws Box_Exception 
     */
    public function update($data)
    {
        $model = $this->_getProduct($data);
        $service = $this->getService();
        return $service->updateProduct($model, $data);
    }

    /**
     * Change products sorting order
     * 
     * @param array $priority - id => number key value pairs to define sort order for all products
     * @return boolean
     * @throws Box_Exception 
     */
    public function update_priority($data)
    {
        if(!isset($data['priority']) || !is_array($data['priority'])) {
            throw new \Box_Exception('priority params is missing');
        }

        $service = $this->getService();
        return $service->updatePriority($data);
    }

    /**
     * Convenience method to update product config only
     * 
     * @param int $id - product id
     * 
     * @optional array $config - product config key value array
     * 
     * @return boolean 
     */
    public function update_config($data)
    {
        $model = $this->_getProduct($data);
        
        $service = $this->getService();
        return $service->updateConfig($model, $data);
    }

    /**
     * Get available addons
     * 
     * @return array 
     */
    public function addon_get_pairs($data)
    {
        return $this->getService()->getAddons();
    }

    /**
     * Create new addon
     * 
     * @param string $title - addon title
     * 
     * @return int - new addon id
     * @throws Box_Exception 
     */
    public function addon_create($data)
    {
        if(!isset($data['title'])) {
            throw new \Box_Exception('Title is required');
        }
        $title  = $data['title'];
        $status = isset($data['status']) ? $data['status'] : null;
        $setup  = isset($data['setup']) ? $data['setup'] : null;
        $iconUrl = isset($data['icon_url']) ? $data['icon_url'] : null;
        $description = isset($data['description']) ? $data['description'] : null;

        $service = $this->getService();
        return $service->createAddon($title, $description, $setup, $status, $iconUrl);
    }

    /**
     * Get addon details
     * 
     * @param int $id - addon id
     * 
     * @return array
     * @throws Box_Exception 
     */
    public function addon_get($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('Addon id is required');
        }

        $model = $this->di['db']->load('Product', $data['id']);
        if(!$model instanceof \Model_Product || !$model->is_addon) {
            throw new \Box_Exception('Addon not found');
        }
        $service = $this->getService();

        return $service->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Addon update
     * 
     * @param int $id - addon id
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
     * 
     * @optional bool $stock_control - product stock control flag.
     * @optional bool $allow_quantity_select - client can select product quantity on order form flag
     * @optional bool $quantity_in_stock - quantity available for sale. When out of stock, new order can not be placed.
     * 
     * @return bool
     * @throws Box_Exception 
     */
    public function addon_update($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('Addon id is required');
        }

        $model = $this->di['db']->load('Product', $data['id']);
        if(!$model instanceof \Model_Product || !$model->is_addon) {
            throw new \Box_Exception('Addon not found');
        }
        return $this->update($data);
        $this->di['logger']->info('Updated addon #%s', $model->id);
    }

    /**
     * Remove addon
     * 
     * @param int $id - addon id
     * 
     * @return bool 
     */
    public function addon_delete($data)
    {
        return $this->delete($data);
    }

    /**
     * Remove product
     * 
     * @param int $id - addon id
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
     * Get product category pairs
     * 
     * @return array
     */
    public function category_get_pairs($data)
    {
        return $this->getService()->getProductCategoryPairs($data);
    }
    
    /**
     * Method to update category
     * 
     * @param int $id - category id
     * 
     * @optional string $title - category title
     * @optional string $icon_url - icon url
     * @optional string $description - description
     * 
     * @return boolean
     * @throws Box_Exception 
     */
    public function category_update($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('Category id is missing');
        }
        $model = $this->di['db']->load('ProductCategory', $data['id']);
        if(!$model instanceof \Model_ProductCategory) {
            throw new \Box_Exception('Category not found');
        }

        $title          = isset($data['title']) ? $data['title'] : null;
        $description    = isset($data['description']) ? $data['description'] : null;
        $icon_url       = isset($data['icon_url']) ? $data['icon_url'] : null;

        $service = $this->getService();
        return $service->updateCategory($model, $title, $description, $icon_url);

    }

    /**
     * Get category details
     * 
     * @return array
     * @throws Box_Exception 
     */
    public function category_get($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('Category id is missing');
        }

        $model = $this->di['db']->load('ProductCategory', $data['id']);
        if(!$model instanceof \Model_ProductCategory) {
            throw new \Box_Exception('Category not found');
        }
        return $this->getService()->toProductCategoryApiArray($model);
    }

    /**
     * Create new product category
     * 
     * @param string $title - new category title
     * 
     * @optional string $icon_url - icon url
     * @optional string $description - description
     * 
     * @return int - new category id
     * 
     * @throws Box_Exception 
     */
    public function category_create($data)
    {
        if(!isset($data['title'])) {
            throw new \Box_Exception('Category title is missing');
        }
        $service = $this->getService();

        $title = isset($data['title']) ? $data['title'] : null;
        $description = isset($data['description']) ? $data['description'] : null;
        $icon_url = isset($data['icon_url']) ? $data['icon_url'] : null;

        return (int) $service->createCategory($title, $description, $icon_url);

    }

    /**
     * Remove product category
     * 
     * @param int $id - category id
     * @return boolean
     * @throws Box_Exception 
     */
    public function category_delete($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('Category id is missing');
        }

        $model = $this->di['db']->load('ProductCategory', $data['id']);
        if(!$model instanceof \Model_ProductCategory) {
            throw new \Box_Exception('Category not found');
        }
        $service = $this->getService();
        return $service->removeProductCategory($model);
    }

    /**
     * Get product promo codes list
     * 
     * @return array
     */
    public function promo_get_list($data)
    {
        $service = $this->getService();
        list($sql, $params) = $service->getPromoSearchQuery($data);
        $per_page = isset($data['per_page']) ? $data['per_page'] : $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $model               = $this->di['db']->getExistingModelById('Promo', $item['id'], 'Promo not found');
            $pager['list'][$key] = $this->getService()->toPromoApiArray($model);
        }

        return $pager;
    }
    
    /**
     * Create new promo code
     * 
     * @param string $code - promo code
     * @param string $type - promo code type: percentage|absolute
     * @param string $value - promo code value. Percents or discount amount in currency
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
     * @throws Box_Exception 
     */
    public function promo_create($data)
    {
        if(!isset($data['code'])) {
            throw new \Box_Exception('Promo code is missing');
        }
        if(!isset($data['type'])) {
            throw new \Box_Exception('Promo type is missing');
        }
        if(!isset($data['value'])) {
            throw new \Box_Exception('Promo value is missing');
        }
        
        $products = array();
        if(isset($data['products']) && is_array($data['products'])) {
            $products = $data['products'];
        }
        $periods = array();
        if(isset($data['periods']) && is_array($data['periods'])) {
            $periods = $data['periods'];
        }
        $service = $this->getService();
        return (int) $service->createPromo($data['code'], $data['type'], $data['value'], $products, $periods, $data);
    }
    
    /**
     * Get promo code details
     * 
     * @param int $id - promo code id
     * 
     * @return array
     * @throws Box_Exception 
     */
    public function promo_get($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('Promo id is missing');
        }
        
        $model = $this->di['db']->load('Promo', $data['id']);
        if(!$model instanceof \Model_Promo) {
            throw new \Box_Exception('Promo not found');
        }

        return $this->getService()->toPromoApiArray($model, true, $this->getIdentity());
    }

    /**
     * Promo code update
     * 
     * @param int $id - promo code id
     * 
     * @optional string $code - promo code
     * @optional string $type - promo code type: percentage|absolute
     * @optional string $value - promo code value. Percents or discount amount in currency
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
     * @optional int $used - how many times this promo code was already used
     * 
     * @return boolean
     * @throws Box_Exception 
     */
    public function promo_update($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('Promo id is missing');
        }

        $model = $this->di['db']->load('Promo', $data['id']);
        if(!$model instanceof \Model_Promo) {
            throw new \Box_Exception('Promo not found');
        }

        $service = $this->getService();
        return $service->updatePromo($model, $data);
    }
    
    /**
     * Delete promo code
     * 
     * @param int $id - promo code id
     * 
     * @return bool
     * @throws Box_Exception 
     */
    public function promo_delete($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('Promo id is missing');
        }

        $model = $this->di['db']->load('Promo', $data['id']);
        if(!$model instanceof \Model_Promo) {
            throw new \Box_Exception('Promo not found');
        }
        return $this->getService()->deletePromo($model);
    }
    
    private function _getProduct($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('Product id not passed');
        }

        $model = $this->di['db']->load('Product', $data['id']);
        if(!$model instanceof \Model_Product) {
            throw new \Box_Exception('Product not found');
        }
        return $model;
    }
}