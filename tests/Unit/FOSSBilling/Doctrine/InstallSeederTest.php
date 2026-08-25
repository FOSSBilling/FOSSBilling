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
use FOSSBilling\Doctrine\SchemaInstaller;
use Symfony\Component\Filesystem\Path;

/**
 * A real SQLite connection with the full application schema, seeded from the *actual*
 * install/sql/content.sql file - not a fixture copy. This is the regression test for the
 * install/sql/content.sql portability rewrite: a plain pass here already proves the real seed
 * data (including its markdown-ish text content) executes as valid, equivalent SQL on a
 * standard-conforming platform, not just on MySQL.
 *
 * Goes through the same EntityManagerFactory::create() the real install path uses (naming
 * strategy included), rather than building an ad-hoc EntityManager, so this exercises the exact
 * metadata configuration production schema generation does.
 */
function installSeederConnection(): Doctrine\DBAL\Connection
{
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $entityManager = EntityManagerFactory::create($connection);

    SchemaInstaller::createSchema($entityManager);

    return $connection;
}

function realContentSql(): string
{
    return file_get_contents(Path::join(PATH_ROOT, 'install', 'sql', 'content.sql'));
}

test('createSchema builds every table content.sql seeds, including the ORM-unmapped session table', function (): void {
    $connection = installSeederConnection();

    foreach (['admin_group', 'client_group', 'currency', 'extension', 'support_kb_article', 'setting', 'session'] as $table) {
        expect($connection->createSchemaManager()->tablesExist([$table]))->toBeTrue("expected table {$table} to exist");
    }
});

test('seedContent replays every row from the real content.sql, portably', function (): void {
    $connection = installSeederConnection();
    $now = new DateTimeImmutable('2026-08-23 12:00:00');

    InstallSeeder::seedContent($connection, realContentSql(), $now);

    // Row counts pinned against the real file, so this fails loudly if content.sql gains or
    // loses a row without this test being updated to match.
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM admin_group'))->toBe(3)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM client_group'))->toBe(1)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM currency'))->toBe(1)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM extension'))->toBe(3)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM support_kb_article'))->toBe(3)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM support_kb_article_category'))->toBe(2)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM pay_gateway'))->toBe(2)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM post'))->toBe(3)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM product'))->toBe(1)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM product_category'))->toBe(1)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM setting'))->toBe(32)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM support_helpdesk'))->toBe(1)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM support_pr'))->toBe(17)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM support_pr_category'))->toBe(7)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM tld'))->toBe(1)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM tld_registrar'))->toBe(3);
});

test('seedContent resolves NOW() to the given timestamp', function (): void {
    $connection = installSeederConnection();
    $now = new DateTimeImmutable('2026-08-23 12:00:00');

    InstallSeeder::seedContent($connection, realContentSql(), $now);

    expect($connection->fetchOne('SELECT created_at FROM currency WHERE id = 1'))->toBe('2026-08-23 12:00:00');
});

test('seedContent unescapes mysqldump backslash sequences into real characters', function (): void {
    $connection = installSeederConnection();

    InstallSeeder::seedContent($connection, realContentSql(), new DateTimeImmutable());

    $article = $connection->fetchAssociative("SELECT content FROM support_kb_article WHERE slug = 'how-to-place-new-order'");
    expect($article['content'])
        ->toContain("\n") // a real newline, not the two literal characters "\" and "n"
        ->not->toContain('\\n')
        ->toContain('Click "Continue"'); // \" unescaped to a literal double quote
});

test('seedContent preserves doubled-single-quote escaping as a literal apostrophe', function (): void {
    $connection = installSeederConnection();

    InstallSeeder::seedContent($connection, realContentSql(), new DateTimeImmutable());

    $category = $connection->fetchAssociative("SELECT title FROM support_kb_article_category WHERE slug = 'how-to'");
    expect($category['title'])->toBe("How to's");
});

test('seedAdmin creates the admin account and links it to the seeded Super Administrator group', function (): void {
    $connection = installSeederConnection();
    $now = new DateTimeImmutable('2026-08-23 12:00:00');
    InstallSeeder::seedContent($connection, realContentSql(), $now);

    $adminId = InstallSeeder::seedAdmin($connection, 'Ada Lovelace', 'ada@example.test', 'hashed-password', 'api-token-value', $now);

    $admin = $connection->fetchAssociative('SELECT * FROM admin WHERE id = :id', ['id' => $adminId]);
    expect($admin['name'])->toBe('Ada Lovelace')
        ->and($admin['email'])->toBe('ada@example.test')
        ->and($admin['pass'])->toBe('hashed-password')
        ->and($admin['api_token'])->toBe('api-token-value');

    $membership = $connection->fetchAssociative(
        'SELECT * FROM admin_group_member WHERE admin_id = :admin_id',
        ['admin_id' => $adminId],
    );
    expect($membership['admin_group_id'])->toEqual(1);
});

test('setDefaultCurrency repoints the seeded USD row at the chosen currency code', function (): void {
    $connection = installSeederConnection();
    InstallSeeder::seedContent($connection, realContentSql(), new DateTimeImmutable());

    InstallSeeder::setDefaultCurrency($connection, 'EUR');

    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM currency'))->toBe(1)
        ->and($connection->fetchOne('SELECT code FROM currency WHERE id = 1'))->toBe('EUR');
});

test('seedContent respects foreign key dependency order for every real FK-mapped seed table', function (): void {
    // Plain installSeederConnection() (like every other test above) runs against SQLite with FK
    // enforcement left at its default off, so a child row seeded before its parent silently
    // succeeds there even when it wouldn't on a platform that actually enforces the constraint.
    // MySQL/MariaDB never had real FK constraints before the MySQL-onto-SchemaInstaller cutover
    // (see install.php's installPortable()), so content.sql's mysqldump-derived table order -
    // effectively alphabetical, which for several tables means the *child* row (`product`,
    // `support_kb_article`, `support_pr`, `tld`) is dumped before its own *parent* (`product_
    // category`, `support_kb_article_category`, `support_pr_category`, `tld_registrar`) - was
    // never actually exercised against real FK enforcement. Turning the pragma on here catches
    // exactly the class of bug a live MariaDB/PostgreSQL run caught during that cutover: seeding
    // support_kb_article's `kb_article_category_id = 2` before support_kb_article_category's own
    // id 2 row existed, and the same shape for product/tld/support_pr's own category-or-registrar
    // reference.
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('PRAGMA foreign_keys = ON');
    $entityManager = EntityManagerFactory::create($connection);
    SchemaInstaller::createSchema($entityManager);

    InstallSeeder::seedContent($connection, realContentSql(), new DateTimeImmutable());

    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM product'))->toBe(1)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM tld'))->toBe(1)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM support_pr'))->toBe(17)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM support_kb_article'))->toBe(3);
});

test('seedInstallNudge records the version as a new setting row', function (): void {
    $connection = installSeederConnection();
    InstallSeeder::seedContent($connection, realContentSql(), new DateTimeImmutable());

    InstallSeeder::seedInstallNudge($connection, '1.2.3', new DateTimeImmutable('2026-08-23 12:00:00'));

    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM setting'))->toBe(33)
        ->and($connection->fetchOne("SELECT value FROM setting WHERE param = 'last_error_reporting_nudge'"))->toBe('1.2.3');
});
