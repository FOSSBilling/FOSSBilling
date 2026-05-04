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
            $pager['list'][$key] = $this->getService()->toApiArray($model, false);
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
        $pager = $this->di['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));

        foreach ($pager['list'] as $key => $item) {
            $category = $this->di['db']->getExistingModelById('ProductCategory', $item['id'], 'Product category not found');
            $pager['list'][$key] = $this->getService()->toProductCategoryApiArray($category);
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
}