<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\News\Api;

use Box\Mod\News\Entity\Post;
use FOSSBilling\PaginationOptions;

class Guest extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Get paginated list of active news items.
     *
     * @param array $data Filtering and pagination parameters
     *
     * @return array Paginated list of news items
     */
    public function get_list(array $data): array
    {
        $data['status'] = Post::STATUS_ACTIVE;

        /** @var \Box\Mod\News\Repository\PostRepository $repo */
        $repo = $this->getService()->getPostRepository();

        // Repository method returns a QueryBuilder with filters applied
        $qb = $repo->getSearchQueryBuilder($data);

        return $this->getDi()['pager']->paginateDoctrineQuery($qb, PaginationOptions::fromArray($data));
    }

    /**
     * Get a single news item by ID or slug.
     *
     * @param array $data ['id' => int|null, 'slug' => string|null]
     *
     * @throws \FOSSBilling\InformationException if ID/slug is missing or news item not found
     */
    public function get(array $data): array
    {
        $id = $data['id'] ?? null;
        $slug = $data['slug'] ?? null;

        if (!$id && !$slug) {
            throw new \FOSSBilling\InformationException('ID or slug is required.');
        }

        /** @var \Box\Mod\News\Repository\PostRepository $repo */
        $repo = $this->getService()->getPostRepository();

        $post = null;
        if ($id) {
            $post = $repo->findOneActiveById((int) $id);
        } elseif ($slug) {
            $post = $repo->findOneActiveBySlug($slug);
        }

        if (!$post || $post->getStatus() !== Post::STATUS_ACTIVE) {
            throw new \FOSSBilling\InformationException('News item not found.');
        }

        /**@todo Doctrine: Replace with actual Admin entity once it's migrated to Doctrine. */
        $admin = $repo->findAdminSummary((int) $post->getAdminId()) ?? [];

        $post->setAdminData($admin);

        return $post->toApiArray();
    }
}
