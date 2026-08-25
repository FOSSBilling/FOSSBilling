<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
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
 *
 * @return array{0: Connection, 1: EntityManagerInterface}
 */
function installSeederConnection(): array
{
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $entityManager = EntityManagerFactory::create($connection);

    SchemaInstaller::createSchema($entityManager);

    return [$connection, $entityManager];
}

/**
 * There's no PostgreSQL server in this project's default local/CI setup, so every test needing
 * one skips gracefully rather than failing when it isn't reachable - but wherever a server *is*
 * reachable (a local `docker run postgres:16`, matching FOSSBILLING_TEST_PGSQL_DSN, or the
 * default localhost:5432/postgres/postgres/postgres), the test below exercises the real driver
 * PostgreSQL installs actually use, which is the only way to catch a real "boolean" column
 * rejecting a bare 0/1 literal - SQLite has no real boolean type, so it can't catch that class of
 * bug at all.
 */
function postgresTestDsn(): string
{
    return getenv('FOSSBILLING_TEST_PGSQL_DSN') ?: 'pgsql://postgres:postgres@127.0.0.1:5432/postgres';
}

function postgresAvailable(): bool
{
    try {
        DriverManager::getConnection((new Doctrine\DBAL\Tools\DsnParser())->parse(postgresTestDsn()))->fetchOne('SELECT 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

/**
 * @return array{0: Connection, 1: EntityManagerInterface}
 */
function postgresSeederConnection(): array
{
    $connection = DriverManager::getConnection((new Doctrine\DBAL\Tools\DsnParser())->parse(postgresTestDsn()));

    // A single fixed, always-recreated schema (namespace) rather than a fresh randomly-named one
    // per test: this project's tests run sequentially (no --parallel), so nothing needs isolating
    // between tests, and dropping-and-recreating it here means a run never leaves a stray schema
    // behind for the next one to clean up.
    $connection->executeStatement('DROP SCHEMA IF EXISTS fb_install_seeder_test CASCADE');
    $connection->executeStatement('CREATE SCHEMA fb_install_seeder_test');
    $connection->executeStatement('SET search_path TO fb_install_seeder_test');

    $entityManager = EntityManagerFactory::create($connection);
    SchemaInstaller::createSchema($entityManager);

    return [$connection, $entityManager];
}

function realContentSql(): string
{
    return file_get_contents(Path::join(PATH_ROOT, 'install', 'sql', 'content.sql'));
}

/**
 * A raw (non-hydrated) fetch of a boolean column returns different PHP representations per
 * platform - real PHP bool on SQLite, but PDO pgsql's own 't'/'f' strings on PostgreSQL (Doctrine
 * only normalizes that during entity hydration, not on a bare Connection::fetchOne()) - so tests
 * asserting a seeded boolean's value need this rather than a bare truthy/falsy check, since a
 * pgsql 'f' string is itself PHP-truthy.
 */
function fetchedBool(mixed $value): bool
{
    return in_array($value, [true, 1, '1', 't'], true);
}

test('createSchema builds every table content.sql seeds, including the ORM-unmapped session table', function (): void {
    [$connection] = installSeederConnection();

    foreach (['admin_group', 'client_group', 'currency', 'extension', 'support_kb_article', 'setting', 'session'] as $table) {
        expect($connection->createSchemaManager()->tablesExist([$table]))->toBeTrue("expected table {$table} to exist");
    }
});

test('seedContent replays every row from the real content.sql, portably', function (): void {
    [$connection, $entityManager] = installSeederConnection();
    $now = new DateTimeImmutable('2026-08-23 12:00:00');

    InstallSeeder::seedContent($connection, $entityManager, realContentSql(), $now);

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
    [$connection, $entityManager] = installSeederConnection();
    $now = new DateTimeImmutable('2026-08-23 12:00:00');

    InstallSeeder::seedContent($connection, $entityManager, realContentSql(), $now);

    expect($connection->fetchOne('SELECT created_at FROM currency WHERE id = 1'))->toBe('2026-08-23 12:00:00');
});

test('seedContent unescapes mysqldump backslash sequences into real characters', function (): void {
    [$connection, $entityManager] = installSeederConnection();

    InstallSeeder::seedContent($connection, $entityManager, realContentSql(), new DateTimeImmutable());

    $article = $connection->fetchAssociative("SELECT content FROM support_kb_article WHERE slug = 'how-to-place-new-order'");
    expect($article['content'])
        ->toContain("\n") // a real newline, not the two literal characters "\" and "n"
        ->not->toContain('\\n')
        ->toContain('Click "Continue"'); // \" unescaped to a literal double quote
});

test('seedContent preserves doubled-single-quote escaping as a literal apostrophe', function (): void {
    [$connection, $entityManager] = installSeederConnection();

    InstallSeeder::seedContent($connection, $entityManager, realContentSql(), new DateTimeImmutable());

    $category = $connection->fetchAssociative("SELECT title FROM support_kb_article_category WHERE slug = 'how-to'");
    expect($category['title'])->toBe("How to's");
});

test('seedContent rewrites content.sql\'s mysqldump 0/1 literals to real booleans, only for genuinely boolean-mapped columns', function (): void {
    [$connection, $entityManager] = installSeederConnection();

    InstallSeeder::seedContent($connection, $entityManager, realContentSql(), new DateTimeImmutable());

    // A spot check across several tables/columns rather than every boolean column: this is a
    // portability behavior, not per-row seed data, so one representative true and one
    // representative false per table is enough to prove the rewrite is happening.
    expect((bool) $connection->fetchOne("SELECT protected FROM admin_group WHERE system_name = 'super_admin'"))->toBeTrue()
        ->and((bool) $connection->fetchOne("SELECT protected FROM admin_group WHERE system_name = 'support_lead'"))->toBeFalse()
        ->and((bool) $connection->fetchOne("SELECT enabled FROM pay_gateway WHERE name = 'Custom'"))->toBeTrue()
        ->and((bool) $connection->fetchOne("SELECT test_mode FROM pay_gateway WHERE name = 'Custom'"))->toBeFalse()
        ->and((bool) $connection->fetchOne("SELECT active FROM product WHERE slug = 'domain-checker'"))->toBeTrue()
        ->and((bool) $connection->fetchOne("SELECT hidden FROM product WHERE slug = 'domain-checker'"))->toBeFalse();

    // A real integer column that happens to also hold a 0 must be left completely alone - proves
    // the rewrite only touches columns Doctrine actually maps as boolean, by column position, not
    // every bare "0"/"1" token in the file.
    expect((int) $connection->fetchOne("SELECT quantity_in_stock FROM product WHERE slug = 'domain-checker'"))->toBe(0)
        ->and((int) $connection->fetchOne("SELECT min_years FROM tld WHERE tld = '.com'"))->toBe(1);
});

test('seedAdmin creates the admin account and links it to the seeded Super Administrator group', function (): void {
    [$connection, $entityManager] = installSeederConnection();
    $now = new DateTimeImmutable('2026-08-23 12:00:00');
    InstallSeeder::seedContent($connection, $entityManager, realContentSql(), $now);

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
    [$connection, $entityManager] = installSeederConnection();
    InstallSeeder::seedContent($connection, $entityManager, realContentSql(), new DateTimeImmutable());

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

    InstallSeeder::seedContent($connection, $entityManager, realContentSql(), new DateTimeImmutable());

    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM product'))->toBe(1)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM tld'))->toBe(1)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM support_pr'))->toBe(17)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM support_kb_article'))->toBe(3);
});

