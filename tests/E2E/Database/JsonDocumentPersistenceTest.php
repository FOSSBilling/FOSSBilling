<?php

declare(strict_types=1);

// cspell:ignore Résumé

use Box\Mod\Massmailer\Entity\MassmailerMessage;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Doctrine\JsonMigration;
use FOSSBilling\Doctrine\JsonMigrationException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;

beforeEach(function (): void {
    $driver = getenv('JSON_TEST_DRIVER') ?: 'pdo_sqlite';
    $params = ['driver' => $driver];
    if ($driver === 'pdo_sqlite') {
        $params['memory'] = true;
    } elseif (in_array($driver, ['pdo_mysql', 'pdo_pgsql'], true)) {
        $port = getenv('JSON_TEST_PORT');
        if (!$port) {
            $this->markTestSkipped('Set JSON_TEST_PORT for an isolated database server.');
        }
        $this->database = 'fb_json_test_' . bin2hex(random_bytes(6));
        $params += ['host' => '127.0.0.1', 'port' => (int) $port,
            'user' => $driver === 'pdo_mysql' ? 'root' : 'postgres', 'password' => ''];
        if ($driver === 'pdo_mysql') {
            $params['charset'] = 'utf8mb4';
        } else {
            $params['dbname'] = 'postgres';
        }
        $this->adminConnection = DriverManager::getConnection($params);
        $this->adminConnection->executeStatement('CREATE DATABASE ' . $this->adminConnection->quoteSingleIdentifier($this->database));
        $params['dbname'] = $this->database;
    } else {
        throw new InvalidArgumentException('Unsupported JSON test driver.');
    }
    $this->connection = DriverManager::getConnection($params);
    $config = ORMSetup::createAttributeMetadataConfig([PATH_MODS . '/Massmailer/Entity'], true, cache: new ArrayAdapter());
    if (PHP_VERSION_ID >= 80400) {
        $config->enableNativeLazyObjects(true);
    } else {
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('JsonDocumentTestProxies');
    }
    $this->em = new EntityManager($this->connection, $config);
    (new SchemaTool($this->em))->createSchema([$this->em->getClassMetadata(MassmailerMessage::class)]);
    $this->jsonBackupDirectory = sys_get_temp_dir() . '/fb-json-native-' . bin2hex(random_bytes(6));
    (new Filesystem())->mkdir($this->jsonBackupDirectory, 0o700);
});

afterEach(function (): void {
    if (isset($this->jsonBackupDirectory)) {
        (new Filesystem())->remove($this->jsonBackupDirectory);
    }
    if (isset($this->connection)) {
        $this->em->close();
        $this->connection->close();
    }
    if (isset($this->adminConnection)) {
        $this->adminConnection->executeStatement('DROP DATABASE ' . $this->adminConnection->quoteSingleIdentifier($this->database));
        $this->adminConnection->close();
    }
});

function makeLegacyJsonColumn(Doctrine\DBAL\Connection $connection): void
{
    $platform = $connection->getDatabasePlatform();
    if ($platform instanceof Doctrine\DBAL\Platforms\AbstractMySQLPlatform) {
        // Upgrade coverage is independent of patch 133.
        $connection->executeStatement('ALTER TABLE mod_massmailer MODIFY COLUMN `filter` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL');
    } elseif ($platform instanceof Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
        $connection->executeStatement('ALTER TABLE mod_massmailer ALTER COLUMN filter TYPE TEXT USING filter::text');
    }
}

