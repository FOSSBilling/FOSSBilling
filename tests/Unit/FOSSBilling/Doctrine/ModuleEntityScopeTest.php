<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Doctrine\DBAL\DriverManager;
use FOSSBilling\Doctrine\EntityManagerFactory;
use FOSSBilling\Doctrine\InstallSeeder;
use FOSSBilling\Doctrine\ModuleEntityScope;
use FOSSBilling\Doctrine\SchemaInstaller;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

test('moduleForEntityClass extracts the lowercased module name from a Box\Mod\{Module}\Entity\... class', function (): void {
    expect(ModuleEntityScope::moduleForEntityClass(Box\Mod\Custompages\Entity\CustomPage::class))->toBe('custompages')
        ->and(ModuleEntityScope::moduleForEntityClass(Box\Mod\Currency\Entity\Currency::class))->toBe('currency');
});

test('moduleForEntityClass returns null for a class outside the Box\Mod\{Module}\Entity\... convention', function (): void {
    expect(ModuleEntityScope::moduleForEntityClass(ModuleEntityScope::class))->toBeNull()
        ->and(ModuleEntityScope::moduleForEntityClass('NotEvenNamespaced'))->toBeNull();
});

test('isEagerAtInstall is true for a core module', function (): void {
    expect(ModuleEntityScope::isEagerAtInstall('currency'))->toBeTrue();
});

test('isEagerAtInstall is true for a default-active extension', function (): void {
    expect(ModuleEntityScope::isEagerAtInstall('news'))->toBeTrue();
});

test('isEagerAtInstall is false for a non-core extension not installed by default', function (): void {
    expect(ModuleEntityScope::isEagerAtInstall('custompages'))->toBeFalse();
});

test('installedExtensionModules returns an empty list when the extension table does not exist yet', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

    expect(ModuleEntityScope::installedExtensionModules($connection))->toBe([]);
});

test('installedExtensionModules lists only mod-type rows currently marked installed', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $entityManager = EntityManagerFactory::create($connection);
    SchemaInstaller::createSchema($entityManager);

    $connection->insert('extension', ['type' => 'mod', 'name' => 'custompages', 'status' => 'installed', 'version' => '1.0.0']);
    // A deactivated mod-type row must not count, nor a non-mod (theme) type row even if installed.
    $connection->insert('extension', ['type' => 'mod', 'name' => 'massmailer', 'status' => 'deactivated', 'version' => '1.0.0']);
    $connection->insert('extension', ['type' => 'theme', 'name' => 'huraga', 'status' => 'installed', 'version' => '1.0.0']);

    expect(ModuleEntityScope::installedExtensionModules($connection))->toBe(['custompages']);
});

test('isEagerNow is true for a core module even with an empty installed list', function (): void {
    expect(ModuleEntityScope::isEagerNow('currency', []))->toBeTrue();
});

test('isEagerNow is true for a non-core module present in the installed list', function (): void {
    expect(ModuleEntityScope::isEagerNow('custompages', ['custompages']))->toBeTrue();
});

test('isEagerNow is false for a non-core module absent from the installed list', function (): void {
    expect(ModuleEntityScope::isEagerNow('custompages', ['massmailer']))->toBeFalse();
});

/*
 * DEFAULT_ACTIVE_EXTENSIONS can't be derived from content.sql at runtime - see its own docblock:
 * SchemaInstaller::createSchema() needs this before the `extension` table (or the database
 * itself, on a from-scratch install) exists to query. So it's a hand-maintained copy of what
 * content.sql actually seeds, which can silently drift out of sync with the real file. This test
 * is what keeps that copy honest: seed the real content.sql into a real database and compare what
 * it actually installs against the constant, so a future content.sql change that adds, removes, or
 * renames a default-installed mod extension without updating this constant fails loudly here,
 * rather than silently gating (or failing to gate) the wrong module at fresh-install time.
 */
test('DEFAULT_ACTIVE_EXTENSIONS matches exactly the mod extensions the real content.sql seeds as installed', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $entityManager = EntityManagerFactory::create($connection);
    SchemaInstaller::createSchema($entityManager);

    $contentSql = (new Filesystem())->readFile(Path::join(PATH_ROOT, 'install', 'sql', 'content.sql'));
    InstallSeeder::seedContent($connection, $entityManager, $contentSql, new DateTimeImmutable());

    $seededDefaultExtensions = $connection->fetchFirstColumn(
        "SELECT name FROM extension WHERE type = 'mod' AND status = 'installed' ORDER BY name",
    );

    $declaredDefaultExtensions = (new ReflectionClass(ModuleEntityScope::class))->getConstant('DEFAULT_ACTIVE_EXTENSIONS');
    sort($declaredDefaultExtensions);

    expect($declaredDefaultExtensions)->toBe($seededDefaultExtensions);
});
