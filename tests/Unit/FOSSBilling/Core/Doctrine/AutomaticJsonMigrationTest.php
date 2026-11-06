<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use FOSSBilling\Core\Doctrine\EntityManagerFactory;
use FOSSBilling\Core\Doctrine\JsonMigration;
use FOSSBilling\Core\Security\Crypt;

function automaticJsonConnection(): Doctrine\DBAL\Connection
{
    return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
}

test('automatic migration encrypts changed originals, resumes interrupted work and skips completed work', function (): void {
    $c = automaticJsonConnection();
    $c->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY, config TEXT)');
    $c->insert('product', ['id' => 1, 'config' => '']);
    $c->insert('product', ['id' => 2, 'config' => ' ']);
    $c->executeStatement("CREATE TRIGGER interrupt_json BEFORE UPDATE ON product WHEN OLD.id = 2 BEGIN SELECT RAISE(ABORT, 'interrupted'); END");
    $migration = new JsonMigration();
    $em = EntityManagerFactory::create($c);
    $crypt = new Crypt();
    expect(fn () => $migration->automatic($em, $crypt))->toThrow(FOSSBilling\Core\Doctrine\JsonMigrationException::class);
    expect($c->fetchFirstColumn('SELECT config FROM product ORDER BY id'))->toBe([null, ' ']);
    $journal = new FOSSBilling\Core\Doctrine\JsonMigrationJournal($em, $crypt);
    expect($journal->find('original:product.config:1')['base64'])->toBe('')
        ->and($journal->find('original:product.config:2'))->toBeNull();
    $c->executeStatement('DROP TRIGGER interrupt_json');
    expect($migration->automatic($em, $crypt)['empty_values'])->toBe(1)
        ->and($migration->automatic($em, $crypt))->toBe(['columns' => 0, 'empty_values' => 0])
        ->and((int) $c->fetchOne('SELECT COUNT(*) FROM json_migration_record'))->toBe(1)
        ->and($journal->find('original:product.config:1'))->toBeNull();
});

test('form defaults preserve text and false-like values while checkbox collections become JSON arrays', function (): void {
    $c = automaticJsonConnection();
    $c->executeStatement('CREATE TABLE form_field (id INTEGER PRIMARY KEY, type TEXT, options TEXT, default_value VARCHAR(255))');
    $values = [['text', 'false'], ['text', '0'], ['textarea', '{"literal":true}'], ['checkbox', '{"0":"a","1":"b"}'], ['text', ''], ['text', str_repeat('x', 255)]];
    foreach ($values as $id => [$type, $value]) {
        $c->insert('form_field', ['id' => $id, 'type' => $type, 'options' => '{}', 'default_value' => $value]);
    }
    $migration = new JsonMigration();
    $em = EntityManagerFactory::create($c);
    $migration->automatic($em, new Crypt());
    $actual = array_map(fn ($v) => json_decode($v, true, 512, JSON_THROW_ON_ERROR), $c->fetchFirstColumn('SELECT default_value FROM form_field ORDER BY id'));
    expect($actual)->toBe(['false', '0', '{"literal":true}', ['a', 'b'], '', str_repeat('x', 255)]);
    $migration->automatic($em, new Crypt());
    expect(array_map(fn ($v) => json_decode($v, true), $c->fetchFirstColumn('SELECT default_value FROM form_field ORDER BY id')))->toBe($actual);
});

test('extension configuration is re-encrypted without changing unrelated metadata or plaintext meaning', function (): void {
    $c = automaticJsonConnection();
    $c->executeStatement('CREATE TABLE extension_meta (id INTEGER PRIMARY KEY, extension TEXT, meta_key TEXT, meta_value TEXT, rel_type TEXT, rel_id TEXT, client_id BIGINT)');
    $pass = FOSSBilling\Core\System\Config::getProperty('info.salt');
    $plain = '{ "password":"secret", "unknown_extension_option":false }';
    $iv = str_repeat('x', 16);
    $v2 = 'v2:' . base64_encode($iv . openssl_encrypt($plain, Crypt::LEGACY_METHOD, hash_pbkdf2('sha256', $pass, 'fossbilling_salt', 100000, 32, true), OPENSSL_RAW_DATA, $iv));
    $c->insert('extension_meta', ['id' => 1, 'meta_key' => 'config', 'meta_value' => $v2]);
    $c->insert('extension_meta', ['id' => 2, 'meta_key' => 'config', 'meta_value' => 'plain-theme-setting', 'rel_type' => 'settings']);
    $c->insert('extension_meta', ['id' => 3, 'meta_key' => 'token', 'meta_value' => 'opaque-token-hash']);
    $crypt = new Crypt();
    $migration = new FOSSBilling\Core\Doctrine\EncryptedConfigurationMigration();
    $migration->run($c, $crypt);
    $stored = $c->fetchOne('SELECT meta_value FROM extension_meta WHERE id = 1');
    expect($stored)->toStartWith('v3:')->and($crypt->decrypt($stored))->toBe($plain)
        ->and($c->fetchOne('SELECT meta_value FROM extension_meta WHERE id = 2'))->toBe('plain-theme-setting')
        ->and($c->fetchOne('SELECT meta_value FROM extension_meta WHERE id = 3'))->toBe('opaque-token-hash');
    $migration->run($c, $crypt);
    expect($c->fetchOne('SELECT meta_value FROM extension_meta WHERE id = 1'))->toBe($stored);
});