test('seedInstallNudge records the version as a new setting row', function (): void {
    [$connection, $entityManager] = installSeederConnection();
    InstallSeeder::seedContent($connection, $entityManager, realContentSql(), new DateTimeImmutable());

    InstallSeeder::seedInstallNudge($connection, '1.2.3', new DateTimeImmutable('2026-08-23 12:00:00'));

    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM setting'))->toBe(33)
        ->and($connection->fetchOne("SELECT value FROM setting WHERE param = 'last_error_reporting_nudge'"))->toBe('1.2.3');
});

test('seedContent completes on a real PostgreSQL server, with boolean columns holding true/false rather than raising a datatype-mismatch error', function (): void {
    [$connection, $entityManager] = postgresSeederConnection();
    $now = new DateTimeImmutable('2026-08-23 12:00:00');

    // This is the actual regression: PostgreSQL has a genuine `boolean` column type (unlike MySQL's
    // tinyint stand-in or SQLite's lack of one) that rejects a bare integer literal outright -
    // before the fix, this INSERT failed on the very first row with SQLSTATE 42804 ("column
    // "protected" is of type boolean but expression is of type integer").
    InstallSeeder::seedContent($connection, $entityManager, realContentSql(), $now);
    InstallSeeder::seedAdmin($connection, 'Ada Lovelace', 'ada@example.test', 'hashed-password', 'api-token-value', $now);
    InstallSeeder::setDefaultCurrency($connection, 'USD');
    InstallSeeder::seedInstallNudge($connection, '1.2.3', $now);

    expect(fetchedBool($connection->fetchOne("SELECT protected FROM admin_group WHERE system_name = 'super_admin'")))->toBeTrue()
        ->and(fetchedBool($connection->fetchOne("SELECT protected FROM admin_group WHERE system_name = 'support_lead'")))->toBeFalse()
        ->and(fetchedBool($connection->fetchOne("SELECT enabled FROM pay_gateway WHERE name = 'Custom'")))->toBeTrue()
        ->and(fetchedBool($connection->fetchOne("SELECT test_mode FROM pay_gateway WHERE name = 'Custom'")))->toBeFalse()
        ->and(fetchedBool($connection->fetchOne("SELECT allow_register FROM tld WHERE tld = '.com'")))->toBeTrue()
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM setting'))->toBe(33)
        ->and($connection->fetchOne("SELECT value FROM setting WHERE param = 'hide_company_public'"))->toBe('1');
})->skip(fn (): bool => !postgresAvailable(), 'No local PostgreSQL server reachable at FOSSBILLING_TEST_PGSQL_DSN (or the localhost:5432 default) - this test only runs when one is available.');

