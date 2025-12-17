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

namespace Box\Mod\News\Api;

use Box\Mod\News\Entity\Post;

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of news items (any status).
     *
     * @param array $data Filtering and pagination parameters
     *
     * @return array Paginated list of news items
     */
    public function get_list(array $data): array
    {
        /** @var \Box\Mod\News\Repository\PostRepository $repo */
        $repo = $this->getService()->getPostRepository();

        // Repository method returns a QueryBuilder with filters applied
        $qb = $repo->getSearchQueryBuilder($data);

        return $this->di['pager']->paginateDoctrineQuery($qb);
    }

    /**
     * Get a single news item by ID or slug.
     *
     * @param array $data ['id' => int|null, 'slug' => string|null]
     *
     * @throws \FOSSBilling\Exception if ID/slug is missing or news item not found
     */
    public function get(array $data): array
    {
        $id = $data['id'] ?? null;
        $slug = $data['slug'] ?? null;

        if (!$id && !$slug) {
            throw new \FOSSBilling\Exception('ID or slug is required.');
        }

        /** @var \Box\Mod\News\Repository\PostRepository $repo */
        $repo = $this->getService()->getPostRepository();

        $post = null;
        if ($id) {
            $post = $repo->find($id);
        } elseif ($slug) {
            $post = $repo->findOneBy(['slug' => $slug]);
        }

        if (!$post instanceof Post) {
            throw new \FOSSBilling\Exception('News item not found.');
        }

        /** @todo Doctrine: Replace with actual Admin entity once it's migrated to Doctrine. */
        $admin = $this->di['db']->getRow('SELECT name FROM admin WHERE id = :id', ['id' => $post->getAdminId()]);

        $post->setAdminData($admin);

        return $post->toApiArray();
    }

    /**
     * Update news item.
     */
    public function update(array $data): bool
    {
        $this->di['validator']->checkRequiredParamsForArray(['id' => 'Post ID not passed'], $data);

        /** @var \Box\Mod\News\Repository\PostRepository $repo */
        $repo = $this->getService()->getPostRepository();

        $post = $repo->find($data['id']);

        if (!$post instanceof Post) {
            throw new \FOSSBilling\Exception('News item not found');
        }

        $service = $this->getService();
        $description = $data['description'] ?? $post->getDescription();
        if (empty($description)) {
            $description = $service->generateDescriptionFromContent($data['content'] ?? $post->getContent());
        }

        $post->setTitle($data['title'] ?? $post->getTitle())
             ->setDescription($description)
             ->setSlug($data['slug'] ?? $post->getSlug())
             ->setContent($data['content'] ?? $post->getContent())
             ->setImage($data['image'] ?? $post->getImage())
             ->setSection($data['section'] ?? $post->getSection())
             ->setStatus($data['status'] ?? $post->getStatus());

        if (!empty($data['created_at'])) {
            $post->setCreatedAt(new \DateTime($data['created_at']));
        }

        $post->setAdminId($this->getIdentity()->id);

        $this->di['em']->persist($post);
        $this->di['em']->flush();

        $this->di['logger']->info('Updated news item #%s', $post->getId());

        return true;
    }

    /**
     * Create new news item.
     *
     * @return int New post ID
     */
    public function create(array $data): int
    {
        $this->di['validator']->checkRequiredParamsForArray(['title' => 'Post title not passed'], $data);

        $post = new Post($data['title'], $this->di['tools']->slug($data['title']));

        $post->setAdminId($this->getIdentity()->id)
             ->setContent($data['content'] ?? null)
             ->setStatus($data['status'] ?? Post::STATUS_ACTIVE)
             ->setDescription($data['description'] ?? null);

        $this->di['em']->persist($post);
        $this->di['em']->flush();

        $this->di['logger']->info('Created news item #%s', $post->getId());

        return $post->getId();
    }

    /**
     * Delete news item by ID.
     */
    public function delete(array $data): bool
    {
        $this->di['validator']->checkRequiredParamsForArray(['id' => 'Post ID not passed'], $data);

        /** @var \Box\Mod\News\Repository\PostRepository $repo */
        $repo = $this->getService()->getPostRepository();

        $post = $repo->find($data['id']);

        if (!$post instanceof Post) {
            throw new \FOSSBilling\Exception('News item not found');
        }

        $this->di['em']->remove($post);
        $this->di['em']->flush();

        $this->di['logger']->info('Removed news item #%s', $data['id']);

        return true;
    }

    /**
     * Batch delete news items by IDs.
     */
    public function batch_delete(array $data): bool
    {
        $this->di['validator']->checkRequiredParamsForArray(['ids' => 'IDs not passed'], $data);

        /** @var \Box\Mod\News\Repository\PostRepository $repo */
        $repo = $this->getService()->getPostRepository();

        $count = $repo->deleteByIds($data['ids']);

        $this->di['logger']->info('Removed %s news items', $count);

        return true;
    }
}