test('an interrupted preflight resumes from its durable batch cursor before any source writes', function (): void {
    $logger = new class extends Psr\Log\AbstractLogger {
        public bool $interrupt = true;
        public array $reads = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $sql = $context['sql'] ?? '';
            if (!str_contains($sql, 'AS value') || !str_contains($sql, 'FROM "product"')) {
                return;
            }
            $cursor = isset($context['params'][1]) ? (string) $context['params'][1] : null;
            $this->reads[] = $cursor;
            if ($this->interrupt && $cursor === '500') {
                $this->interrupt = false;

                throw new RuntimeException('Simulated process interruption');
            }
        }
    };
    $configuration = new Doctrine\DBAL\Configuration();
    $configuration->setMiddlewares([new Doctrine\DBAL\Logging\Middleware($logger)]);
    $c = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
    $c->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY, config TEXT)');
    for ($id = 1; $id <= 600; ++$id) {
        $c->insert('product', ['id' => $id, 'config' => '{}']);
    }
    $em = EntityManagerFactory::create($c);
    $crypt = new Crypt();
    expect(fn () => (new JsonMigration())->automatic($em, $crypt))->toThrow(RuntimeException::class, 'Simulated process interruption');
    $journal = new FOSSBilling\Core\Doctrine\JsonMigrationJournal($em, $crypt);
    expect(base64_decode($journal->find('scan:product.config')['cursor']))->toBe('500');
    $logger->reads = [];
    (new JsonMigration())->automatic($em, $crypt);
    expect($logger->reads[0])->toBe('500')->and((int) $c->fetchOne('SELECT COUNT(*) FROM product WHERE config = ?', ['{}']))->toBe(600);
});

test('interrupted form default preparation retains originals and resumes without double encoding', function (): void {
    $c = automaticJsonConnection();
    $c->executeStatement('CREATE TABLE form_field (id INTEGER PRIMARY KEY, type TEXT, options TEXT, default_value VARCHAR(255))');
    $c->insert('form_field', ['id' => 1, 'type' => 'checkbox', 'options' => '{}', 'default_value' => '{"1":"selected"}']);
    $c->insert('form_field', ['id' => 2, 'type' => 'text', 'options' => '{}', 'default_value' => 'false']);
    $c->insert('form_field', ['id' => 3, 'type' => 'checkbox', 'options' => '{}', 'default_value' => '{"0":"a","1":"b"}']);
    $c->executeStatement("CREATE TRIGGER interrupt_defaults BEFORE UPDATE ON form_field WHEN OLD.id = 3 BEGIN SELECT RAISE(ABORT, 'interrupted'); END");
    $em = EntityManagerFactory::create($c);
    $crypt = new Crypt();
    expect(fn () => (new JsonMigration())->automatic($em, $crypt))->toThrow(FOSSBilling\Core\Doctrine\JsonMigrationException::class);
    $journal = new FOSSBilling\Core\Doctrine\JsonMigrationJournal($em, $crypt);
    expect(base64_decode($journal->find('prepared:form_field.default_value:1')['base64']))->toBe('{"1":"selected"}')
        ->and(base64_decode($journal->find('prepared:form_field.default_value:2')['base64']))->toBe('false')
        ->and(iterator_to_array((new FOSSBilling\Core\Doctrine\JsonAudit())->inspect($c, 'form_field')))->toBe([]);
    // Recovery payloads contain no plaintext default values at rest.
    foreach ($c->fetchFirstColumn('SELECT payload FROM json_migration_record') as $payload) {
        expect($payload)->toStartWith('v3:')->not->toContain('false');
    }
    $c->executeStatement('DROP TRIGGER interrupt_defaults');
    (new JsonMigration())->automatic($em, $crypt);
    expect(array_map(fn ($raw) => json_decode($raw, true, 512, JSON_THROW_ON_ERROR), $c->fetchFirstColumn('SELECT default_value FROM form_field ORDER BY id')))->toBe([[1 => 'selected'], 'false', ['a', 'b']])
        ->and((int) $c->fetchOne('SELECT COUNT(*) FROM json_migration_record'))->toBe(1);
});