test('seedContent resyncs PostgreSQL sequences past content.sql\'s explicit ids, so the next auto-generated insert never collides', function (): void {
    [$connection, $entityManager] = postgresSeederConnection();

    InstallSeeder::seedContent($connection, $entityManager, realContentSql(), new DateTimeImmutable());

    // content.sql seeds setting rows 1-32 with explicit ids - a PostgreSQL sequence never tracks
    // those on its own (unlike MySQL's AUTO_INCREMENT or SQLite's rowid), so without the resync
    // this collides on the *first* auto-generated insert into any seeded table, exactly as
    // seedInstallNudge() does here.
    InstallSeeder::seedInstallNudge($connection, '1.2.3', new DateTimeImmutable());
    $newSettingId = (int) $connection->fetchOne("SELECT id FROM setting WHERE param = 'last_error_reporting_nudge'");
    expect($newSettingId)->toBeGreaterThan(32);

    // Every other seeded table with explicit ids needs the same resync, not just `setting` -
    // check every sequence directly (rather than inserting a full row into each table, which
    // would also need to satisfy every other NOT NULL column) that its next value already lands
    // past content.sql's highest seeded id for that table.
    foreach (['admin_group', 'currency', 'extension', 'support_kb_article_category', 'support_kb_article', 'pay_gateway', 'post', 'product_category', 'product', 'support_helpdesk', 'support_pr_category', 'support_pr', 'tld_registrar', 'tld'] as $table) {
        $maxId = (int) $connection->fetchOne("SELECT MAX(id) FROM {$table}");
        $sequence = $connection->fetchOne('SELECT pg_get_serial_sequence(?, ?)', [$table, 'id']);
        $nextId = (int) $connection->fetchOne('SELECT nextval(?)', [$sequence]);
        expect($nextId)->toBeGreaterThan($maxId, "expected {$table}'s sequence to already be past content.sql's highest seeded id");
    }
})->skip(fn (): bool => !postgresAvailable(), 'No local PostgreSQL server reachable at FOSSBILLING_TEST_PGSQL_DSN (or the localhost:5432 default) - this test only runs when one is available.');
