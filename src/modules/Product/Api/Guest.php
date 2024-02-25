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
 * Products management api.
 */

namespace Box\Mod\Product\Api;

class Guest extends \Api_Abstract
{
    /**
     * Get paginated list of products.
     *
     * @optional bool $show_hidden - also get hidden products. Default false
     *
     * @return array
     */
    public function get_list($data)
    {
        $data['status'] = 'enabled';
        if (!isset($data['show_hidden'])) {
            $data['show_hidden'] = false;
        }

        [$sql, $params] = $this->getService()->getProductSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $model = $this->di['db']->getExistingModelById('Product', $item['id'], 'Post not found');
            $pager['list'][$key] = $this->getService()->toApiArray($model, false, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Get products pairs. Product id -> title values.
     *
     * @return array
     */
    public function get_pairs($data)
    {
        $data['products_only'] = true;
        $data['active_only'] = true;
        $service = $this->getService();

        return $service->getPairs($data);
    }

    /**
     * Get product by ID.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function get($data)
    {
        if (!isset($data['id']) && !isset($data['slug'])) {
            throw new \FOSSBilling\Exception('Product ID or slug is missing');
        }

        $id = $data['id'] ?? null;
        $slug = $data['slug'] ?? null;

        $service = $this->getService();
        if ($id) {
            $model = $service->findOneActiveById($id);
        } else {
            $model = $service->findOneActiveBySlug($slug);
        }

        if (!$model instanceof \Model_Product) {
            throw new \FOSSBilling\Exception('Product not found');
        }

        return $service->toApiArray($model);
    }

    /**
     * Get paginated list of product categories.
     *
     * @return array
     */
    public function category_get_list($data)
    {
        $data['status'] = 'enabled';
        $service = $this->getService();
        [$sql, $params] = $service->getProductCategorySearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getAdvancedResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $category = $this->di['db']->getExistingModelById('ProductCategory', $item['id'], 'Product category not found');
            $pager['list'][$key] = $this->getService()->toProductCategoryApiArray($category, true, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Get pairs of product categories.
     *
     * @return array
     */
    public function category_get_pairs($data)
    {
        return $this->getService()->getProductCategoryPairs($data);
    }

    /**
     * Return slider data for product types.
     * Products are grouped by type. You can pass parameter to select product type for slider
     * Product configuration must have slider_%s keys.
     *
     * @optional string $type - product type for slider - default = hosting
     * @optional string $format - return format. Default is array . You can choose json format, to directly inject to javascript
     */
    public function get_slider($data)
    {
        $format = $data['format'] ?? null;
        $type = $data['type'] ?? 'hosting';

        $products = $this->di['db']->find('Product', 'type = :type', [':type' => $type]);
        if ((is_countable($products) ? count($products) : 0) <= 0) {
            return [];
        }

        $slider = [];
        foreach ($products as $productModel) {
            $product = $this->getService()->toApiArray($productModel);
            $pc = $product['config'];
            $s = [
                'product_id' => $product['id'],
                'slug' => $product['slug'],
                'title' => $product['title'],
                'pricing' => $product['pricing'],
            ];
            foreach ($pc as $k => $v) {
                if (str_contains($k, 'slider_')) {
                    $s[substr($k, strlen('slider_'))] = $v;
                }
            }
            $slider[] = $s;
        }
        if ($format == 'json') {
            return json_encode($slider);
        }

        return $slider;
    }
}
