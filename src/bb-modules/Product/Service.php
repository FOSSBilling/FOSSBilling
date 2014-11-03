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


namespace Box\Mod\Product;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    const CUSTOM            = 'custom';
    const LICENSE           = 'license';
    const ADDON             = 'addon';
    const DOMAIN            = 'domain';
    const DOWNLOADABLE      = 'downloadable';
    const HOSTING           = 'hosting';
    const MEMBERSHIP        = 'membership';
    const VPS               = 'vps';

    const SETUP_AFTER_ORDER     = 'after_order';
    const SETUP_AFTER_PAYMENT   = 'after_payment';
    const SETUP_MANUAL          = 'manual';

    /**
     * @var \Box_Di
     */
    protected $di = null;

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    public function getPairs($data)
    {
        $sql = 'SELECT id, title
                FROM product
                WHERE 1';

        $type = isset($data['type']) ? $data['type'] : null;
        $products_only = isset($data['products_only']) ? $data['products_only'] : true;
        $active_only = isset($data['active_only']) ? $data['active_only'] : true;

        $params = array();
        if($products_only) {
            $sql .= ' AND is_addon = 0';
        }

        if($active_only) {
            $sql .= ' AND active = 1';
        }

        if($type) {
            $sql .= ' AND type = :type';
            $params['type'] = $type;
        }

        $rows = $this->di['db']->getAll($sql, $params);
        $result = array();
        foreach ($rows as $record) {
            $result[ $record['id'] ] = $record['title'];
        }
        return $result;
    }

    public function toApiArray(\Model_Product $model, $deep = true, $identity = null)
    {
        $repo = $model->getTable();
        $addons = $this->getAddonsApiArray($model);
        $config = $this->di['tools']->decodeJ($model->config, 1);
        $pricing = $repo->getPricingArray($model);
        $starting_from = $this->getStartingFromPrice($model);

        $result = array(
            'id'                    => $model->id,
            'product_category_id'          => $model->product_category_id,
            'type'          => $model->type,
            'title'         => $model->title,
            'form_id'       => $model->form_id,
            'slug'          => $model->slug,
            'description'   => $model->description,
            'unit'          => $model->unit,
            'priority'      => $model->priority,
            'created_at'    => $model->created_at,
            'updated_at'    => $model->updated_at,
            'pricing'       => $pricing,
            'config'        => $config,
            'addons'        => $addons,

            'price_starting_from'   => $starting_from,
            'icon_url'              => $model->icon_url,

            //stock control
            'allow_quantity_select' => $model->allow_quantity_select,
            'quantity_in_stock'     => $model->quantity_in_stock,
            'stock_control'         => $model->stock_control,
        );

        if($identity instanceof \Model_Admin) {
            $result['upgrades'] = $this->getUpgradablePairs($model);
            $result['status'] = $model->status;
            $result['hidden'] = $model->hidden;
            $result['setup'] = $model->setup;
            if($model->product_category_id) {
                $productCategory = $this->di['db']->load('ProductCategory', $model->product_category_id);
                $result['category'] = array(
                    'id' =>  $productCategory->id,
                    'title' =>  $productCategory->title,
                );
            }
        }

        return $result;
    }

    public function getTypes()
    {
        $data = array(
            self::CUSTOM       => 'Custom',
            self::LICENSE      => 'License',
            self::DOWNLOADABLE => 'Downloadable',
            self::HOSTING      => 'Hosting',
            self::DOMAIN       => 'Domain',
        );

        // attach service modules
        $extensionService = $this->di['mod_service']('extension');
        $list             = $extensionService->getInstalledMods();
        foreach ($list as $mod) {
            if (substr($mod, 0, strlen('service')) == 'service') {
                $n        = substr($mod, strlen('service'));
                $data[$n] = ucfirst($n);
            }
        }

        return $data;
    }

    public function getMainDomainProduct()
    {
        return $this->di['db']->findOne('Product', 'type = ?', array(self::DOMAIN));
    }

    public function getPaymentTypes()
    {
        return array(
            \Model_ProductPayment::FREE      =>  'Free',
            \Model_ProductPayment::ONCE      =>  'One time',
            \Model_ProductPayment::RECURRENT =>  'Recurrent',
        );
    }

    public function createProduct($title, $type, $categoryId = null)
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_Product', 5);
        $sql="SELECT MAX(priority) FROM product GROUP BY priority ORDER BY priority DESC LIMIT 1";
        $priority = $this->di['db']->getCell($sql);

        $modelPayment = $this->di['db']->dispense('ProductPayment');
        $modelPayment->type = \Model_ProductPayment::FREE;
        $paymentId = $this->di['db']->store($modelPayment);

        $model = $this->di['db']->dispense('Product');
        $model->product_payment_id = $paymentId;
        $model->product_category_id = $categoryId;
        $model->status = \Model_Product::STATUS_DISABLED;
        $model->title = $title;
        $model->slug = $this->di['tools']->slug($title);
        $model->type = $type;
        $model->setup = self::SETUP_AFTER_PAYMENT;
        $model->priority = $priority + 10;

        $model->updated_at = date('c');
        $model->created_at = date('c');

        // try save with slug
        try {
            $productId = $this->di['db']->store($model);
        } catch(\Exception $e) {
            $model->slug = $this->di['tools']->slug($title) .'-'. rand(1,9999);
        }
        $productId = $this->di['db']->store($model);

        $this->di['logger']->info('Created new product #%s', $model->id);
        return (int) $productId;
    }

    public function updateProduct(\Model_Product $model, $data)
    {
        //pricing
        if(isset($data['pricing'])) {
            $types = $this->getPaymentTypes();

            if(!isset($data['pricing']['type']) || !array_key_exists($data['pricing']['type'], $types) ) {
                throw new \Box_Exception('Pricing type is required');
            }
            $productPayment = $this->di['db']->load('ProductPayment', $model->product_payment_id);

            $pricing = $data['pricing'];
            $productPayment->type = $data['pricing']['type'];

            if($data['pricing']['type'] == \Model_ProductPayment::ONCE) {
                $productPayment->once_setup_price = $data['pricing']['once']['setup'];
                $productPayment->once_price = $data['pricing']['once']['price'];
            }

            if($data['pricing']['type'] == \Model_ProductPayment::RECURRENT) {

                if(isset($pricing['recurrent']['1W'])) {
                    $productPayment->w_setup_price   = $pricing['recurrent']['1W']['setup'];
                    $productPayment->w_price         = $pricing['recurrent']['1W']['price'];
                    $productPayment->w_enabled       = $pricing['recurrent']['1W']['enabled'];
                }

                if(isset($pricing['recurrent']['1M'])) {
                    $productPayment->m_setup_price   = $pricing['recurrent']['1M']['setup'];
                    $productPayment->m_price         = $pricing['recurrent']['1M']['price'];
                    $productPayment->m_enabled       = $pricing['recurrent']['1M']['enabled'];
                }

                if(isset($pricing['recurrent']['3M'])) {
                    $productPayment->q_setup_price   = $pricing['recurrent']['3M']['setup'];
                    $productPayment->q_price         = $pricing['recurrent']['3M']['price'];
                    $productPayment->q_enabled       = $pricing['recurrent']['3M']['enabled'];
                }

                if(isset($pricing['recurrent']['6M'])) {
                    $productPayment->b_setup_price   = $pricing['recurrent']['6M']['setup'];
                    $productPayment->b_price         = $pricing['recurrent']['6M']['price'];
                    $productPayment->b_enabled       = $pricing['recurrent']['6M']['enabled'];
                }

                if(isset($pricing['recurrent']['1Y'])) {
                    $productPayment->a_setup_price   = $pricing['recurrent']['1Y']['setup'];
                    $productPayment->a_price         = $pricing['recurrent']['1Y']['price'];
                    $productPayment->a_enabled       = $pricing['recurrent']['1Y']['enabled'];
                }

                if(isset($pricing['recurrent']['2Y'])) {
                    $productPayment->bia_setup_price   = $pricing['recurrent']['2Y']['setup'];
                    $productPayment->bia_price         = $pricing['recurrent']['2Y']['price'];
                    $productPayment->bia_enabled       = $pricing['recurrent']['2Y']['enabled'];
                }

                if(isset($pricing['recurrent']['3Y'])) {
                    $productPayment->tria_setup_price   = $pricing['recurrent']['3Y']['setup'];
                    $productPayment->tria_price         = $pricing['recurrent']['3Y']['price'];
                    $productPayment->tria_enabled       = $pricing['recurrent']['3Y']['enabled'];
                }
            }

            $this->di['db']->store($productPayment);
            
        }

        if(isset($data['config']) && is_array($data['config'])) {
            $current = $this->di['tools']->decodeJ($model->config);
            $c = array_merge($current, $data['config']);
            $model->config = json_encode($c);
        }

        if(isset($data['product_category_id'])) {
            $model->product_category_id = $data['product_category_id'];
        }

        if(isset($data['form_id'])) {
            $model->form_id = $data['form_id'];
        }

        if(isset($data['icon_url'])) {
            $model->icon_url = $data['icon_url'];
        }

        if(isset($data['status'])) {
            $model->status = $data['status'];
        }

        if(isset($data['hidden'])) {
            $model->hidden = (int)$data['hidden'];
        }

        if(isset($data['slug'])) {
            $model->slug = $data['slug'];
        }

        if(isset($data['setup'])) {
            $model->setup = $data['setup'];
        }

        if(isset($data['upgrades']) && is_array($data['upgrades'])) {
            $model->upgrades = json_encode($data['upgrades']);
        } elseif(isset($data['upgrades']) && empty($data['upgrades'])) {
            $model->upgrades = NULL;
        }

        if(isset($data['addons']) && is_array($data['addons'])) {
            $addons = array();
            foreach($data['addons'] as $addon=>$on) {
                if($on) {
                    $addons[] = $addon;
                }
            }
            if(empty ($addons)) {
                $model->addons = NULL;
            } else {
                $model->addons = json_encode($addons);
            }
        }

        if(isset($data['title'])) {
            $model->title = $data['title'];
        }

        if(isset($data['stock_control'])) {
            $model->stock_control = $data['stock_control'];
        }

        if(isset($data['allow_quantity_select'])) {
            $model->allow_quantity_select = $data['allow_quantity_select'];
        }

        if(isset($data['quantity_in_stock'])) {
            $model->quantity_in_stock = $data['quantity_in_stock'];
        }

        if(isset($data['description'])) {
            $model->description = $data['description'];
        }

        if(isset($data['plugin'])) {
            $model->plugin = $data['plugin'];
        }

        $model->updated_at = date('c');

        $this->di['db']->store($model);

        $this->di['logger']->info('Updated product #%s configuration', $model->id);
        return true;
    }

    public function updatePriority($data)
    {
        foreach($data['priority'] as $id => $p) {
            $model = $this->di['db']->load('Product', $id);
            if($model instanceof \Model_Product) {
                $model->priority = $p;
                $model->updated_at = date('c');
                $this->di['db']->store($model);
            }
        }

        $this->di['logger']->info('Changed product priorities');
        return true;
    }

    public function updateConfig(\Model_Product $model, $data)
    {
        /* add new config value */
        $config =json_decode($model->config, 1);

        if(isset($data['config']) && is_array($data['config'])) {
            foreach($data['config'] as $key=>$val) {
                $config[$key] = $val;
                if(isset($config[$key]) && empty ($val)) {
                    unset ($config[$key]);
                }
            }
        }

        if(isset($data['new_config_name']) &&
            isset($data['new_config_value']) &&
            !empty($data['new_config_name']) &&
            !empty($data['new_config_value'])) {

            $config[$data['new_config_name']] = $data['new_config_value'];
        }

        $model->config = json_encode($config);
        $model->updated_at = date('c');
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated product #%s configuration', $model->id);
        return true;
    }

    public function getAddons()
    {
        $sql = 'SELECT id, title
                FROM product
                WHERE is_addon =1
                ORDER by id asc';
        $addons = $this->di['db']->getAll($sql);

        $result = array();
        foreach ($addons as $addon) {
            $result[$addon['id']] = $addon['title'];
        }
        return $result;
    }

    public function createAddon($title, $description=null, $setup=null, $status=null, $iconUrl=null)
    {
        $modelPayment = $this->di['db']->dispense('ProductPayment');
        $modelPayment->type = \Model_ProductPayment::FREE;
        $paymentId = $this->di['db']->store($modelPayment);

        $model = $this->di['db']->dispense('Product');
        $model->product_payment_id = $paymentId;
        $model->product_category_id = NULL;
        $model->status = (isset($status)) ? $status : \Model_Product::STATUS_DISABLED;
        $model->title = $title;
        $model->slug = $this->di['tools']->slug($title);
        $model->type = self::CUSTOM;
        $model->setup = (isset($setup)) ? $setup : self::SETUP_AFTER_PAYMENT;
        $model->is_addon = 1;

        $model->icon_url = $iconUrl;
        $model->description = $description;

        $model->updated_at = date('c');
        $model->created_at = date('c');

        // try save with slug
        try {
            $productId = $this->di['db']->store($model);
        } catch(\Exception $e) {
            $model->slug = $this->di['tools']->slug($title) .'-'. rand(1,9999);
            $productId = $this->di['db']->store($model);
        }

        $this->di['logger']->info('Created new addon #%s', $productId);
        return $productId;
    }

    public function deleteProduct(\Model_Product $product)
    {
        $orderService = $this->di['mod_service']('order');
        if($orderService->productHasOrders($product)) {
            throw new \Box_Exception('Can not remove product which has active orders.');
        }
        $id = $product->id;
        $this->di['db']->trash($product);
        $this->di['logger']->info('Deleted product #%s', $id);
        return true;
    }

    public function getProductCategoryPairs()
    {
        $sql = 'SELECT id, title
                FROM product_category';

        $rows = $this->di['db']->getAll($sql);
        $result = array();
        foreach ($rows as $record) {
            $result[ $record['id'] ] = $record['title'];
        }
        return $result;
    }

    public function updateCategory(\Model_ProductCategory $productCategory, $title=null, $icon_url=null, $descprioption=null)
    {
        $productCategory->title = $title;
        $productCategory->icon_url = $icon_url;
        $productCategory->description = $descprioption;

        $productCategory->updated_at = date('c');
        $this->di['db']->store($productCategory);

        $this->di['logger']->info('Updated product category #%s', $productCategory->id);
        return true;
    }

    public function createCategory($title, $description = null, $icon_url = null)
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_ProductCategory', 2);

        $model = $this->di['db']->dispense('ProductCategory');
        $model->title = $title;
        $model->description = $description;
        $model->icon_url = $icon_url;
        $model->updated_at = date('c');
        $model->created_at = date('c');
        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Created new product category #%s', $id);
        return $id;
    }

    public function removeProductCategory(\Model_ProductCategory $category)
    {
        $model = $this->di['db']->findOne('Product', 'product_category_id = :category_id', array(':category_id' => $category->id));
        if($model instanceof \Model_Product) {
            throw new \Box_Exception('Can not remove product category with products');
        }
        $id = $category->id;
        $this->di['db']->trash($category);

        $this->di['logger']->info('Deleted product category #%s', $id);
        return true;
    }

    public function getPromoSearchQuery($data)
    {
        $sql = 'SELECT *
                FROM promo
                WHERE 1';

        $search     = isset($data['search']) ? $data['search'] : NULL;
        $id         = isset($data['id']) ? $data['id'] : NULL;
        $status     = isset($data['status']) ? $data['status'] : NULL;

        $params = array();
        if($id) {
            $sql .= ' AND id = :id';
            $params['id'] = $id;
        }

        if($search) {
            $sql .= ' AND code like %:search%';
            $params['search'] = $search;
        }

        switch($status) {
            case 'active':
                $sql .= ' AND start_at <= :start_at AND end_at >= :end_at';
                $params['start_at'] = time();
                $params['end_at'] = time();
                break;
            case 'not-started':
                $sql .= ' AND start_at <= :start_at';
                $params['start_at'] = time();
                break;
            case 'expired':
                $sql .= ' AND start_at <= :end_at';
                $params['end_at'] = time();
                break;
        }

        $sql .= ' ORDER BY id asc';

        return array($sql, $params);
    }

    public function createPromo($code, $type, $value, $products = array(), $periods = array(), $data)
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_Promo', 2);

        $model = $this->di['db']->dispense('Promo');
        $model->code = $code;
        $model->type = $type;
        $model->value = $value;
        $model->active = isset($data['active']) ? $data['active'] : 0;
        $model->freesetup = isset($data['freesetup']) ? $data['freesetup'] : 0;
        $model->once_per_client = isset($data['once_per_client']) ? (bool)$data['once_per_client'] : 0;
        $model->recurring = isset($data['recurring']) ? (bool)$data['recurring'] : 0;
        $model->maxuses = isset($data['maxuses']) ? $data['maxuses'] : NULL;
        $model->start_at = (isset($data['start_at']) && !empty($data['start_at'])) ? date('c', strtotime($data['start_at'])) : NULL;
        $model->end_at = (isset($data['end_at']) && !empty($data['end_at'])) ? date('c', strtotime($data['end_at'])) : NULL;

        $model->products = json_encode($products);
        $model->periods = json_encode($periods);

        $model->updated_at = date('c');
        $model->created_at = date('c');
        $promoId = $this->di['db']->store($model);

        $this->di['logger']->info('Created new promo code %s', $model->code);
        return $promoId;
    }

    public function toPromoApiArray(\Model_Promo $model, $deep = false, $identity = null)
    {
        $products = json_decode($model->products, 1);
        $products = $this->getProductTitlesByIds($products);

        $result = $this->di['db']->toArray($model);
        $result['applies_to'] = $products;
        $result['products'] = json_decode($model->products, 1);
        $result['periods'] = json_decode($model->periods, 1);
        return $result;
    }

    public function updatePromo(\Model_Promo $model, array $data)
    {
        if(isset($data['code'])) {
            $model->code = $data['code'];
        }

        if(isset($data['type'])) {
            $model->type = $data['type'];
        }

        if(isset($data['value'])) {
            $model->value = $data['value'];
        }

        if(isset($data['active'])) {
            $model->active = (bool)$data['active'];
        }

        if(isset($data['freesetup'])) {
            $model->freesetup = (bool)$data['freesetup'];
        }

        if(isset($data['once_per_client'])) {
            $model->once_per_client = (bool)$data['once_per_client'];
        }

        if(isset($data['recurring'])) {
            $model->recurring = (bool)$data['recurring'];
        }

        if(isset($data['maxuses'])) {
            $model->maxuses = (int)$data['maxuses'];
        }

        if(isset($data['used'])) {
            $model->used = (int)$data['used'];
        }

        if(isset($data['start_at'])) {
            if(empty ($data['start_at'])) {
                $model->start_at = NULL;
            } else {
                $model->start_at = date('c', strtotime($data['start_at']));
            }
        }

        if(isset($data['end_at'])) {
            if(empty ($data['end_at'])) {
                $model->end_at = NULL;
            } else {
                $model->end_at = date('c', strtotime($data['end_at']));
            }
        }

        if(isset($data['products'])) {
            if(empty($data['products'])) {
                $model->products = NULL;
            } else {
                $model->products = json_encode($data['products']);
            }
        }

        if(isset($data['periods']) && is_array($data['periods'])) {
            $model->periods = json_encode($data['periods']);
        }

        $model->updated_at = date('c');
        $this->di['db']->store($model);

        $this->di['logger']->info('Update promo code %s', $model->code);
        return true;
    }

    public function deletePromo(\Model_Promo $model)
    {
        $sql = 'UPDATE client_order SET promo_id = NULL WHERE promo_id = :id';
        $this->di['db']->exec($sql, array(':id' => $model->id));

        $id = $model->code;
        $this->di['db']->trash($model);

        $this->di['logger']->info('Removed promo code %s', $id);
        return true;
    }

    public function getProductSearchQuery(array $data)
    {
        $sql         = 'SELECT m.*
                FROM product as m
                  LEFT JOIN product_payment as pp on m.product_payment_id = pp.id
                WHERE m.is_addon = 0';
        $type        = isset($data['type']) ? $data['type'] : NULL;
        $search      = isset($data['search']) ? $data['search'] : NULL;
        $status      = isset($data['status']) ? $data['status'] : NULL;
        $show_hidden = isset($data['show_hidden']) ? $data['show_hidden'] : TRUE;

        $params = array();
        if ($type) {
            $sql .= ' AND m.type = :type';
            $params[':type'] = $type;
        }

        if ($status) {
            $sql .= ' AND m.status = :status';
            $params[':status'] = $status;
        }

        if (!$show_hidden) {
            $sql .= ' AND m.hidden = 0';
        }

        if ($search) {
            $sql .= ' AND m.title LIKE %:search%';
            $params[':search'] = $search;
        }

        $sql .= ' ORDER BY m.priority ASC';

        return array($sql, $params);
    }

    public function toProductCategoryApiArray(\Model_ProductCategory $model, $deep = true)
    {
        $min_price = 0;
        $products = array();
        $pr = $this->getCategoryProducts($model);

        $type = null; //identified by first product in category
        foreach($pr as $p) {
            $pa = $this->toApiArray($p, false);
            if (reset($pr) == $p){
                $type = $p->type;
            }
            $products[] = $pa;
            $startingPrice = isset($pa['price_starting_from']) ? $pa['price_starting_from'] : 0;
            if($startingPrice < $min_price) {
                $min_price = $startingPrice;
            }
        }

        $data = $this->di['db']->toArray($model);
        $data['price_starting_from'] = $min_price;
        $data['icon_url'] = $model->icon_url;
        $data['type'] = $type;
        $data['products'] = $products;
        return $data;
    }

    /**
     * @param int $id
     * @return \Model_Product
     */
    public function findOneActiveById($id)
    {
        return $this->di['db']->findOne('Product', "id = ? and active = 1 and status = 'enabled' and is_addon = 0", array($id));
    }

    /**
     * @param string $slug
     * @return \Model_Product
     */
    public function findOneActiveBySlug($slug)
    {
        return $this->di['db']->findOne('Product', "slug = ? and active = 1 and status = 'enabled' and is_addon = 0", array($slug));
    }


    public function getProductCategorySearchQuery($data)
    {
        $sql = "SELECT m.*
                FROM product_category as m
                  LEFT JOIN product p on p.product_category_id = m.id
                WHERE p.status = 'enabled'
                  AND p.hidden = 0
                GROUP BY p.product_category_id
                ORDER BY p.priority ASC
        ";

        $params = array();
        return array($sql, $params);
    }

    public function getStartingFromPrice(\Model_Product $model)
    {
        if($model->product_payment_id) {
            $productPaymentModel = $this->di['db']->load('ProductPayment', $model->product_payment_id);
            return $this->getStartingPrice($productPaymentModel);
        }

        return NULL;
    }

    public function getUpgradablePairs(\Model_Product $model)
    {
        $ids = json_decode($model->upgrades, 1);
        $pids = $this->getProductTitlesByIds($ids);
        unset($pids[$model->id]);
        return $pids;
    }


    public function canUpgradeTo(\Model_Product $model, \Model_Product $new)
    {
        if($model->id == $new->id) {
            return false;
        }

        $pairs = $this->getUpgradablePairs($model);
        return array_key_exists($new->id, $pairs);
    }

    public function getProductTitlesByIds($ids)
    {
        if (empty ($ids)) {
            return array();
        }

        $slots = (count($ids)) ? implode(',', array_fill(0, count($ids), '?')) : ''; //same as RedBean genSlots() method

        $rows = $this->di['db']->getAll('SELECT id, title FROM product WHERE id in (' . $slots . ')', $ids);

        $result = array();
        foreach ($rows as $record) {
            $result[$record['id']] = $record['title'];
        }

        return $result;
    }

    public function getCategoryProducts(\Model_ProductCategory $model)
    {
        return $this->di['db']->find('Product', 'is_addon = 0 and status="enabled" and hidden = 0 and product_category_id = ?', array($model->id));

    }

    public function toProductPaymentApiArray(\Model_ProductPayment $model)
    {
        $periods = array();
        $periods['1W'] = array('price'=>$model->w_price, 'setup'=>$model->w_setup_price, 'enabled'=>$model->w_enabled);
        $periods['1M'] = array('price'=>$model->m_price, 'setup'=>$model->m_setup_price, 'enabled'=>$model->m_enabled);
        $periods['3M'] = array('price'=>$model->q_price, 'setup'=>$model->q_setup_price, 'enabled'=>$model->q_enabled);
        $periods['6M'] = array('price'=>$model->b_price, 'setup'=>$model->b_setup_price, 'enabled'=>$model->b_enabled);
        $periods['1Y'] = array('price'=>$model->a_price, 'setup'=>$model->a_setup_price, 'enabled'=>$model->a_enabled);
        $periods['2Y'] = array('price'=>$model->bia_price, 'setup'=>$model->bia_setup_price, 'enabled'=>$model->bia_enabled);
        $periods['3Y'] = array('price'=>$model->tria_price, 'setup'=>$model->tria_setup_price, 'enabled'=>$model->tria_enabled);

        return array(
            'type' =>   $model->type,
            \Model_ProductPayment::FREE      => array('price'=>0, 'setup'=>0),
            \Model_ProductPayment::ONCE      => array('price'=>$model->once_price, 'setup'=>$model->once_setup_price),
            \Model_ProductPayment::RECURRENT => $periods,
        );
    }

    public function getStartingPrice(\Model_ProductPayment $model)
    {
        if($model->type == 'free') {
            return 0;
        }

        if($model->type == 'once') {
            return $model->once_price;
        }

        if($model->type == 'recurrent') {
            $p = array();

            if($model->w_enabled) {
                $p[] = $model->w_price;
            }

            if($model->m_enabled) {
                $p[] = $model->m_price;
            }

            if($model->q_enabled) {
                $p[] = $model->q_price;

            }

            if($model->b_enabled) {
                $p[] = $model->b_price;
            }

            if($model->a_enabled) {
                $p[] = $model->a_price;
            }

            if($model->bia_enabled) {
                $p[] = $model->bia_price;
            }

            if($model->tria_enabled) {
                $p[] = $model->tria_price;
            }
            return min($p);
        }

        return NULL;
    }

    public function getSavePath($filename = null)
    {
        $path = $this->di['config']['path_data'].'/uploads/';
        if(null !== $filename) {
            $path .= md5($filename);
        }
        return $path;
    }

    public function removeOldFile($config)
    {
        if(isset($config['filename'])) {
            $f = $this->getSavePath($config['filename']);
            if($this->di['tools']->fileExists($f)) {
                $this->di['tools']->unlink($f);
                return true;
            }
        }
        return false;
    }

    public function getAddonById($id)
    {
        return $this->di['db']->findOne('Product', "type = 'custom' and is_addon = 1 and id = ?", array($id));
    }

    public function getProductDiscount(\Model_Product $product, \Model_Promo $promo, array $config = null)
    {
        if(!$this->isPromoLinkedToProduct($promo, $product)) {
            return 0;
        }

        // check if promo code aplies to specific period only
        if(isset($config['period'])) {
            $periods = $this->getPeriods($promo);
            if(!empty($periods) && !in_array($config['period'], $periods)) {
                return 0;
            }
        }

        $repo = $product->getTable();
        $price = $repo->getProductPrice($product, $config);

        if ($price == 0){
            return 0;
        }

        $discount = 0;

        switch ($promo->type) {
            case \Model_Promo::ABSOLUTE:
                $discount += $promo->value;
                break;

            case \Model_Promo::PERCENTAGE:
                $discount += round(($price * $promo->value / 100), 2);
                break;

            default:
                break;
        }

        return $discount;
    }

    private function getPeriods(\Model_Promo $model)
    {
        return $this->di['tools']->decodeJ($model->periods);
    }

    private function getProducts(\Model_Promo $model)
    {
        return $this->di['tools']->decodeJ($model->products);
    }

    public function isPromoLinkedToProduct(\Model_Promo $promo, \Model_Product $product)
    {
        if($product->is_addon) {
            return false;
        }

        $products = $this->getProducts($promo);
        if(empty($products)) {
            return true;
        }

        return in_array($product->id, $products);
    }

    public function isPromoLinkedToTld(\Model_Promo $promo, \Model_Tld $tld)
    {
        foreach ($promo->PromoItem as $item) {
            if ($item->tld_id == $tld->id) {
                return true;
            }
        }

        return false;
    }

    private function getAddonsApiArray(\Model_Product $model)
    {
        $addons = array();
        foreach($this->getProductAddons($model) as $addon) {
            $d = $this->toAddonArray($addon);
            $addons[] = $d;
        }
        return $addons;
    }

    public function getProductAddons(\Model_Product $model)
    {
        $ids = $this->di['tools']->decodeJ($model->addons);
        if (empty($ids)) {
            return array();
        }

        $slots = (count($ids)) ? implode(',', array_fill(0, count($ids), '?')) : ''; //same as RedBean genSlots() method
        array_unshift($ids, (int)$model->id); //adding product ID as first param in array

        return $this->di['db']->find('Product', 'type = "custom" and is_addon= 1 and id != ? and id IN (' . $slots . ')', $ids);
    }

    public function toAddonArray(\Model_Product $model, $deep = true)
    {
        $productPayment = $this->di['db']->load('ProductPayment', $model->product_payment_id);
        $pricing = $this->toProductPaymentApiArray($productPayment);

        $config = $this->di['tools']->decodeJ($model->config);

        return array(
            'id'            => $model->id,
            'type'          => $model->type,
            'title'         => $model->title,
            'slug'          => $model->slug,
            'description'   => $model->description,
            'unit'          => $model->unit,
            'plugin'        => $model->plugin,
            'allow_quantity_select' => $model->allow_quantity_select,
            'created_at'    => $model->created_at,
            'updated_at'    => $model->updated_at,
            'icon_url'      => $model->icon_url,

            'pricing'       => $pricing,
            'config'        => $config,
        );
    }

}
