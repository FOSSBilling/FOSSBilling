<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Product;

use FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    final public const CUSTOM = 'custom';
    final public const LICENSE = 'license';
    final public const ADDON = 'addon';
    final public const DOMAIN = 'domain';
    final public const DOWNLOADABLE = 'downloadable';
    final public const HOSTING = 'hosting';
    final public const MEMBERSHIP = 'membership';
    final public const VPS = 'vps';

    final public const SETUP_AFTER_ORDER = 'after_order';
    final public const SETUP_AFTER_PAYMENT = 'after_payment';
    final public const SETUP_MANUAL = 'manual';

    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * @return mixed[]
     */
    public function getPairs($data): array
    {
        $sql = 'SELECT id, title
                FROM product
                WHERE 1';

        $type = $data['type'] ?? null;
        $products_only = $data['products_only'] ?? true;
        $active_only = $data['active_only'] ?? true;

        $params = [];
        if ($products_only) {
            $sql .= ' AND is_addon = 0';
        }

        if ($active_only) {
            $sql .= ' AND active = 1';
        }

        if ($type) {
            $sql .= ' AND type = :type';
            $params['type'] = $type;
        }

        $rows = $this->di['db']->getAll($sql, $params);
        $result = [];
        foreach ($rows as $record) {
            $result[$record['id']] = $record['title'];
        }

        return $result;
    }

    public function toApiArray(\Model_Product $model, $deep = true, $identity = null): array
    {
        $repo = $model->getTable();
        $addons = $this->getAddonsApiArray($model);
        if (is_string($model->config) && json_validate($model->config)) {
            $config = json_decode($model->config, true);
        } else {
            $config = [];
        }
        $pricing = $repo->getPricingArray($model);
        $starting_from = $this->getStartingFromPrice($model);

        $result = [
            'id' => $model->id,
            'product_category_id' => $model->product_category_id,
            'type' => $model->type,
            'title' => $model->title,
            'form_id' => $model->form_id,
            'slug' => $model->slug,
            'description' => $model->description,
            'unit' => $model->unit,
            'priority' => $model->priority,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
            'pricing' => $pricing,
            'config' => $config,
            'addons' => $addons,

            'price_starting_from' => $starting_from,
            'icon_url' => $model->icon_url,

            // stock control
            'allow_quantity_select' => $model->allow_quantity_select,
            'quantity_in_stock' => $model->quantity_in_stock,
            'stock_control' => $model->stock_control,
        ];

        if ($identity instanceof \Model_Admin) {
            $result['upgrades'] = $this->getUpgradablePairs($model);
            $result['status'] = $model->status;
            $result['hidden'] = $model->hidden;
            $result['setup'] = $model->setup;
            if ($model->product_category_id) {
                $productCategory = $this->di['db']->load('ProductCategory', $model->product_category_id);
                $result['category'] = [
                    'id' => $productCategory->id,
                    'title' => $productCategory->title,
                ];
            }
        }

        return $result;
    }

    public function getTypes(): array
    {
        $data = [
            self::CUSTOM => 'Custom',
            self::LICENSE => 'License',
            self::DOWNLOADABLE => 'Downloadable',
            self::HOSTING => 'Hosting',
            self::DOMAIN => 'Domain',
        ];

        // attach service modules
        $extensionService = $this->di['mod_service']('extension');
        $list = $extensionService->getInstalledMods();
        foreach ($list as $mod) {
            if (str_starts_with($mod, 'service')) {
                $n = substr($mod, strlen('service'));
                $data[$n] = ucfirst($n);
            }
        }

        return $data;
    }

    public function getMainDomainProduct()
    {
        return $this->di['db']->findOne('Product', 'type = ?', [self::DOMAIN]);
    }

    public function getPaymentTypes()
    {
        return [
            \Model_ProductPayment::FREE => 'Free',
            \Model_ProductPayment::ONCE => 'One time',
            \Model_ProductPayment::RECURRENT => 'Recurrent',
        ];
    }

    public function createProduct($title, $type, $categoryId = null)
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_Product', 5);
        $sql = 'SELECT MAX(priority) FROM product LIMIT 1';
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

        $model->updated_at = date('Y-m-d H:i:s');
        $model->created_at = date('Y-m-d H:i:s');

        // try save with slug
        try {
            $productId = $this->di['db']->store($model);
        } catch (\Exception) {
            $model->slug = $this->di['tools']->slug($title) . '-' . random_int(1, 9999);
        }
        $productId = $this->di['db']->store($model);

        $this->di['logger']->info('Created new product #%s', $model->id);

        return (int) $productId;
    }

    public function updateProduct(\Model_Product $model, $data)
    {
        // pricing
        if (isset($data['pricing'])) {
            $types = $this->getPaymentTypes();

            if (!isset($data['pricing']['type']) || !array_key_exists($data['pricing']['type'], $types)) {
                throw new \FOSSBilling\InformationException('Pricing type is required');
            }
            $productPayment = $this->di['db']->getExistingModelById('ProductPayment', $model->product_payment_id, 'Product payment not found');

            $pricing = $data['pricing'];
            $productPayment->type = $data['pricing']['type'];

            if ($data['pricing']['type'] == \Model_ProductPayment::ONCE) {
                $productPayment->once_setup_price = (float) $data['pricing']['once']['setup'];
                $productPayment->once_price = (float) $data['pricing']['once']['price'];
            }

            if ($data['pricing']['type'] == \Model_ProductPayment::RECURRENT) {
                if (isset($pricing['recurrent']['1W'])) {
                    $productPayment->w_setup_price = $pricing['recurrent']['1W']['setup'];
                    $productPayment->w_price = $pricing['recurrent']['1W']['price'];
                    $productPayment->w_enabled = $pricing['recurrent']['1W']['enabled'];
                }

                if (isset($pricing['recurrent']['1M'])) {
                    $productPayment->m_setup_price = $pricing['recurrent']['1M']['setup'];
                    $productPayment->m_price = $pricing['recurrent']['1M']['price'];
                    $productPayment->m_enabled = $pricing['recurrent']['1M']['enabled'];
                }

                if (isset($pricing['recurrent']['3M'])) {
                    $productPayment->q_setup_price = $pricing['recurrent']['3M']['setup'];
                    $productPayment->q_price = $pricing['recurrent']['3M']['price'];
                    $productPayment->q_enabled = $pricing['recurrent']['3M']['enabled'];
                }

                if (isset($pricing['recurrent']['6M'])) {
                    $productPayment->b_setup_price = $pricing['recurrent']['6M']['setup'];
                    $productPayment->b_price = $pricing['recurrent']['6M']['price'];
                    $productPayment->b_enabled = $pricing['recurrent']['6M']['enabled'];
                }

                if (isset($pricing['recurrent']['1Y'])) {
                    $productPayment->a_setup_price = $pricing['recurrent']['1Y']['setup'];
                    $productPayment->a_price = $pricing['recurrent']['1Y']['price'];
                    $productPayment->a_enabled = $pricing['recurrent']['1Y']['enabled'];
                }

                if (isset($pricing['recurrent']['2Y'])) {
                    $productPayment->bia_setup_price = $pricing['recurrent']['2Y']['setup'];
                    $productPayment->bia_price = $pricing['recurrent']['2Y']['price'];
                    $productPayment->bia_enabled = $pricing['recurrent']['2Y']['enabled'];
                }

                if (isset($pricing['recurrent']['3Y'])) {
                    $productPayment->tria_setup_price = $pricing['recurrent']['3Y']['setup'];
                    $productPayment->tria_price = $pricing['recurrent']['3Y']['price'];
                    $productPayment->tria_enabled = $pricing['recurrent']['3Y']['enabled'];
                }
            }

            $this->di['db']->store($productPayment);
        }

        if (isset($data['config']) && is_array($data['config'])) {
            if (is_string($model->config) && json_validate($model->config)) {
                $current = json_decode($model->config, true);
            } else {
                $current = [];
            }
            $c = array_merge($current, $data['config']);
            $model->config = json_encode($c);
        }

        $form_id = $data['form_id'] ?? $model->form_id;

        $model->product_category_id = $data['product_category_id'] ?? $model->product_category_id;
        $model->form_id = empty($form_id) ? null : $form_id;
        $model->icon_url = $data['icon_url'] ?? $model->icon_url;
        $model->status = $data['status'] ?? $model->status;
        $model->hidden = (int) ($data['hidden'] ?? $model->hidden);
        $model->slug = $data['slug'] ?? $model->slug;
        $model->setup = $data['setup'] ?? $model->setup;
        // remove empty value in data['upgrades];
        if (is_array($data['upgrades'] ?? null)) {
            $upgrades = array_values(array_filter($data['upgrades']));
            if (empty($upgrades)) {
                $model->upgrades = null;
            } else {
                $model->upgrades = json_encode($upgrades);
            }
        }
        if (is_array($data['addons'] ?? null)) {
            $addons = array_values(array_filter($data['addons']));
            if (is_null($addons)) {
                $model->addons = null;
            } else {
                $model->addons = json_encode($addons);
            }
        }

        $model->title = $data['title'] ?? $model->title;
        $model->stock_control = $data['stock_control'] ?? $model->stock_control;
        $model->allow_quantity_select = $data['allow_quantity_select'] ?? $model->allow_quantity_select;
        $model->quantity_in_stock = $data['quantity_in_stock'] ?? $model->quantity_in_stock;
        $model->description = $data['description'] ?? $model->description;
        $model->plugin = $data['plugin'] ?? $model->plugin;
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        $this->di['logger']->info('Updated product #%s configuration', $model->id);

        return true;
    }

    public function updatePriority($data)
    {
        foreach ($data['priority'] as $id => $p) {
            $model = $this->di['db']->load('Product', $id);
            if ($model instanceof \Model_Product) {
                $model->priority = $p;
                $model->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($model);
            }
        }

        $this->di['logger']->info('Changed product priorities');

        return true;
    }

    public function updateConfig(\Model_Product $model, $data)
    {
        /* add new config value */
        if ($model->config) {
            $config = json_decode($model->config, true);
        } else {
            $config = [];
        }

        if (isset($data['config']) && is_array($data['config'])) {
            $config = array_intersect_key((array) $config, $data['config']);
            foreach ($data['config'] as $key => $val) {
                $config[$key] = $val;
                if (isset($config[$key]) && empty($val) && !is_numeric($val)) {
                    unset($config[$key]);
                }
            }
        }

        if (
            isset($data['new_config_name'])
            && isset($data['new_config_value'])
            && !empty($data['new_config_name'])
            && !empty($data['new_config_value'])
        ) {
            $config[$data['new_config_name']] = $data['new_config_value'];
        }

        $model->config = json_encode($config);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated product #%s configuration', $model->id);

        return true;
    }

    /**
     * @return mixed[]
     */
    public function getAddons(): array
    {
        $sql = 'SELECT id, title
                FROM product
                WHERE is_addon =1
                ORDER by id asc';
        $addons = $this->di['db']->getAll($sql);

        $result = [];
        foreach ($addons as $addon) {
            $result[$addon['id']] = $addon['title'];
        }

        return $result;
    }

    public function createAddon($title, $description = null, $setup = null, $status = null, $iconUrl = null)
    {
        $modelPayment = $this->di['db']->dispense('ProductPayment');
        $modelPayment->type = \Model_ProductPayment::FREE;
        $paymentId = $this->di['db']->store($modelPayment);

        $model = $this->di['db']->dispense('Product');
        $model->product_payment_id = $paymentId;
        $model->product_category_id = null;
        $model->status = $status ?? \Model_Product::STATUS_DISABLED;
        $model->title = $title;
        $model->slug = $this->di['tools']->slug($title);
        $model->type = self::CUSTOM;
        $model->setup = $setup ?? self::SETUP_AFTER_PAYMENT;
        $model->is_addon = 1;

        $model->icon_url = $iconUrl;
        $model->description = $description;

        $model->updated_at = date('Y-m-d H:i:s');
        $model->created_at = date('Y-m-d H:i:s');

        // try save with slug
        try {
            $productId = $this->di['db']->store($model);
        } catch (\Exception) {
            $model->slug = $this->di['tools']->slug($title) . '-' . random_int(1, 9999);
            $productId = $this->di['db']->store($model);
        }

        $this->di['logger']->info('Created new addon #%s', $productId);

        return $productId;
    }

    public function deleteProduct(\Model_Product $product)
    {
        $orderService = $this->di['mod_service']('order');
        if ($orderService->productHasOrders($product)) {
            throw new \FOSSBilling\InformationException('Cannot remove product which has active orders.');
        }
        $id = $product->id;
        $this->di['db']->trash($product);
        $this->di['logger']->info('Deleted product #%s', $id);

        return true;
    }

    /**
     * @return mixed[]
     */
    public function getProductCategoryPairs(): array
    {
        $sql = 'SELECT id, title
                FROM product_category';

        $rows = $this->di['db']->getAll($sql);
        $result = [];
        foreach ($rows as $record) {
            $result[$record['id']] = $record['title'];
        }

        return $result;
    }

    public function updateCategory(\Model_ProductCategory $productCategory, $title = null, $description = null, $icon_url = null)
    {
        $productCategory->title = $title;
        $productCategory->icon_url = $icon_url;
        $productCategory->description = $description;

        $productCategory->updated_at = date('Y-m-d H:i:s');
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
        $model->updated_at = date('Y-m-d H:i:s');
        $model->created_at = date('Y-m-d H:i:s');
        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Created new product category #%s', $id);

        return $id;
    }

    public function removeProductCategory(\Model_ProductCategory $category)
    {
        $model = $this->di['db']->findOne('Product', 'product_category_id = :category_id', [':category_id' => $category->id]);
        if ($model instanceof \Model_Product) {
            throw new \FOSSBilling\InformationException('Cannot remove product category with products');
        }
        $id = $category->id;
        $this->di['db']->trash($category);

        $this->di['logger']->info('Deleted product category #%s', $id);

        return true;
    }

    public function getProductSearchQuery(array $data)
    {
        $sql = 'SELECT m.*
                FROM product as m
                  LEFT JOIN product_payment as pp on m.product_payment_id = pp.id
                WHERE m.is_addon = 0';

        $type = $data['type'] ?? null;
        $search = $data['search'] ?? null;
        $status = $data['status'] ?? null;
        $show_hidden = $data['show_hidden'] ?? true;

        $params = [];
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
            $sql .= ' AND m.title LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY m.priority ASC';

        return [$sql, $params];
    }

    public function toProductCategoryApiArray(\Model_ProductCategory $model, $deep = true)
    {
        $min_price = 0;
        $products = [];
        $pr = $this->getCategoryProducts($model);

        $type = null; // identified by first product in category
        foreach ($pr as $p) {
            $pa = $this->toApiArray($p, false);
            if (reset($pr) == $p) {
                $type = $p->type;
            }
            $products[] = $pa;
            $startingPrice = $pa['price_starting_from'] ?? 0;

            if ($min_price == 0) {
                $min_price = $startingPrice;
            } elseif ($startingPrice < $min_price) {
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
     *
     * @return \Model_Product
     */
    public function findOneActiveById($id)
    {
        return $this->di['db']->findOne('Product', "id = ? and active = 1 and status = 'enabled' and is_addon = 0", [$id]);
    }

    /**
     * @param string $slug
     *
     * @return \Model_Product
     */
    public function findOneActiveBySlug($slug)
    {
        return $this->di['db']->findOne('Product', "slug = ? and active = 1 and status = 'enabled' and is_addon = 0", [$slug]);
    }

    public function getProductCategorySearchQuery($data)
    {
        $sql = 'SELECT m.id,
                       m.title,
                       m.description,
                       m.icon_url,
                       m.created_at,
                       m.updated_at,
                       Max(p.priority) AS MaxPrio
                FROM   product_category AS m
                       LEFT JOIN product p
                              ON p.product_category_id = m.id
                WHERE  p.status = \'enabled\'
                       AND p.hidden = 0
                GROUP  BY m.id,
                          m.title,
                          m.description,
                          m.icon_url,
                          m.created_at,
                          m.updated_at
                ORDER  BY MaxPrio ASC;';

        $params = [];

        return [$sql, $params];
    }

    public function getStartingFromPrice(\Model_Product $model)
    {
        if ($model->type == self::DOMAIN) {
            return $this->getStartingDomainPrice();
        }

        if ($model->product_payment_id) {
            $productPaymentModel = $this->di['db']->load('ProductPayment', $model->product_payment_id);

            return $this->getStartingPrice($productPaymentModel);
        }

        return null;
    }

    /**
     * @return mixed[]
     */
    public function getUpgradablePairs(\Model_Product $model): array
    {
        if (is_null($model->upgrades)) {
            $model->upgrades = '';
        }
        $ids = json_decode($model->upgrades, 1);
        $pids = $this->getProductTitlesByIds($ids);
        unset($pids[$model->id]);

        return $pids;
    }

    public function canUpgradeTo(\Model_Product $model, \Model_Product $new)
    {
        if ($model->id == $new->id) {
            return false;
        }

        $pairs = $this->getUpgradablePairs($model);

        return array_key_exists($new->id, $pairs);
    }

    /**
     * @return mixed[]
     */
    public function getProductTitlesByIds($ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $slots = (is_countable($ids) ? count($ids) : 0) ? implode(',', array_fill(0, is_countable($ids) ? count($ids) : 0, '?')) : ''; // same as RedBean genSlots() method

        $rows = $this->di['db']->getAll('SELECT id, title FROM product WHERE id in (' . $slots . ')', $ids);

        $result = [];
        foreach ($rows as $record) {
            $result[$record['id']] = $record['title'];
        }

        return $result;
    }

    public function getCategoryProducts(\Model_ProductCategory $model)
    {
        return $this->di['db']->find('Product', 'is_addon = 0 and status="enabled" and hidden = 0 and product_category_id = ?', [$model->id]);
    }

    public function toProductPaymentApiArray(\Model_ProductPayment $model)
    {
        $periods = [];
        $periods['1W'] = ['price' => $model->w_price, 'setup' => $model->w_setup_price, 'enabled' => $model->w_enabled];
        $periods['1M'] = ['price' => $model->m_price, 'setup' => $model->m_setup_price, 'enabled' => $model->m_enabled];
        $periods['3M'] = ['price' => $model->q_price, 'setup' => $model->q_setup_price, 'enabled' => $model->q_enabled];
        $periods['6M'] = ['price' => $model->b_price, 'setup' => $model->b_setup_price, 'enabled' => $model->b_enabled];
        $periods['1Y'] = ['price' => $model->a_price, 'setup' => $model->a_setup_price, 'enabled' => $model->a_enabled];
        $periods['2Y'] = ['price' => $model->bia_price, 'setup' => $model->bia_setup_price, 'enabled' => $model->bia_enabled];
        $periods['3Y'] = ['price' => $model->tria_price, 'setup' => $model->tria_setup_price, 'enabled' => $model->tria_enabled];

        return [
            'type' => $model->type,
            \Model_ProductPayment::FREE => ['price' => 0, 'setup' => 0],
            \Model_ProductPayment::ONCE => ['price' => $model->once_price, 'setup' => $model->once_setup_price],
            \Model_ProductPayment::RECURRENT => $periods,
        ];
    }

    public function getStartingDomainPrice()
    {
        $sql = 'SELECT min(price_registration)
                FROM tld
                WHERE active = 1';

        return (float) $this->di['db']->getCell($sql);
    }

    public function getStartingPrice(\Model_ProductPayment $model)
    {
        if ($model->type == 'free') {
            return 0;
        }

        if ($model->type == 'once') {
            return $model->once_price;
        }

        if ($model->type == 'recurrent') {
            $p = [];

            if ($model->w_enabled) {
                $p[] = $model->w_price;
            }

            if ($model->m_enabled) {
                $p[] = $model->m_price;
            }

            if ($model->q_enabled) {
                $p[] = $model->q_price;
            }

            if ($model->b_enabled) {
                $p[] = $model->b_price;
            }

            if ($model->a_enabled) {
                $p[] = $model->a_price;
            }

            if ($model->bia_enabled) {
                $p[] = $model->bia_price;
            }

            if ($model->tria_enabled) {
                $p[] = $model->tria_price;
            }

            if ($p) {
                return min($p);
            } else {
                return null;
            }
        }

        return null;
    }

    public function getSavePath($filename = null)
    {
        $path = PATH_DATA . '/uploads/';
        if ($filename !== null) {
            $path .= md5($filename);
        }

        return $path;
    }

    public function removeOldFile($config)
    {
        if (isset($config['filename'])) {
            $f = $this->getSavePath($config['filename']);
            if (file_exists($f)) {
                unlink($f);

                return true;
            }
        }

        return false;
    }

    public function getAddonById($id)
    {
        return $this->di['db']->findOne('Product', "type = 'custom' and is_addon = 1 and id = ?", [$id]);
    }

    private function getPeriods(\Model_Promo $model): array
    {
        if (is_string($model->periods) && json_validate($model->periods)) {
            return json_decode($model->periods, true);
        }

        return [];
    }

    private function getProducts(\Model_Promo $model): array
    {
        if (is_string($model->products) && json_validate($model->products)) {
            return json_decode($model->products, true);
        }

        return [];
    }

    /**
     * @return mixed[]
     */
    private function getAddonsApiArray(\Model_Product $model): array
    {
        $addons = [];
        foreach ($this->getProductAddons($model) as $addon) {
            $d = $this->toAddonArray($addon);
            $addons[] = $d;
        }

        return $addons;
    }

    public function getProductAddons(\Model_Product $model)
    {
        if (is_string($model->addons) && json_validate($model->addons)) {
            $ids = json_decode($model->addons, true);
        } else {
            $ids = [];
        }

        if ($ids === []) {
            return [];
        }

        $slots = (is_countable($ids) ? count($ids) : 0) ? implode(',', array_fill(0, is_countable($ids) ? count($ids) : 0, '?')) : ''; // same as RedBean genSlots() method
        array_unshift($ids, (int) $model->id); // adding product ID as first param in array

        return $this->di['db']->find('Product', 'type = "custom" and is_addon= 1 and id != ? and id IN (' . $slots . ')', $ids);
    }

    public function toAddonArray(\Model_Product $model, $deep = true)
    {
        $productPayment = $this->di['db']->load('ProductPayment', $model->product_payment_id);
        $pricing = $this->toProductPaymentApiArray($productPayment);

        if (is_string($model->config) && json_validate($model->config)) {
            $config = json_decode($model->config, true);
        } else {
            $config = [];
        }

        return [
            'id' => $model->id,
            'type' => $model->type,
            'title' => $model->title,
            'slug' => $model->slug,
            'description' => $model->description,
            'unit' => $model->unit,
            'plugin' => $model->plugin,
            'allow_quantity_select' => $model->allow_quantity_select,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
            'icon_url' => $model->icon_url,

            'pricing' => $pricing,
            'config' => $config,
        ];
    }

    /*
     * Product Promotion Functions
     */
    public function getPromoSearchQuery($data)
    {
        $sql = 'SELECT *
                FROM promo
                WHERE 1';

        $search = $data['search'] ?? null;
        $id = $data['id'] ?? null;
        $status = $data['status'] ?? null;

        $params = [];
        if ($id) {
            $sql .= ' AND id = :id';
            $params['id'] = $id;
        }

        if ($search) {
            $sql .= ' AND code like :search';
            $params['search'] = '%' . $search . '%';
        }

        switch ($status) {
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

        return [$sql, $params];
    }

    public function createPromo($code, $type, $value, $products, $periods, $clientGroups, $data)
    {
        if ($this->di['db']->findOne('Promo', 'code = :code', [':code' => $code])) {
            throw new \FOSSBilling\InformationException('This promotion code already exists.');
        }

        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_Promo', 2);

        $model = $this->di['db']->dispense('Promo');
        $model->code = $code;
        $model->type = $type;
        $model->value = $value;
        $model->active = $data['active'] ?? 0;
        $model->freesetup = $data['freesetup'] ?? 0;
        $model->once_per_client = (bool) ($data['once_per_client'] ?? 0);
        $model->recurring = (bool) ($data['recurring'] ?? 0);
        $model->maxuses = (int) $data['maxuses'] ?? null;
        $model->start_at = !empty($data['start_at']) ? date('Y-m-d H:i:s', strtotime($data['start_at'])) : null;
        $model->end_at = !empty($data['end_at']) ? date('Y-m-d H:i:s', strtotime($data['end_at'])) : null;
        $model->products = json_encode($products);
        $model->periods = json_encode($periods);
        $model->client_groups = json_encode($clientGroups);
        $model->updated_at = date('Y-m-d H:i:s');
        $model->created_at = date('Y-m-d H:i:s');
        $promoId = $this->di['db']->store($model);

        $this->di['logger']->info('Created new promotion code %s', $model->code);

        return $promoId;
    }

    public function toPromoApiArray(\Model_Promo $model, $deep = false, $identity = null)
    {
        $products = $model->products ? $this->getProductTitlesByIds(json_decode($model->products, 1)) : null;
        $clientGroups = $model->client_groups ? $this->di['tools']->getPairsForTableByIds('client_group', json_decode($model->client_groups, 1)) : null;

        $result = $this->di['db']->toArray($model);
        $result['applies_to'] = $products;
        $result['cgroups'] = $clientGroups;
        $result['products'] = $model->products ? json_decode($model->products, 1) : null;
        $result['periods'] = $model->periods ? json_decode($model->periods, 1) : null;
        $result['client_groups'] = $model->client_groups ? json_decode($model->client_groups, 1) : null;

        return $result;
    }

    public function updatePromo(\Model_Promo $model, array $data = [])
    {
        $model->code = $data['code'] ?? $model->code;
        $model->type = $data['type'] ?? $model->type;
        $model->value = $data['value'] ?? $model->value;
        $model->active = $data['active'] ?? $model->active;
        $model->freesetup = $data['freesetup'] ?? $model->freesetup;
        $model->once_per_client = $data['once_per_client'] ?? $model->once_per_client;
        $model->recurring = $data['recurring'] ?? $model->recurring;
        $model->used = $data['used'] ?? $model->used;
        $model->start_at = !empty($data['start_at']) ? date('Y-m-d H:i:s', strtotime($data['start_at'])) : null;
        $model->end_at = !empty($data['end_at']) ? date('Y-m-d H:i:s', strtotime($data['end_at'])) : null;
        $model->maxuses = (int) $data['maxuses'] ?? $model->maxuses;

        if (!is_array($data['products'] ?? null)) {
            $model->products = null;
        } else {
            $products = array_values(array_filter($data['products'] ?? null));
            if (empty($products)) {
                $model->products = null;
            } else {
                $model->products = json_encode($products);
            }
        }
        if (!is_array($data['client_groups'] ?? null)) {
            $model->client_groups = null;
        } else {
            $client_groups = array_values(array_filter($data['client_groups'] ?? null));
            if (empty($client_groups)) {
                $model->client_groups = null;
            } else {
                $model->client_groups = json_encode($client_groups);
            }
        }

        if (!is_array($data['periods'] ?? null)) {
            $model->periods = null;
        } else {
            $periods = array_values(array_filter($data['periods'] ?? null));
            if (empty($periods)) {
                $model->periods = null;
            } else {
                $model->periods = json_encode($periods);
            }
        }

        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Update promo code %s', $model->code);

        return true;
    }

    public function deletePromo(\Model_Promo $model)
    {
        $sql = 'UPDATE client_order SET promo_id = NULL WHERE promo_id = :id';

        $this->di['db']->exec($sql, [':id' => $model->id]);
        $this->di['db']->trash($model);

        $this->di['logger']->info('Removed promo code %s', $model->code);

        return true;
    }

    public function isPromoLinkedToProduct(\Model_Promo $promo, \Model_Product $product)
    {
        if ($product->is_addon) {
            return false;
        }

        $products = $this->getProducts($promo);
        if (empty($products)) {
            return true;
        }

        return in_array($product->id, $products);
    }

    public function getProductDiscount(\Model_Product $product, \Model_Promo $promo, array $config = null)
    {
        if (!$this->isPromoLinkedToProduct($promo, $product)) {
            return 0;
        }

        // check if promo code applies to specific period only
        if (isset($config['period'])) {
            $periods = $this->getPeriods($promo);
            if (!empty($periods) && !in_array($config['period'], $periods)) {
                return 0;
            }
        }

        $repo = $product->getTable();
        $price = $repo->getProductPrice($product, $config);

        if ($price == 0) {
            return 0;
        }

        $discount = 0;
        $quantity = 1;

        switch ($promo->type) {
            case \Model_Promo::ABSOLUTE:
                $discount += $promo->value;

                break;

            case \Model_Promo::PERCENTAGE:
                if (isset($config['quantity']) && is_numeric($config['quantity'])) {
                    $quantity = $config['quantity'];
                }

                $discount += round($price * $quantity * $promo->value / 100, 2);

                break;

            default:
                break;
        }

        return $discount;
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

    // Function to get all orders for a product
    public function getOrdersForProduct(\Model_Product $product)
    {
        // return type Model_ClientOrder that have product_id = $product->id
        $sql = 'SELECT * FROM client_order WHERE product_id = :product_id';

        return $this->di['db']->getAll($sql, [':product_id' => $product->id], '\Model_ClientOrder');
    }
}