test('standard mappings create native JSON columns and persist compatible structured values', function (): void {
    $data = ['id' => '001', 'fraction' => 1.0, 'enabled' => false, 'nested' => ['label' => '🚀'], 'list' => []];
    $message = (new MassmailerMessage())->setFilter($data)->setSubject('Before');
    $this->em->persist($message);
    $this->em->flush();
    $id = $message->getId();
    $this->em->clear();
    $loaded = $this->em->find(MassmailerMessage::class, $id);
    expect($loaded->getFilter())->toEqual($data);
    $raw = $this->connection->fetchOne('SELECT ' . $this->connection->quoteSingleIdentifier('filter') . ' FROM mod_massmailer WHERE id = ?', [$id]);
    expect(json_decode($raw, true, 512, JSON_THROW_ON_ERROR))->toEqual($data);
    $copy = $loaded->getFilter();
    $copy['nested']['label'] = 'Changed';
    $loaded->setSubject('After');
    $this->em->flush();
    expect($this->connection->fetchOne('SELECT ' . $this->connection->quoteSingleIdentifier('filter') . ' FROM mod_massmailer WHERE id = ?', [$id]))->toBe($raw);
    $loaded->setFilter($copy);
    $this->em->flush();
    $this->em->clear();
    expect($this->em->find(MassmailerMessage::class, $id)->getFilter())->toEqual($copy);
    $loaded = $this->em->find(MassmailerMessage::class, $id);
    foreach ([null, [], ['replacement' => true]] as $empty) {
        $loaded->setFilter($empty);
        $this->em->flush();
        $this->em->clear();
        $loaded = $this->em->find(MassmailerMessage::class, $id);
        expect($loaded->getFilter())->toBe($empty);
    }
    (new JsonMigration())->assertReady($this->connection);
    if (!$this->connection->getDatabasePlatform() instanceof Doctrine\DBAL\Platforms\SQLitePlatform) {
        expect($this->connection->createSchemaManager()->introspectTableByUnquotedName('mod_massmailer')->getColumn('filter')->getTypeName())
            ->toBe(Doctrine\DBAL\Types\Types::JSON);
        expect(fn () => $this->connection->insert('mod_massmailer', ['filter' => '{invalid']))->toThrow(Doctrine\DBAL\Exception\DriverException::class);
    }
});

test('legacy TEXT upgrades retain JSON meaning, archive exact originals and safely repeat', function (): void {
    makeLegacyJsonColumn($this->connection);
    $values = [null, '', " \t\n", 'null', '{}', '[]', '{ "id": "001", "n": 1.0, "label": "Résumé" }'];
    foreach ($values as $raw) {
        $this->connection->insert('mod_massmailer', ['filter' => $raw, 'subject' => 'Before']);
    }
    $file = $this->jsonBackupDirectory . '/legacy.jsonl';
    $migration = new JsonMigration();
    expect(fn () => $migration->assertReady($this->connection))->toThrow(JsonMigrationException::class);
    expect($migration->migrate($this->connection, $file))->toBe(['columns' => 1, 'empty_values' => 2]);
    $migration->assertReady($this->connection);
    $records = array_map(fn ($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR), file($file, FILE_IGNORE_NEW_LINES));
    $rows = array_values(array_filter($records, fn ($row) => array_key_exists('base64', $row)));
    expect(array_map(fn ($row) => $row['base64'] === null ? null : base64_decode($row['base64'], true), $rows))->toBe($values)
        ->and(end($records))->toBe(['complete' => true]);
    foreach ($this->em->getRepository(MassmailerMessage::class)->findBy([], ['id' => 'ASC']) as $index => $message) {
        expect($message->getFilter())->toEqual(json_decode($values[$index] ?? 'null', true));
        $message->setSubject('After');
    }
    $this->em->flush();
    $before = $this->connection->fetchAllAssociative('SELECT * FROM mod_massmailer ORDER BY id');
    expect($migration->migrate($this->connection, $this->jsonBackupDirectory . '/retry.jsonl'))->toBe(['columns' => 1, 'empty_values' => 0])
        ->and($this->connection->fetchAllAssociative('SELECT * FROM mod_massmailer ORDER BY id'))->toBe($before);
    $this->em->clear();
    $message = $this->em->getRepository(MassmailerMessage::class)->findOneBy([]);
    $message->setFilter(['unicode' => '🚀', 'id' => '001']);
    $this->em->flush();
    $this->em->clear();
    expect($this->em->find(MassmailerMessage::class, $message->getId())->getFilter())->toEqual(['unicode' => '🚀', 'id' => '001']);
});

test('unsafe legacy documents block conversion without changing earlier valid rows or exposing secrets', function (string $raw): void {
    makeLegacyJsonColumn($this->connection);
    $this->connection->insert('mod_massmailer', ['filter' => '']);
    $this->connection->insert('mod_massmailer', ['filter' => $raw]);
    $before = $this->connection->fetchAllAssociative('SELECT * FROM mod_massmailer ORDER BY id');

    try {
        (new JsonMigration())->migrate($this->connection, $this->jsonBackupDirectory . '/blocked.jsonl');
        $this->fail('Unsafe legacy JSON was accepted.');
    } catch (JsonMigrationException $exception) {
        expect($exception->getMessage())->not->toContain('private-secret', $raw);
    }
    expect($this->connection->fetchAllAssociative('SELECT * FROM mod_massmailer ORDER BY id'))->toBe($before);
})->with(['{private-secret', '{"private-secret":1,"private-secret":2}', '{"private-secret":9223372036854775808}', 'false', '42', '"scalar"', '"{\\"a\\":1}"']);