test('a no-op default update still rejects a missing source row and rolls back preparation', function (): void {
    $c = automaticJsonConnection();
    $c->executeStatement('CREATE TABLE form_field (id INTEGER PRIMARY KEY, type TEXT, default_value TEXT)');
    $c->insert('form_field', ['id' => 1, 'type' => 'checkbox', 'default_value' => '{"1":"selected"}']);
    $c->executeStatement('CREATE TRIGGER remove_default BEFORE UPDATE ON form_field BEGIN DELETE FROM form_field WHERE id = OLD.id; SELECT RAISE(IGNORE); END');
    $em = EntityManagerFactory::create($c);
    $crypt = new Crypt();
    expect(fn () => (new JsonMigration())->automatic($em, $crypt))->toThrow(FOSSBilling\Core\Doctrine\JsonMigrationException::class, 'Form default changed')
        ->and($c->fetchOne('SELECT default_value FROM form_field WHERE id = 1'))->toBe('{"1":"selected"}')
        ->and((new FOSSBilling\Core\Doctrine\JsonMigrationJournal($em, $crypt))->has('prepared:form_field.default_value:1'))->toBeFalse();
});

test('current-format extension configuration is validated without re-encrypting valid documents', function (string $plain): void {
    $c = automaticJsonConnection();
    $c->executeStatement('CREATE TABLE extension_meta (id INTEGER PRIMARY KEY, extension TEXT, meta_key TEXT, meta_value TEXT, rel_type TEXT, rel_id TEXT, client_id BIGINT)');
    $crypt = new Crypt();
    $stored = $plain === 'truncated' ? 'v3:invalid' : $crypt->encrypt($plain);
    $c->insert('extension_meta', ['id' => 1, 'meta_key' => 'config', 'meta_value' => $stored]);
    $migration = new FOSSBilling\Core\Doctrine\EncryptedConfigurationMigration();
    if (in_array($plain, ['{}', '[]'], true)) {
        $migration->run($c, $crypt);
    } else {
        expect(fn () => $migration->run($c, $crypt))->toThrow(FOSSBilling\Core\Doctrine\JsonMigrationException::class);
    }
    expect($c->fetchOne('SELECT meta_value FROM extension_meta WHERE id = 1'))->toBe($stored);
})->with(['{}', '[]', 'null', '"private-secret"', '{broken', 'truncated']);

test('readiness retries retain validated batches but revalidate changed or inserted rows', function (string $change): void {
    $logger = new class extends Psr\Log\AbstractLogger {
        public bool $interrupt = true;

        public function log($level, string|Stringable $message, array $context = []): void
        {
            if ($this->interrupt && str_contains($context['sql'] ?? '', 'AS value') && str_contains($context['sql'], 'FROM "product"') && ($context['params'][1] ?? null) === 500) {
                $this->interrupt = false;

                throw new RuntimeException('Interrupted readiness validation');
            }
        }
    };
    $config = new Doctrine\DBAL\Configuration();
    $config->setMiddlewares([new Doctrine\DBAL\Logging\Middleware($logger)]);
    $c = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
    $c->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY, config TEXT)');
    for ($id = 1; $id <= 600; ++$id) {
        $c->insert('product', ['id' => $id, 'config' => '{"valid":true}']);
    }
    $journal = new FOSSBilling\Core\Doctrine\JsonMigrationJournal(EntityManagerFactory::create($c), new Crypt());
    $migration = new JsonMigration();
    expect(fn () => $migration->assertReady($c, $journal))->toThrow(RuntimeException::class, 'Interrupted readiness');
    $key = 'ready:product.config:' . hash('sha256', '1');
    expect($journal->has($key))->toBeTrue();
    $record = $c->fetchOne('SELECT payload FROM json_migration_record WHERE record_key = ?', [hash('sha256', $key)], [Doctrine\DBAL\Types\Types::BINARY]);
    if ($change === 'updated') {
        $c->update('product', ['config' => 'false'], ['id' => 1]);
    } elseif ($change === 'inserted') {
        $c->insert('product', ['id' => 0, 'config' => 'false']);
    }
    if ($change !== 'unchanged') {
        expect(fn () => $migration->assertReady($c, $journal))->toThrow(FOSSBilling\Core\Doctrine\JsonMigrationException::class);
    } else {
        $migration->assertReady($c, $journal);
        expect($c->fetchOne('SELECT payload FROM json_migration_record WHERE record_key = ?', [hash('sha256', $key)], [Doctrine\DBAL\Types\Types::BINARY]))->toBe($record);
        $migration->automatic(EntityManagerFactory::create($c), new Crypt());
        expect((int) $c->fetchOne('SELECT COUNT(*) FROM json_migration_record'))->toBe(1)
            ->and($journal->has(FOSSBilling\Core\Doctrine\JsonMigrationJournal::COMPLETE))->toBeTrue();
    }
})->with(['unchanged', 'updated', 'inserted']);
