<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Custompages\Api;

class Guest extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Get paginated list of custom pages.
     *
     * All custom pages are publicly accessible, so no visibility filtering applies.
     *
     * @optional string $sort - sort column: 'id', 'title', 'slug', 'created_at' or 'updated_at'
     * @optional string $direction - sort direction: 'ASC' or 'DESC'
     *
     * @return array
     */
    public function get_list($data)
    {
        return $this->getService()->searchPages($data);
    }

    /**
     * Get a single custom page by ID or slug.
     *
     * @optional int $id - custom page ID
     * @optional string $slug - custom page slug
     *
     * @return array
     *
     * @throws \FOSSBilling\InformationException when the page is missing or not found
     */
    public function get($data)
    {
        if (!isset($data['id']) && !isset($data['slug'])) {
            throw new \FOSSBilling\InformationException('Custom page ID or slug is missing');
        }

        $page = isset($data['slug'])
            ? $this->getService()->getPage((string) $data['slug'], 'slug')
            : $this->getService()->getPage((int) $data['id']);

        if ($page === null) {
            throw new \FOSSBilling\InformationException('Custom page not found');
        }

        return $page;
    }
}