test('the full JSON-column inventory migrates while encrypted and dormant exclusions remain unchanged', function (): void {
    $c = $this->connection;
    $c->executeStatement('DROP TABLE mod_massmailer');
    $payload = '{"id":"001","fraction":1.0,"nested":{"enabled":false},"label":"Résumé"}';
    foreach (FOSSBilling\Doctrine\JsonFields::FIELDS as $table => $definition) {
        $idType = $table === 'session' ? 'VARCHAR(255)' : 'BIGINT';
        $columns = array_map(static fn ($column): string => $c->quoteSingleIdentifier($column) . ' TEXT NULL', array_keys($definition['columns']));
        if ($table === 'service_license') {
            $columns[] = 'config TEXT NULL';
        }
        $c->executeStatement('CREATE TABLE ' . $c->quoteSingleIdentifier($table) . ' (id ' . $idType . ' PRIMARY KEY, ' . implode(', ', $columns) . ')');
        foreach ([-1, 0] as $id) {
            $row = ['id' => $table === 'session' ? 'session-' . $id : $id];
            foreach (array_keys($definition['columns']) as $column) {
                $row[$column] = $id === -1 ? $payload : '';
            }
            if ($table === 'service_license') {
                $row['config'] = 'dormant-unstructured-value';
            }
            $c->insert($c->quoteSingleIdentifier($table), $row);
        }
    }
    $c->executeStatement('CREATE TABLE email_template (id BIGINT PRIMARY KEY, vars TEXT)');
    $c->insert('email_template', ['id' => 1, 'vars' => 'encrypted-private-value']);
    $migration = new JsonMigration();
    $file = $this->jsonBackupDirectory . '/inventory.jsonl';
    expect($migration->migrate($c, $file))->toBe(['columns' => 27, 'empty_values' => 26]);
    $migration->assertReady($c);
    foreach (FOSSBilling\Doctrine\JsonFields::FIELDS as $table => $definition) {
        $actual = $c->createSchemaManager()->introspectTableByUnquotedName($table);
        foreach (array_keys($definition['columns']) as $column) {
            $values = $c->fetchFirstColumn('SELECT ' . $c->quoteSingleIdentifier($column) . ' FROM ' . $c->quoteSingleIdentifier($table) . ' ORDER BY id');
            if ($table === 'form_field' && $column === 'default_value') {
                expect(json_decode($values[1], true, 512, JSON_THROW_ON_ERROR))->toBe('')
                    ->and(json_decode($values[0], true, 512, JSON_THROW_ON_ERROR))->toBe($payload);
            } else {
                expect($values[1])->toBeNull()
                    ->and(json_decode($values[0], true, 512, JSON_THROW_ON_ERROR))->toEqual(json_decode($payload, true, 512, JSON_THROW_ON_ERROR));
            }
            if (!$c->getDatabasePlatform() instanceof Doctrine\DBAL\Platforms\SQLitePlatform) {
                expect($actual->getColumn($column)->getTypeName())->toBe(Doctrine\DBAL\Types\Types::JSON);
            }
        }
    }
    expect($c->fetchFirstColumn('SELECT config FROM service_license'))->toBe(['dormant-unstructured-value', 'dormant-unstructured-value'])
        ->and($c->fetchOne('SELECT vars FROM email_template'))->toBe('encrypted-private-value')
        ->and(file_get_contents($file))->not->toContain('encrypted-private-value', 'dormant-unstructured-value');
});

