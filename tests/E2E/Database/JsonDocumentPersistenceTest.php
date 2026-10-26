<?php

declare(strict_types=1);

use Box\Mod\Massmailer\Entity\MassmailerMessage;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Doctrine\JsonAudit;
use FOSSBilling\Doctrine\Type\JsonDocumentType;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

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
    JsonDocumentType::register();
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
    if ($driver === 'pdo_mysql') {
        // The JSON migration must work even before patch 133 repairs legacy charsets.
        $this->connection->executeStatement('ALTER TABLE mod_massmailer MODIFY COLUMN `filter` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL');
    }
});

afterEach(function (): void {
    if (isset($this->connection)) {
        $this->em->close();
        $this->connection->close();
    }
    if (isset($this->adminConnection)) {
        $this->adminConnection->executeStatement('DROP DATABASE ' . $this->adminConnection->quoteSingleIdentifier($this->database));
        $this->adminConnection->close();
    }
});

test('JSON hydration and unrelated updates preserve legacy bytes on every supported database', function (): void {
    foreach ([null, '', '{broken-secret', 'null', '42', '"scalar"', '"{\\"a\\":1}"', '{}', '[]', '{ "id": "001", "n": 1.0, "label": "Résumé" }'] as $raw) {
        $message = (new MassmailerMessage())->setFilter($raw)->setSubject('Before');
        $this->em->persist($message);
        $this->em->flush();
        $id = $message->getId();
        $this->em->clear();
        $loaded = $this->em->find(MassmailerMessage::class, $id);
        expect($loaded->getFilter())->toBe($raw);
        $loaded->getFilterData();
        $loaded->setSubject('After');
        $this->em->flush();
        expect($this->connection->fetchOne('SELECT ' . $this->connection->quoteSingleIdentifier('filter') . ' FROM mod_massmailer WHERE id = ?', [$id]))->toBe($raw);
    }
    $before = $this->connection->fetchAllAssociative('SELECT * FROM mod_massmailer ORDER BY id');
    $issues = iterator_to_array((new JsonAudit())->inspect($this->connection, 'mod_massmailer', 2));
    expect(array_column($issues, 'issue'))->toBe(['empty_string', 'invalid_json', 'unexpected_root', 'unexpected_root', 'double_encoded_json'])
        ->and($this->connection->fetchAllAssociative('SELECT * FROM mod_massmailer ORDER BY id'))->toBe($before);
});

test('structured JSON updates persist once, retain Unicode and track replacements', function (): void {
    $data = ['id' => '001', 'enabled' => false, 'nested' => ['label' => '🚀'], 'list' => []];
    $message = (new MassmailerMessage())->setFilterData($data);
    $this->em->persist($message);
    $this->em->flush();
    $id = $message->getId();
    $this->em->clear();
    $loaded = $this->em->find(MassmailerMessage::class, $id);
    expect($loaded->getFilterData())->toBe($data)
        ->and($loaded->getFilter())->toBe(json_encode($data, JSON_THROW_ON_ERROR));
    $copy = $loaded->getFilterData();
    $copy['nested']['label'] = 'Changed';
    $this->em->flush();
    expect($this->connection->fetchOne('SELECT ' . $this->connection->quoteSingleIdentifier('filter') . ' FROM mod_massmailer WHERE id = ?', [$id]))
        ->toBe(json_encode($data, JSON_THROW_ON_ERROR));
    $loaded->setFilterData($copy);
    $this->em->flush();
    $this->em->clear();
    expect($this->em->find(MassmailerMessage::class, $id)->getFilterData())->toBe($copy);
    $loaded = $this->em->find(MassmailerMessage::class, $id);
    foreach ([new stdClass(), []] as $empty) {
        $loaded->setFilterData($empty);
        $this->em->flush();
        expect($this->connection->fetchOne('SELECT ' . $this->connection->quoteSingleIdentifier('filter') . ' FROM mod_massmailer WHERE id = ?', [$id]))
            ->toBe(json_encode($empty, JSON_THROW_ON_ERROR));
    }
});
