<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Update\Patch;

use FOSSBilling\Update\Patcher;

class Patch102 implements PatchInterface
{
    public function getVersion(): int
    {
        return 102;
    }

    public function apply(Patcher $patcher): void
    {
        // Enforce unique slugs on custom_pages at the DB level (matches the CustomPage
        // entity UniqueConstraint and the module installer). The Custompages module may
        // not be installed on every instance, so skip cleanly when the table is absent.
        if (!$patcher->tableExists('custom_pages')) {
            return;
        }

        // Reconcile any duplicate slugs before adding the unique index: keep the
        // lowest-id row for each duplicated slug and rename the rest to an unused
        // suffixed variant (probed against the database so existing rows such as a
        // pre-existing "foo-2" are never collided with).
        $duplicates = $patcher->fetchAll(
            'SELECT c.id, c.slug FROM custom_pages c
             INNER JOIN (
                 SELECT slug FROM custom_pages GROUP BY slug HAVING COUNT(*) > 1
             ) d ON d.slug = c.slug
             ORDER BY c.slug ASC, c.id ASC'
        );

        $kept = [];
        foreach ($duplicates as $row) {
            $slug = $row['slug'];
            $id = (int) $row['id'];
            if (!isset($kept[$slug])) {
                $kept[$slug] = $id;

                continue;
            }

            $newSlug = $patcher->allocateUniqueCustomPageSlug($slug);
            $patcher->executeSql(
                'UPDATE custom_pages SET slug = :slug WHERE id = :id',
                ['slug' => $newSlug, 'id' => $id]
            );
        }

        if (!$patcher->tableHasIndex('custom_pages', 'uniq_custom_pages_slug')) {
            $patcher->executeSql('ALTER TABLE `custom_pages` ADD UNIQUE INDEX `uniq_custom_pages_slug` (`slug`)');
        }
    }
}