test('JSON conversion and patch 133 compose in either upgrade order', function (bool $unicodeFirst): void {
    $c = $this->connection;
    if (!$c->getDatabasePlatform() instanceof Doctrine\DBAL\Platforms\AbstractMySQLPlatform) {
        $this->markTestSkipped('Patch 133 applies only to MySQL/MariaDB.');
    }
    makeLegacyJsonColumn($c);
    $c->executeStatement('ALTER TABLE mod_massmailer DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci');
    $c->executeStatement('ALTER TABLE mod_massmailer MODIFY COLUMN subject VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL');
    $original = '{"label":"Résumé","id":"001"}';
    $c->insert('mod_massmailer', ['subject' => 'Résumé', 'filter' => $original]);
    $id = (int) $c->lastInsertId();
    $patcher = new FOSSBilling\UpdatePatcher();
    $patcher->setDi(new Pimple\Container(['pdo' => $c->getNativeConnection(), 'dbal' => $c, 'logger' => new Tests\Helpers\TestLogger()]));
    $unicode = (new ReflectionMethod($patcher, 'patch133'))->getClosure($patcher);
    $json = fn () => (new JsonMigration())->migrate($c, $this->jsonBackupDirectory . '/combined.jsonl');
    if ($unicodeFirst) {
        $unicode();
        $json();
    } else {
        $json();
        $unicode();
    }
    expect($c->createSchemaManager()->introspectTableByUnquotedName('mod_massmailer')->getColumn('filter')->getTypeName())->toBe(Doctrine\DBAL\Types\Types::JSON)
        ->and(json_decode($c->fetchOne('SELECT ' . $c->quoteSingleIdentifier('filter') . ' FROM mod_massmailer WHERE id = ?', [$id]), true))->toEqual(json_decode($original, true));
    $model = $this->em->find(MassmailerMessage::class, $id);
    $model->setSubject('🚀 Release')->setFilter(['label' => '🚀', 'id' => '001']);
    $this->em->flush();
    $unicode();
    $this->em->clear();
    $saved = $this->em->find(MassmailerMessage::class, $id);
    expect($saved->getSubject())->toBe('🚀 Release')->and($saved->getFilter())->toEqual(['label' => '🚀', 'id' => '001']);
    (new JsonMigration())->assertReady($c);
})->with(['Unicode first' => true, 'JSON first' => false]);

test('automatic updates convert mixed defaults and real binary session identifiers without manual files', function (): void {
    $c = $this->connection;
    $sessionEm = FOSSBilling\Doctrine\EntityManagerFactory::create($c);
    (new SchemaTool($sessionEm))->createSchema([$sessionEm->getClassMetadata(Box\Mod\System\Entity\Session::class)]);
    $platform = $c->getDatabasePlatform();
    if ($platform instanceof Doctrine\DBAL\Platforms\AbstractMySQLPlatform) {
        $c->executeStatement('ALTER TABLE session MODIFY fingerprint TEXT NULL');
    } elseif ($platform instanceof Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
        $c->executeStatement('ALTER TABLE session ALTER COLUMN fingerprint TYPE TEXT USING fingerprint::text');
    }
    $c->executeStatement('CREATE TABLE form_field (id BIGINT PRIMARY KEY, type VARCHAR(30), options TEXT, default_value VARCHAR(255))');
    foreach ([['text', 'false'], ['text', '0'], ['text', str_repeat('x', 255)], ['checkbox', '{"0":"one","1":"two"}']] as $id => [$type, $value]) {
        $c->insert('form_field', ['id' => $id, 'type' => $type, 'options' => '{}', 'default_value' => $value]);
    }
    for ($id = 0; $id < 503; ++$id) {
        $c->insert('session', ['id' => sprintf('session-%04d', $id), 'fingerprint' => '', 'modified_at' => 1, 'content' => '', 'lifetime' => 7200], ['id' => Doctrine\DBAL\Types\Types::BINARY, 'content' => Doctrine\DBAL\Types\Types::BLOB]);
    }
    $migration = new JsonMigration();
    expect(count(iterator_to_array((new FOSSBilling\Doctrine\JsonAudit())->inspect($c, 'session', 100))))->toBe(503);
    $result = $migration->automatic($sessionEm, new FOSSBilling\Crypt());
    expect($result['empty_values'])->toBe(503)
        ->and((int) $c->fetchOne('SELECT COUNT(*) FROM session WHERE fingerprint IS NULL'))->toBe(503)
        ->and(array_map(fn ($value) => json_decode($value, true), $c->fetchFirstColumn('SELECT default_value FROM form_field ORDER BY id')))->toBe(['false', '0', str_repeat('x', 255), ['one', 'two']])
        ->and($migration->automatic($sessionEm, new FOSSBilling\Crypt()))->toBe(['columns' => 0, 'empty_values' => 0]);
});
