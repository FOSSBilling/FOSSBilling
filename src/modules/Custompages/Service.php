<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Custompages;

use Box\Mod\Custompages\Entity\CustomPage;
use Box\Mod\Custompages\Repository\CustomPageRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use FOSSBilling\PaginationOptions;

class Service
{
    protected ?\Pimple\Container $di = null;
    protected CustomPageRepository $pageRepository;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->pageRepository = $di['em']->getRepository(CustomPage::class);
    }

    public function getPageRepository(): CustomPageRepository
    {
        return $this->pageRepository;
    }

    public function getModulePermissions(): array
    {
        return [
            'view' => [
                'type' => 'bool',
                'display_name' => __trans('View custom pages'),
                'description' => __trans('Allows the staff member to view custom pages.'),
            ],
            'manage' => [
                'type' => 'bool',
                'display_name' => __trans('Manage custom pages'),
                'description' => __trans('Allows the staff member to create, update, and delete custom pages.'),
            ],
        ];
    }

    public function install(): bool
    {
        $sql = '
            CREATE TABLE IF NOT EXISTS `custom_pages` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `title` varchar(255) NOT NULL,
                `description` varchar(555) NOT NULL,
                `keywords` varchar(555) NOT NULL,
                `content` text NOT NULL,
                `slug` varchar(255) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_custom_pages_slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8';
        $this->di['em']->getConnection()->executeStatement($sql);

        return true;
    }

    public function searchPages(array $data = []): array
    {
        $qb = $this->pageRepository->getSearchQueryBuilder($data);

        return $this->di['pager']->paginateDoctrineQuery($qb, PaginationOptions::fromArray($data));
    }

    public function deletePage($id): void
    {
        if (is_array($id)) {
            $ids = array_map(static fn ($x): int => (int) $x, $id);
            $this->pageRepository->deleteByIds($ids);

            return;
        }

        $page = $this->pageRepository->find((int) $id);
        if ($page instanceof CustomPage) {
            $this->di['em']->remove($page);
            $this->di['em']->flush();
        }
    }

    public function getPage($id, $type = 'id'): ?array
    {
        $allowedColumns = ['id', 'slug'];
        if (!in_array($type, $allowedColumns, true)) {
            throw new \FOSSBilling\Exception('Invalid column type: :type', [':type' => $type]);
        }

        $page = $type === 'slug'
            ? $this->pageRepository->findOneBySlug((string) $id)
            : $this->pageRepository->find((int) $id);

        return $page instanceof CustomPage ? $page->toApiArray() : null;
    }

    public function createPage($title, $description, $keywords, $content): int
    {
        // generateUniqueSlug() picks a free candidate, but a concurrent request can
        // claim the same slug between the check and the flush. The unique index on
        // custom_pages.slug turns that race into a catchable constraint violation,
        // which we resolve by clearing the EM and retrying with a fresh candidate.
        $page = null;
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $slug = $this->generateUniqueSlug($title);

            $page = new CustomPage();
            $page->setTitle($title)
                ->setDescription($description ?? '')
                ->setKeywords($keywords ?? '')
                ->setContent($content)
                ->setSlug($slug);

            try {
                $this->di['em']->persist($page);
                $this->di['em']->flush();

                $id = $page->getId() ?? 0;
                $this->di['logger']->info('Created new custom page #%s', $id);

                return $id;
            } catch (UniqueConstraintViolationException) {
                $this->di['em']->clear();
            }
        }

        throw new \FOSSBilling\Exception('Unable to generate a unique slug for the custom page.');
    }

    public function updatePage($id, $title, $description, $keywords, $content, $slug): int
    {
        $page = $this->pageRepository->find((int) $id);
        if (!$page instanceof CustomPage) {
            throw new \FOSSBilling\Exception('Custom page not found');
        }

        $slug = $this->di['tools']->slug($slug);
        $existing = $this->pageRepository->findOneBySlugExcludingId($slug, (int) $id);
        if ($existing instanceof CustomPage) {
            throw new \FOSSBilling\Exception('You need to set unique slug.', null, 9999);
        }

        $page->setTitle($title)
            ->setDescription($description ?? '')
            ->setKeywords($keywords ?? '')
            ->setContent($content)
            ->setSlug($slug);

        try {
            $this->di['em']->flush();
        } catch (UniqueConstraintViolationException) {
            // A concurrent request claimed this slug between the app-level check
            // and the flush. Surface it as the same uniqueness error as above.
            throw new \FOSSBilling\Exception('You need to set unique slug.', null, 9999);
        }
        $this->di['logger']->info('Updated custom page #%s', $id);

        return (int) $id;
    }

    /**
     * Generate a unique slug for a page title, appending an incrementing
     * suffix until no existing page uses it.
     *
     * Preserves the legacy behavior of re-slugging the title on each iteration
     * (`<slug>-1`, `<slug>-2`, ...).
     */
    private function generateUniqueSlug(string $title): string
    {
        $slug = $this->di['tools']->slug($title);
        $i = 0;
        while ($this->pageRepository->findOneBySlug($slug) instanceof CustomPage) {
            $slug = $this->di['tools']->slug($title) . '-' . ++$i;
        }

        return $slug;
    }
}
