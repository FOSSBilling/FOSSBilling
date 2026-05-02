<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
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

use FOSSBilling\PaginationOptions;

class Guest extends \Api_Abstract
{
    /**
     * Get paginated list of products.
     *
     * @return array
     */
    public function get_list($data)
    {
        $data['status'] = 'enabled';
        $data['show_hidden'] = false;

        [$sql, $params] = $this->getService()->getProductSearchQuery($data);
        $pager = $this->di['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));

        foreach ($pager['list'] as $key => $item) {
            $model = $this->di['db']->getExistingModelById('Product', $item['id'], 'Post not found');
            $pager['list'][$key] = $this->toGuestApiArray($model);
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
            $model = $service->findOneActiveById((int) $id);
        } else {
            $model = $service->findOneActiveBySlug($slug);
        }

        if (!$model instanceof \Model_Product) {
            throw new \FOSSBilling\Exception('Product not found');
        }

        return $this->toGuestApiArray($model);
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
        $pager = $this->di['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));

        foreach ($pager['list'] as $key => $item) {
            $category = $this->di['db']->getExistingModelById('ProductCategory', $item['id'], 'Product category not found');
            $pager['list'][$key] = $this->toGuestCategoryApiArray($category);
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
     * 
     * @TODO: Redo this and make use of it
     */
    public function get_slider($data)
    {
        $format = $data['format'] ?? null;
        $type = $data['type'] ?? 'hosting';

        $products = $this->di['db']->find('Product', "type = :type AND active = 1 AND status = 'enabled' AND hidden = 0 AND is_addon = 0", [':type' => $type]);
        if (\FOSSBilling\Tools::safeCount($products) <= 0) {
            return [];
        }

        $slider = [];
        foreach ($products as $productModel) {
            $product = $this->toGuestApiArray($productModel);
            $pc = $this->getPublicConfig($productModel);
            $s = [
                'product_id' => $product['id'],
                'slug' => $product['slug'],
                'title' => $product['title'],
                'pricing' => $product['pricing'],
            ];
            foreach ($pc as $k => $v) {
                if (str_contains((string) $k, 'slider_')) {
                    $s[substr((string) $k, strlen('slider_'))] = $v;
                }
            }
            $slider[] = $s;
        }
        if ($format == 'json') {
            return json_encode($slider);
        }

        return $slider;
    }

    private function toGuestApiArray(\Model_Product $model): array
    {
        $product = $this->getService()->toApiArray($model, false);

        return $this->toGuestProductArray($product, $this->getPublicConfig($model));
    }

    private function toGuestCategoryApiArray(\Model_ProductCategory $model): array
    {
        $category = $this->getService()->toProductCategoryApiArray($model, true);

        foreach ($category['products'] as $key => $product) {
            $category['products'][$key] = $this->toGuestProductArray($product, $this->getPublicConfigFromArray($product['config'] ?? []));
        }

        return $category;
    }

    private function toGuestProductArray(array $product, array $config): array
    {
        return [
            'id' => $product['id'],
            'product_category_id' => $product['product_category_id'],
            'type' => $product['type'],
            'title' => $product['title'],
            'slug' => $product['slug'],
            'description' => $product['description'],
            'unit' => $product['unit'],
            'priority' => $product['priority'],
            'pricing' => $this->getPublicPricing($product['pricing']),
            'config' => $config,
            'price_starting_from' => $product['price_starting_from'],
            'icon_url' => $product['icon_url'],
            'allow_quantity_select' => $product['allow_quantity_select'],
        ];
    }


    private function getPublicConfig(\Model_Product $model): array
    {
        $config = json_decode($model->config ?? '', true) ?? [];

        return $this->getPublicConfigFromArray($config);
    }

    private function getPublicConfigFromArray(array $config): array
    {
        $publicConfigKeys = [
            'allow_domain_register',
            'allow_domain_transfer',
            'allow_domain_own',
        ];

        return array_intersect_key($config, array_flip($publicConfigKeys));
    }

    private function getPublicPricing(array $pricing): array
    {
        foreach ($pricing as $key => $value) {
            if ($key === 'registrar') {
                unset($pricing[$key]);

                continue;
            }

            if (is_array($value)) {
                $pricing[$key] = $this->getPublicPricing($value);
            }
        }

        return $pricing;
    }
}
