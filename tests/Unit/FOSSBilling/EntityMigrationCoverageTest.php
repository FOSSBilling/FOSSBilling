<?php

declare(strict_types=1);

/*
 * Guards against a repeat of https://github.com/FOSSBilling/FOSSBilling/issues/4392:
 * an entity column merged without a MySQL migration, so existing installs crash
 * with "Unknown column ..." until the ambient schema sync happens to heal them.
 *
 * For any column added since the snapshot, the test requires an explicit reference
 * in UpdatePatcher (i.e. a hand-written MySQL patch like patch119/patch120): the
 * portable schema sync covers PostgreSQL/SQLite on its own, but MySQL upgrades
 * must not depend on sync timing.
 *
 * After adding the migration, regenerate the snapshot:
 * UPDATE_SNAPSHOT=1 ./src/vendor/bin/pest --test-directory ../tests --configuration phpunit.xml.dist tests/Unit/FOSSBilling/EntityMigrationCoverageTest.php
 */

use Doctrine\ORM\Mapping as ORM;
use FOSSBilling\Doctrine\EntityManagerFactory;
use Symfony\Component\Filesystem\Path;

function entityMigrationCoverageSnapshotPath(): string
{
    return Path::join(PATH_TESTS, 'Fixtures', 'entity-columns.snapshot.json');
}

/**
 * Every mapped `table.column`, sorted. Join columns of associations are
 * included: they are real database columns too (e.g. `gateway_id`).
 *
 * @return list<string>
 */
function entityMigrationCoverageCurrentColumns(): array
{
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $entityManager = EntityManagerFactory::create($connection);

    $columns = [];
    foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
        $table = $metadata->getTableName();
        foreach ($metadata->fieldMappings as $fieldMapping) {
            $columns[] = $table . '.' . $fieldMapping->columnName;
        }
        foreach ($metadata->associationMappings as $associationMapping) {
            if (!$associationMapping instanceof ORM\ToOneAssociationMapping) {
                continue;
            }
            foreach ($associationMapping->joinColumns as $joinColumn) {
                $columns[] = $table . '.' . $joinColumn->name;
            }
        }
    }

    $columns = array_values(array_unique($columns));
    sort($columns);

    return $columns;
}

test('new entity columns ship with a MySQL migration', function (): void {
    $current = entityMigrationCoverageCurrentColumns();
    expect($current)->not->toBe([]);

    $snapshotPath = entityMigrationCoverageSnapshotPath();
    if (getenv('UPDATE_SNAPSHOT')) {
        file_put_contents($snapshotPath, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        expect(true)->toBeTrue();

        return;
    }

    expect(file_exists($snapshotPath))->toBeTrue('Entity column snapshot is missing - regenerate it with UPDATE_SNAPSHOT=1.');

    $previous = json_decode((string) file_get_contents($snapshotPath), true);
    expect($previous)->toBeArray();

    $added = array_values(array_diff($current, $previous));
    if ($added !== []) {
        $patcher = (string) file_get_contents(Path::join(PATH_ROOT, 'library', 'FOSSBilling', 'UpdatePatcher.php'));
        $uncovered = array_values(array_filter(
            $added,
            static function (string $tableColumn) use ($patcher): bool {
                [, $column] = explode('.', $tableColumn, 2);

                return !str_contains($patcher, $column);
            }
        ));

        expect($uncovered)->toBe(
            [],
            'New entity columns without a MySQL migration in UpdatePatcher: ' . implode(', ', $uncovered)
            . ' - add a guarded patch (see patch119/patch120) and bump last_patch in content.sql, then regenerate the snapshot.'
        );
    }

    $removed = array_values(array_diff($previous, $current));
    expect($current)->toBe(
        $previous,
        'Entity columns changed (added: ' . implode(', ', $added) . '; removed: ' . implode(', ', $removed)
        . ') - regenerate the snapshot with UPDATE_SNAPSHOT=1 after covering additions with a migration.'
    );
});
