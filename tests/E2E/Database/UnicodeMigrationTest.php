<?php

declare(strict_types=1);

// cspell:ignore Résumé boîtes

use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Doctrine\DriverManagerFactory;
use FOSSBilling\Doctrine\EntityManagerFactory;
use FOSSBilling\Doctrine\InstallSeeder;
use FOSSBilling\Doctrine\SchemaInstaller;
use FOSSBilling\Doctrine\SchemaSynchronizer;
use FOSSBilling\UpdatePatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final class UnicodeMigrationPdo extends PDO
{
    public array $alters = [];
    public ?string $rejectAlter = null;

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_starts_with($query, 'ALTER TABLE')) {
            $this->alters[] = $query;
            if ($this->rejectAlter !== null && str_contains($query, $this->rejectAlter)) {
                throw new PDOException('Simulated DDL failure');
            }
        }

        return parent::prepare($query, $options);
    }
}

beforeEach(function (): void {
    $port = getenv('CHARSET_TEST_MYSQL_PORT');
    if (!$port) {
        $this->markTestSkipped('Set CHARSET_TEST_MYSQL_PORT for an isolated MySQL/MariaDB test server.');
    }

    $this->database = 'fb_unicode_test_' . bin2hex(random_bytes(6));
    $this->pdo = new UnicodeMigrationPdo('mysql:host=127.0.0.1;port=' . (int) $port . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $this->pdo->exec('CREATE DATABASE `' . $this->database . '` CHARACTER SET utf8 COLLATE utf8_general_ci');
    $this->pdo->exec('USE `' . $this->database . '`');
    $this->connection = DriverManagerFactory::getConnection([], [
        'driver' => 'pdo_mysql', 'host' => '127.0.0.1', 'port' => (int) $port,
        'name' => $this->database, 'user' => 'root', 'password' => '',
    ]);
    $di = new Pimple\Container();
    $di['pdo'] = $this->pdo;
    $di['dbal'] = $this->connection;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $this->patcher = new UpdatePatcher();
    $this->patcher->setDi($di);
    $this->runPatch = (new ReflectionMethod($this->patcher, 'patch133'))->getClosure($this->patcher);
});

afterEach(function (): void {
    if (isset($this->pdo)) {
        $this->pdo->exec('DROP DATABASE `' . $this->database . '`');
        $this->connection->close();
    }
});

test('Unicode repair covers authored text across legacy first-party tables', function (): void {
    $cases = [
        'support_ticket' => ['subject' => '🚀 𠮷', 'author_name' => '🚀 𠮷', 'author_email' => '𠮷@example.com'],
        'support_ticket_message_history' => ['content' => '🚀 𠮷'],
        'support_ticket_note' => ['note' => '🚀 𠮷'],
        'support_kb_article' => ['title' => '🚀 𠮷', 'content' => '🚀 𠮷'],
        'support_kb_article_category' => ['title' => '🚀 𠮷', 'description' => '🚀 𠮷'],
        'support_pr' => ['title' => '🚀 𠮷', 'content' => '🚀 𠮷'],
        'support_helpdesk' => ['name' => '🚀 𠮷', 'signature' => '🚀 𠮷', 'email' => '𠮷@example.com'],
        'email_template' => ['subject' => '🚀 𠮷', 'content' => '🚀 𠮷', 'description' => '🚀 𠮷'],
        'email_queue' => [
            'subject' => '🚀 𠮷', 'content' => '🚀 𠮷', 'to_name' => '🚀 𠮷', 'from_name' => '🚀 𠮷',
            'recipient' => '𠮷@example.com', 'sender' => '𠮷@example.com',
        ],
        'mod_massmailer' => ['subject' => '🚀 𠮷', 'content' => '🚀 𠮷', 'from_email' => '𠮷@example.com'],
        'product' => [
            'title' => '🚀 𠮷', 'description' => '🚀 𠮷', 'icon_url' => 'https://example.com/🚀.svg',
            'plugin_config' => '{"label":"🚀"}',
        ],
        'product_category' => ['title' => '🚀 𠮷', 'description' => '🚀 𠮷', 'icon_url' => 'https://example.com/🚀.svg'],
        'client_order' => ['title' => '🚀 𠮷', 'notes' => '🚀 𠮷', 'reason' => '🚀 𠮷'],
        'invoice' => [
            'notes' => '🚀 𠮷', 'text_1' => '🚀 𠮷', 'text_2' => '🚀 𠮷', 'buyer_first_name' => '🚀 𠮷',
            'buyer_company' => '🚀 𠮷', 'seller_company_vat' => '𠮷-123', 'seller_company_number' => '𠮷-123',
            'buyer_company_vat' => '𠮷-123', 'buyer_company_number' => '𠮷-123', 'buyer_zip' => '𠮷-123',
            'seller_phone' => '📞 123', 'buyer_phone' => '📞 123', 'buyer_email' => '𠮷@example.com',
            'seller_email' => '𠮷@example.com',
        ],
        'invoice_item' => ['title' => '🚀 𠮷'],
        'client' => [
            'first_name' => '🚀 𠮷', 'last_name' => '🚀 𠮷', 'company' => '🚀 𠮷', 'notes' => '🚀 𠮷', 'custom_1' => '🚀 𠮷',
            'custom_20' => '🚀 𠮷', 'company_vat' => '𠮷-123', 'company_number' => '𠮷-123', 'postcode' => '𠮷-123',
            'billing_email' => '𠮷@example.com',
        ],
        'client_balance' => ['description' => '🚀 𠮷'],
        'client_group' => ['title' => '🚀 𠮷'],
        'admin' => ['name' => '🚀 𠮷', 'signature' => '🚀 𠮷'],
        'form_field' => ['label' => '🚀 𠮷', 'description' => '🚀 𠮷', 'default_value' => '🚀 𠮷', 'options' => '{"label":"🚀"}'],
        'setting' => ['value' => '🚀 𠮷'],
        'post' => [
            'title' => '🚀 𠮷', 'description' => '🚀 𠮷', 'content' => '🚀 𠮷', 'section' => '🚀 𠮷',
            'image' => 'https://example.com/🚀.png',
        ],
        'service_domain' => [
            'contact_postcode' => '𠮷-123', 'sld' => '𠮷', 'tld' => '𠮷', 'ns1' => 'ns.𠮷.example',
            'ns2' => 'ns.𠮷.example', 'ns3' => 'ns.𠮷.example', 'ns4' => 'ns.𠮷.example',
            'transfer_code' => '𠮷-secret', 'contact_email' => '𠮷@example.com',
        ],
        'service_hosting_server' => [
            'username' => '𠮷', 'password' => 'test-🚀', 'status_url' => 'https://example.com/🚀',
            'hostname' => 'host.𠮷.example', 'ns1' => 'ns.𠮷.example', 'ns2' => 'ns.𠮷.example',
            'ns3' => 'ns.𠮷.example', 'ns4' => 'ns.𠮷.example',
        ],
        'service_hosting' => ['sld' => '𠮷', 'tld' => '𠮷', 'username' => '𠮷'],
        'activity_client_email' => ['sender' => '𠮷@example.com', 'recipients' => '𠮷@example.com'],
        'service_custom' => ['plugin_config' => '{"label":"🚀"}'],
        'service_downloadable_file' => ['filename' => '🚀.pdf', 'label' => '𠮷', 'description' => 'Download 🚀'],
        'transaction' => ['output' => 'Result 🚀', 'txn_status' => 'Result 🚀'],
    ];
    $original = 'Résumé 日本語';
    expect((new FOSSBilling\Validate())->isSldValid('𠮷'))->toBeTrue();
    foreach ($cases as $table => $values) {
        // Column-definition preservation is tested separately with complete table definitions.
        $definitions = array_map(static fn (string $name): string => '`' . $name . '` TEXT NULL', array_keys($values));
        $this->pdo->exec('CREATE TABLE `' . $table . '` (id INT PRIMARY KEY, ' . implode(', ', $definitions) . ') DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci');
        $this->connection->insert($table, ['id' => 1] + array_fill_keys(array_keys($values), $original));
        foreach ($values as $column => $value) {
            expect(fn () => $this->connection->executeStatement('UPDATE `' . $table . '` SET `' . $column . '` = ?', [$value]))
                ->toThrow(Doctrine\DBAL\Exception\DriverException::class);
        }
    }

    ($this->runPatch)();
    foreach ($cases as $table => $values) {
        foreach ($values as $column => $value) {
            expect($this->connection->fetchOne('SELECT `' . $column . '` FROM `' . $table . '`'))->toBe($original);
            $this->connection->executeStatement('UPDATE `' . $table . '` SET `' . $column . '` = ?', [$value]);
            expect($this->connection->fetchOne('SELECT `' . $column . '` FROM `' . $table . '`'))->toBe($value);
        }
    }
    expect(json_decode($this->connection->fetchOne('SELECT options FROM form_field'), true, 512, JSON_THROW_ON_ERROR))->toBe(['label' => '🚀'])
        ->and($this->patcher->getDi()['logger']->calls)->toBe([]);
    $alters = $this->pdo->alters;
    ($this->runPatch)();
    expect($this->pdo->alters)->toBe($alters);
});

test('Unicode repair rebuilds the legacy support full-text index without changing content types or search', function (): void {
    $this->pdo->exec("CREATE TABLE support_ticket_message (
        id BIGINT PRIMARY KEY,
        content TEXT NULL COLLATE utf8_unicode_ci COMMENT 'Message body',
        FULLTEXT KEY content_idx (content)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
    $this->connection->insert('support_ticket_message', ['id' => 1, 'content' => 'Needle Résumé 日本語']);
    expect(fn () => $this->connection->executeStatement('UPDATE support_ticket_message SET content = ?', ['Needle 🚀 𠮷']))
        ->toThrow(Doctrine\DBAL\Exception\DriverException::class);
    $indexes = $this->pdo->query('SHOW INDEX FROM support_ticket_message')->fetchAll(PDO::FETCH_ASSOC);

    ($this->runPatch)();
    $column = $this->connection->fetchAssociative("SHOW FULL COLUMNS FROM support_ticket_message LIKE 'content'");
    expect($column['Type'])->toBe('text')
        ->and($column['Collation'])->toBe('utf8mb4_unicode_ci')
        ->and($column['Null'])->toBe('YES')
        ->and($column['Default'])->toBeNull()
        ->and($column['Comment'])->toBe('Message body')
        ->and($this->connection->fetchOne('SELECT content FROM support_ticket_message'))->toBe('Needle Résumé 日本語');
    $this->connection->executeStatement('UPDATE support_ticket_message SET content = ?', ['Needle 🚀 𠮷']);
    expect($this->connection->fetchOne('SELECT content FROM support_ticket_message'))->toBe('Needle 🚀 𠮷')
        ->and($this->connection->fetchOne("SELECT id FROM support_ticket_message WHERE MATCH(content) AGAINST ('Needle')"))->toBe(1);
    // Cardinality can change when the server rebuilds an index; its definition must not.
    $definition = static fn (array $rows): array => array_map(static fn (array $row): array => array_intersect_key($row, array_flip(['Key_name', 'Column_name', 'Seq_in_index', 'Index_type', 'Non_unique', 'Sub_part'])), $rows);
    expect($definition($this->pdo->query('SHOW INDEX FROM support_ticket_message')->fetchAll(PDO::FETCH_ASSOC)))->toBe($definition($indexes));
    $alters = $this->pdo->alters;
    ($this->runPatch)();
    expect($this->pdo->alters)->toBe($alters);
});

test('Unicode repair fixes every first-party table default and future schema additions', function (): void {
    $em = EntityManagerFactory::create($this->connection);
    (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
    $tables = $this->connection->fetchFirstColumn('SHOW TABLES');
    foreach ($tables as $table) {
        $this->pdo->exec('ALTER TABLE `' . $table . '` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci');
    }
    $this->pdo->exec('ALTER TABLE setting DROP COLUMN category');
    // External tables and indexed identifiers must retain their own definitions.
    $this->pdo->exec('CREATE TABLE third_party (title TEXT) DEFAULT CHARSET=utf8 COLLATE=utf8_bin');
    $externalDefault = $this->connection->fetchOne("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'third_party'");
    $slugBefore = $this->connection->fetchAssociative("SHOW FULL COLUMNS FROM post LIKE 'slug'");
    ($this->runPatch)();

    foreach ($tables as $table) {
        expect($this->connection->fetchOne('SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]))
            ->toBe('utf8mb4_general_ci');
    }
    expect($this->connection->fetchOne("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'third_party'"))->toBe($externalDefault)
        ->and($this->connection->fetchAssociative("SHOW FULL COLUMNS FROM post LIKE 'slug'"))->toBe($slugBefore);
    SchemaSynchronizer::syncEntities($em, [Box\Mod\System\Entity\Setting::class]);
    $this->connection->insert('setting', ['param' => 'unicode_test', 'category' => '🚀 𠮷']);
    expect($this->connection->fetchOne('SELECT category FROM setting'))->toBe('🚀 𠮷');
    $alters = $this->pdo->alters;
    ($this->runPatch)();
    expect($this->pdo->alters)->toBe($alters);
});

test('Unicode repair leaves customized support indexes intact for manual review', function (string $index): void {
    $this->pdo->exec('CREATE TABLE support_ticket_message (id INT PRIMARY KEY, content TEXT NULL, attachment TEXT NULL, ' . $index . ') ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci');
    $before = $this->pdo->query('SHOW FULL COLUMNS FROM support_ticket_message')->fetchAll(PDO::FETCH_ASSOC);
    ($this->runPatch)();
    expect($this->pdo->query('SHOW FULL COLUMNS FROM support_ticket_message')->fetchAll(PDO::FETCH_ASSOC))->toBe($before)
        ->and($this->patcher->getDi()['logger']->calls)->not->toBeEmpty();
    foreach ($this->patcher->getDi()['logger']->calls as $call) {
        expect($call['method'])->toBe('warning');
    }
})->with([
    'multi-column fulltext' => ['FULLTEXT KEY custom_search (content, attachment)'],
    'ordinary prefix index' => ['KEY custom_search (content(100)), KEY attachment_idx (attachment(100))'],
]);

test('Unicode repair preserves customized defaults, generated columns and non-UTF8 table defaults', function (): void {
    $this->pdo->exec("CREATE TABLE product (
        id INT PRIMARY KEY,
        title VARCHAR(255) NOT NULL DEFAULT 'custom',
        description TEXT GENERATED ALWAYS AS (title) STORED
    ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
    $this->pdo->exec('CREATE TABLE client (notes TEXT) DEFAULT CHARSET=latin1 COLLATE=latin1_bin');
    $before = $this->pdo->query('SHOW FULL COLUMNS FROM product')->fetchAll(PDO::FETCH_ASSOC);
    $clientBefore = $this->pdo->query('SHOW FULL COLUMNS FROM client')->fetchAll(PDO::FETCH_ASSOC);
    ($this->runPatch)();
    expect($this->pdo->query('SHOW FULL COLUMNS FROM product')->fetchAll(PDO::FETCH_ASSOC))->toBe($before)
        ->and($this->pdo->query('SHOW FULL COLUMNS FROM client')->fetchAll(PDO::FETCH_ASSOC))->toBe($clientBefore)
        ->and($this->connection->fetchOne("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client'"))->toBe('latin1_bin');
    $messages = array_map(static fn (array $call): string => $call['params'][0], $this->patcher->getDi()['logger']->calls);
    expect(implode(' ', $messages))->toContain('product.title', 'product.description', 'customized character set');
});

test('Unicode repair can resume after DDL failure without repeating completed conversions', function (): void {
    foreach (['client', 'product'] as $table) {
        $this->pdo->exec('CREATE TABLE ' . $table . ' (id INT PRIMARY KEY, notes TEXT NULL, title TEXT NULL) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci');
    }
    $this->pdo->rejectAlter = '`product`';
    expect(fn () => ($this->runPatch)())->toThrow(FOSSBilling\Exception::class);
    expect($this->connection->fetchOne("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client'"))->toBe('utf8mb4_general_ci')
        ->and($this->connection->fetchOne("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product'"))->not->toStartWith('utf8mb4');
    $this->pdo->rejectAlter = null;
    ($this->runPatch)();
    expect(count(array_filter($this->pdo->alters, static fn (string $sql): bool => str_contains($sql, '`client`'))))->toBe(1);
    $this->connection->insert('product', ['id' => 1, 'title' => '🚀 𠮷']);
    expect($this->connection->fetchOne('SELECT title FROM product'))->toBe('🚀 𠮷');
    $alters = $this->pdo->alters;
    ($this->runPatch)();
    expect($this->pdo->alters)->toBe($alters);
});

test('Unicode repair preserves MariaDB UCA-1400 collation semantics', function (): void {
    $available = $this->connection->fetchFirstColumn("SELECT COLLATION_NAME FROM information_schema.COLLATION_CHARACTER_SET_APPLICABILITY WHERE CHARACTER_SET_NAME = 'utf8mb4'");
    if (!in_array('uca1400_ai_ci', $available, true)) {
        $this->markTestSkipped('MariaDB UCA-1400 collations are not available on this server.');
    }
    $this->pdo->exec('CREATE TABLE post (id INT PRIMARY KEY, title VARCHAR(255) NULL, content TEXT NULL) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci');
    $this->connection->insert('post', ['id' => 1, 'title' => 'Résumé', 'content' => '日本語']);
    expect($this->connection->fetchOne("SELECT id FROM post WHERE title = 'resume'"))->toBe(1);
    ($this->runPatch)();
    expect($this->connection->fetchOne("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'post'"))->toBe('utf8mb4_uca1400_ai_ci')
        ->and($this->connection->fetchOne("SELECT id FROM post WHERE title = 'resume'"))->toBe(1)
        ->and($this->connection->fetchOne('SELECT content FROM post'))->toBe('日本語');
    $this->connection->executeStatement('UPDATE post SET title = ?, content = ?', ['🚀', '𠮷']);
    expect($this->connection->fetchOne('SELECT content FROM post'))->toBe('𠮷')
        ->and($this->patcher->getDi()['logger']->calls)->toBe([]);
});

test('Unicode repair logs collations without an equivalent instead of changing comparison semantics', function (): void {
    $this->pdo->exec('CREATE TABLE post (id INT PRIMARY KEY, content TEXT NULL) DEFAULT CHARSET=utf8 COLLATE=utf8_general_mysql500_ci');
    $before = $this->connection->fetchAssociative("SHOW FULL COLUMNS FROM post LIKE 'content'");
    $default = $this->connection->fetchOne("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'post'");
    ($this->runPatch)();
    expect($this->connection->fetchAssociative("SHOW FULL COLUMNS FROM post LIKE 'content'"))->toBe($before)
        ->and($this->connection->fetchOne("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'post'"))->toBe($default)
        ->and($this->patcher->getDi()['logger']->calls)->toHaveCount(2);
});

test('Unicode repair diagnoses an explicit legacy connection charset', function (): void {
    $this->pdo->exec('SET NAMES utf8');
    ($this->runPatch)();
    $calls = $this->patcher->getDi()['logger']->calls;
    expect($calls)->toHaveCount(1)
        ->and($calls[0]['method'])->toBe('warning')
        ->and($calls[0]['params'][0])->toContain('db.charset', 'utf8mb4');
});

test('Unicode repair handles Stripe cache names, invoice series and serialized domain details', function (): void {
    $this->pdo->exec('CREATE TABLE pay_gateway_product (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci');
    $this->pdo->exec('CREATE TABLE invoice (id INT PRIMARY KEY, serie VARCHAR(50) NULL DEFAULT NULL) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci');
    $this->pdo->exec('CREATE TABLE service_domain (id INT PRIMARY KEY, details TEXT NULL DEFAULT NULL) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci');
    $cases = [
        ['pay_gateway_product', 'name', 'Résumé', 'Hosting 🚀'],
        ['invoice', 'serie', 'INV-', '🚀-'],
        ['service_domain', 'details', serialize(['contact_first_name' => 'Résumé']), serialize(['contact_first_name' => '𠮷'])],
    ];
    foreach ($cases as [$table, $column, $original, $value]) {
        $this->connection->insert($table, ['id' => 1, $column => $original]);
        expect(fn () => $this->connection->executeStatement('UPDATE `' . $table . '` SET `' . $column . '` = ?', [$value]))
            ->toThrow(Doctrine\DBAL\Exception\DriverException::class);
    }

    ($this->runPatch)();
    foreach ($cases as [$table, $column, $original, $value]) {
        expect($this->connection->fetchOne('SELECT `' . $column . '` FROM `' . $table . '`'))->toBe($original);
        $this->connection->executeStatement('UPDATE `' . $table . '` SET `' . $column . '` = ?', [$value]);
        expect($this->connection->fetchOne('SELECT `' . $column . '` FROM `' . $table . '`'))->toBe($value);
    }
    expect(unserialize($this->connection->fetchOne('SELECT details FROM service_domain'), ['allowed_classes' => false]))
        ->toBe(['contact_first_name' => '𠮷']);
    $alters = $this->pdo->alters;
    ($this->runPatch)();
    expect($this->pdo->alters)->toBe($alters);
});

test('Unicode repair preserves literal unit defaults and definitions throughout billing', function (string $productDefault, ?string $billingDefault): void {
    foreach (['product' => [50, false, $productDefault], 'client_order' => [100, true, $billingDefault], 'invoice_item' => [100, true, $billingDefault]] as $table => [$length, $nullable, $default]) {
        $this->pdo->exec('CREATE TABLE `' . $table . '` (id INT PRIMARY KEY, unit VARCHAR(' . $length . ') '
            . ($nullable ? 'NULL' : 'NOT NULL') . ' DEFAULT ' . ($default === null ? 'NULL' : $this->connection->quote($default))
            . " COMMENT 'Billing unit') DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
        $this->connection->insert($table, ['id' => 1]);
        expect($this->connection->fetchOne('SELECT unit FROM `' . $table . '`'))->toBe($default);
        expect(fn () => $this->connection->executeStatement('UPDATE `' . $table . '` SET unit = ?', ['📦 𠮷']))
            ->toThrow(Doctrine\DBAL\Exception\DriverException::class);
        $before[$table] = $this->connection->fetchAssociative('SHOW FULL COLUMNS FROM `' . $table . "` LIKE 'unit'");
    }

    ($this->runPatch)();
    foreach (['product' => $productDefault, 'client_order' => $billingDefault, 'invoice_item' => $billingDefault] as $table => $default) {
        $column = $this->connection->fetchAssociative('SHOW FULL COLUMNS FROM `' . $table . "` LIKE 'unit'");
        expect($column['Collation'])->toBe('utf8mb4_general_ci');
        unset($column['Collation'], $before[$table]['Collation']);
        expect($column)->toBe($before[$table])
            ->and($this->connection->fetchOne('SELECT unit FROM `' . $table . '`'))->toBe($default);
        $this->connection->insert($table, ['id' => 2]);
        expect($this->connection->fetchOne('SELECT unit FROM `' . $table . '` WHERE id = 2'))->toBe($default);
        $this->connection->executeStatement('UPDATE `' . $table . '` SET unit = ?', ['📦 𠮷']);
        expect($this->connection->fetchOne('SELECT unit FROM `' . $table . '` WHERE id = 1'))->toBe('📦 𠮷');
    }
    expect($this->patcher->getDi()['logger']->calls)->toBe([]);
    $alters = $this->pdo->alters;
    ($this->runPatch)();
    expect($this->pdo->alters)->toBe($alters);
})->with([
    'standard defaults' => ['product', null],
    'custom literal defaults' => ["boîtes'\\cases", "unit's\\box"],
    'empty defaults' => ['', ''],
]);

test('Unicode repair leaves expression defaults and indexed unit customizations unchanged', function (): void {
    $this->pdo->exec("CREATE TABLE product (id INT PRIMARY KEY, unit VARCHAR(50) NOT NULL DEFAULT (concat('pro', 'duct'))) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
    $this->pdo->exec("CREATE TABLE invoice_item (id INT PRIMARY KEY, unit VARCHAR(100) NULL DEFAULT 'hour', KEY unit_idx (unit)) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
    foreach (['product', 'invoice_item'] as $table) {
        $before[$table] = $this->connection->fetchAssociative('SHOW FULL COLUMNS FROM `' . $table . "` LIKE 'unit'");
    }
    ($this->runPatch)();
    foreach (['product' => 'product', 'invoice_item' => 'hour'] as $table => $default) {
        expect($this->connection->fetchAssociative('SHOW FULL COLUMNS FROM `' . $table . "` LIKE 'unit'"))->toBe($before[$table]);
        $this->connection->insert($table, ['id' => 1]);
        expect($this->connection->fetchOne('SELECT unit FROM `' . $table . '`'))->toBe($default);
    }
    expect($this->patcher->getDi()['logger']->calls)->toHaveCount(2);
});

test('news charset patch repairs legacy text and preserves data, definitions and slug uniqueness', function (): void {
    $this->pdo->exec("CREATE TABLE post (
        id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL COMMENT 'Announcement title',
        description TEXT NULL DEFAULT NULL,
        content TEXT NULL DEFAULT NULL COLLATE utf8_bin COMMENT 'Editor content',
        slug VARCHAR(255) NOT NULL UNIQUE,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        admin_id BIGINT NULL,
        image VARCHAR(255) NULL,
        section VARCHAR(255) NULL,
        publish_at DATETIME NULL,
        published_at DATETIME NULL,
        expires_at DATETIME NULL,
        created_at DATETIME NULL,
        updated_at DATETIME NULL
    ) ENGINE=InnoDB ROW_FORMAT=COMPACT DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
    $original = 'Résumé 日本語';
    $this->connection->insert('post', ['title' => $original, 'content' => $original, 'slug' => 'example']);
    foreach (['title', 'description', 'content', 'section'] as $column) {
        expect(fn () => $this->connection->executeStatement('UPDATE post SET ' . $column . ' = ?', ['🚀 🎧 🔒']))
            ->toThrow(Doctrine\DBAL\Exception\DriverException::class);
    }

    ($this->runPatch)();
    $columns = $this->pdo->query('SHOW FULL COLUMNS FROM post')->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    expect($columns['content']['Type'])->toBe('text')
        ->and($columns['content']['Null'])->toBe('YES')
        ->and($columns['content']['Default'])->toBeNull()
        ->and($columns['content']['Comment'])->toBe('Editor content')
        ->and($columns['content']['Collation'])->toBe('utf8mb4_bin')
        ->and($columns['title']['Type'])->toBe('varchar(255)')
        ->and($columns['title']['Null'])->toBe('NO')
        ->and($columns['title']['Comment'])->toBe('Announcement title')
        ->and($columns['slug']['Collation'])->not->toStartWith('utf8mb4')
        ->and($this->connection->fetchOne('SELECT content FROM post'))->toBe($original)
        ->and($this->connection->fetchOne('SELECT description FROM post'))->toBeNull();

    $em = EntityManagerFactory::create($this->connection);
    $post = (new Box\Mod\News\Entity\Post('🚀 Release', 'release'))
        ->setDescription('🎧 News')->setContent('🚀 🎧 🔒')->setSection('🔒 Security');
    $em->persist($post);
    $em->flush();
    $id = $post->getId();
    $em->clear();
    $saved = $em->find(Box\Mod\News\Entity\Post::class, $id);
    expect($saved->getTitle())->toBe('🚀 Release')
        ->and($saved->getDescription())->toBe('🎧 News')
        ->and($saved->getContent())->toBe('🚀 🎧 🔒')
        ->and($saved->getSection())->toBe('🔒 Security');

    expect(fn () => $this->connection->insert('post', ['title' => 'Duplicate', 'slug' => 'EXAMPLE']))
        ->toThrow(Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);
    ($this->runPatch)();
    expect($this->pdo->query('SHOW FULL COLUMNS FROM post')->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC))->toBe($columns);
});

test('Unicode repair leaves missing tables uncreated', function (): void {
    ($this->runPatch)();
    expect($this->pdo->query('SHOW TABLES')->fetchAll())->toBe([]);
});

test('fresh installation supports supplementary Unicode in a legacy database without running patches', function (): void {
    $databaseCharset = $this->connection->fetchOne('SELECT @@character_set_database');
    expect($databaseCharset)->toBeIn(['utf8', 'utf8mb3']);

    $em = EntityManagerFactory::create($this->connection);
    SchemaInstaller::createSchema($em);
    InstallSeeder::seedContent(
        $this->connection,
        $em,
        (new Filesystem())->readFile(Path::join(PATH_ROOT, 'install', 'sql', 'content.sql')),
        new DateTimeImmutable(),
    );

    expect((int) $this->connection->fetchOne("SELECT value FROM setting WHERE param = 'last_patch'"))->toBe($this->patcher->latestPatchLevel());
    foreach ($this->connection->fetchFirstColumn('SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()') as $collation) {
        expect($collation)->toStartWith('utf8mb4_');
    }
    $post = (new Box\Mod\News\Entity\Post('🚀 Release', 'release'))
        ->setDescription('🎧 News')->setContent('🚀 🎧 🔒')->setSection('🔒 Security');
    $em->persist($post);
    $em->flush();
    $id = $post->getId();
    $em->clear();
    $saved = $em->find(Box\Mod\News\Entity\Post::class, $id);
    expect($saved->getTitle())->toBe('🚀 Release')
        ->and($saved->getDescription())->toBe('🎧 News')
        ->and($saved->getContent())->toBe('🚀 🎧 🔒')
        ->and($saved->getSection())->toBe('🔒 Security')
        ->and($this->connection->fetchOne('SELECT @@character_set_database'))->toBe($databaseCharset);
});
