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
        // Raw MySQL-only DDL here (backticks, ENGINE=InnoDB) would fail outright on
        // PostgreSQL/SQLite. custom_pages isn't in structure.sql at all - this module creates
        // its own table on activation - so unlike the core install path, this genuinely runs on
        // every platform. SchemaSynchronizer::sync() creates (or catches up) every entity's
        // table from current metadata, additively and safely - the same mechanism
        // UpdatePatcher::applyCorePatches() already uses on every request.
        \FOSSBilling\Doctrine\SchemaSynchronizer::sync($this->di['em']);

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
        // claim the same slug before the insert. Insert via the DBAL connection (not
        // an ORM flush) so a constraint violation doesn't close the EntityManager and
        // break the retry loop on the next iteration.
        $connection = $this->di['em']->getConnection();
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $slug = $this->generateUniqueSlug($title);

            try {
                $connection->insert('custom_pages', [
                    'title' => $title,
                    'description' => $description ?? '',
                    'keywords' => $keywords ?? '',
                    'content' => $content,
                    'slug' => $slug,
                ]);

                $id = (int) $connection->lastInsertId();
                $this->di['logger']->info('Created new custom page #{id}', ['id' => $id]);

                return $id;
            } catch (UniqueConstraintViolationException) {
                // Slug lost the race; loop and try the next candidate.
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
        $this->di['logger']->info('Updated custom page #{id}', ['id' => $id]);

        return (int) $id;
    }

    /**
     * Generate a unique slug for a page title, appending an incrementing
     * suffix until no existing page uses it.
     *
     * Preserves the legacy behavior of re-slugging the title on each iteration
     * (`<slug>-1`, `<slug>-2`, ...). Candidates are truncated so they never
     * exceed the custom_pages.slug VARCHAR(255) column, reserving room for the
     * hyphen and suffix before appending it.
     */
    private function generateUniqueSlug(string $title): string
    {
        $slug = $this->fitSlug($this->di['tools']->slug($title), null);
        $i = 0;
        while ($this->pageRepository->findOneBySlug($slug) instanceof CustomPage) {
            $slug = $this->fitSlug($this->di['tools']->slug($title), ++$i);
        }

        return $slug;
    }

    /**
     * Truncate a slug (optionally suffixed) to fit the VARCHAR(255) column,
     * reserving room for "-$suffix" when a suffix is requested.
     */
    private function fitSlug(string $base, ?int $suffix): string
    {
        if ($suffix === null) {
            return strlen($base) <= 255 ? $base : substr($base, 0, 255);
        }

        $suffixStr = '-' . $suffix;
        if (strlen($base) + strlen($suffixStr) <= 255) {
            return $base . $suffixStr;
        }

        return substr($base, 0, 255 - strlen($suffixStr)) . $suffixStr;
    }
}
