<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Doctrine;

use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;

/**
 * Restores column-length prefixes (the `lengths` index option) that ORM 3.8's schema editor
 * API drops for plain (non-unique) indexes - its table index builder hardcodes a null length
 * for every column, while the unique-index builder next to it honors them. Without the
 * prefix, MySQL/MariaDB refuse to build an index touching a TEXT column (error 1071) and a
 * fresh install fails outright. Platforms without length prefixes never register this
 * listener (see EntityManagerFactory), and it no-ops there anyway since their DDL ignores
 * lengths.
 *
 * Remove once upstream handles `lengths` for plain indexes.
 */
final class IndexLengthsListener
{
    public function postGenerateSchemaTable(GenerateSchemaTableEventArgs $args): void
    {
        $class = $args->getClassMetadata();

        /** @var array<array{columns?: list<string>, fields?: list<string>, flags?: list<string>, options?: array{lengths?: list<int|null>, where?: string}}> $indexes */
        $indexes = $class->table['indexes'] ?? [];

        foreach ($indexes as $indexName => $indexData) {
            $lengths = $indexData['options']['lengths'] ?? null;
            if (!is_array($lengths) || $lengths === []) {
                continue;
            }

            if (is_numeric($indexName)) {
                throw new \LogicException(sprintf('Index on %s declares column lengths but has no name; name it so the lengths can be restored.', $class->getName()));
            }

            if (($indexData['flags'] ?? []) !== []) {
                throw new \LogicException(sprintf('Index %s declares flags with column lengths, which cannot be restored yet.', $indexName));
            }

            $table = $args->getClassTable();
            if (!$table->hasIndex((string) $indexName)) {
                continue; // skipped as redundant against a unique constraint - nothing to restore
            }

            $columns = $indexData['columns'] ?? array_map(
                static fn (string $fieldName): string => $class->fieldMappings[$fieldName]->columnName,
                $indexData['fields'] ?? [],
            );

            $editor = $table->edit();
            $editor->dropIndexByUnquotedName((string) $indexName);

            $indexEditor = Index::editor()->setName(UnqualifiedName::unquoted((string) $indexName));
            foreach ($columns as $position => $columnName) {
                $indexEditor->addColumn(new IndexedColumn(
                    UnqualifiedName::unquoted($columnName),
                    $lengths[$position] ?? null,
                ));
            }

            if (isset($indexData['options']['where'])) {
                $indexEditor->setPredicate($indexData['options']['where']);
            }

            $editor->addIndex($indexEditor);
            $args->setClassTable($editor->create());
        }
    }
}
