<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Doctrine\DriverManagerFactory;
use FOSSBilling\UpdatePatcher;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

beforeEach(function (): void {
    $port = getenv('CHARSET_TEST_MYSQL_PORT');
    if (!$port) {
        $this->markTestSkipped('Set CHARSET_TEST_MYSQL_PORT for an isolated MySQL/MariaDB test server.');
    }

    $this->database = 'fb_charset_test_' . bin2hex(random_bytes(6));
    $this->pdo = new PDO('mysql:host=127.0.0.1;port=' . (int) $port . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $this->pdo->exec('CREATE DATABASE `' . $this->database . '` CHARACTER SET utf8 COLLATE utf8_general_ci');
    $this->pdo->exec('USE `' . $this->database . '`');
    $this->connection = DriverManagerFactory::getConnection([], [
        'driver' => 'pdo_mysql', 'host' => '127.0.0.1', 'port' => (int) $port,
        'name' => $this->database, 'user' => 'root', 'password' => '',
    ]);
    $di = new Pimple\Container();
    $di['pdo'] = $this->pdo;
    $di['dbal'] = $this->connection;
    $this->patcher = new UpdatePatcher();
    $this->patcher->setDi($di);
    $this->runPatch = (new ReflectionMethod($this->patcher, 'patch128'))->getClosure($this->patcher);
});

afterEach(function (): void {
    if (isset($this->pdo)) {
        $this->pdo->exec('DROP DATABASE `' . $this->database . '`');
        $this->connection->close();
    }
});

test('charset migration repairs legacy page text and preserves definitions, data and indexed slugs', function (): void {
    $this->pdo->exec("CREATE TABLE custom_pages (
        id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL COMMENT 'Page title',
        description VARCHAR(555) NULL DEFAULT NULL,
        keywords VARCHAR(555) NOT NULL,
        content TEXT NOT NULL COLLATE utf8_bin COMMENT 'Editor content',
        slug VARCHAR(255) NOT NULL UNIQUE
    ) ENGINE=InnoDB ROW_FORMAT=COMPACT DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
    $insert = $this->pdo->prepare('INSERT INTO custom_pages (title, description, keywords, content, slug) VALUES (?, ?, ?, ?, ?)');
    $original = 'Zażółć gęślą jaźń';
    $insert->execute([$original, null, $original, $original, 'example']);

    expect(fn () => $this->connection->executeStatement('UPDATE custom_pages SET content = ?', ['🖥️']))->toThrow(Doctrine\DBAL\Exception\DriverException::class);
    ($this->runPatch)();

    $columns = $this->pdo->query('SHOW FULL COLUMNS FROM custom_pages')->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    expect($columns['content']['Type'])->toBe('text')
        ->and($columns['content']['Null'])->toBe('NO')
        ->and($columns['content']['Comment'])->toBe('Editor content')
        ->and($columns['content']['Collation'])->toBe('utf8mb4_bin')
        ->and($columns['title']['Type'])->toBe('varchar(255)')
        ->and($columns['title']['Comment'])->toBe('Page title')
        ->and($columns['description']['Null'])->toBe('YES')
        ->and($columns['description']['Default'])->toBeNull()
        ->and($columns['slug']['Collation'])->not->toStartWith('utf8mb4')
        ->and($this->connection->fetchOne('SELECT content FROM custom_pages'))->toBe($original);

    foreach (['title', 'description', 'keywords', 'content'] as $column) {
        $this->connection->executeStatement('UPDATE custom_pages SET ' . $column . ' = ?', ['🖥️']);
        expect($this->connection->fetchOne('SELECT ' . $column . ' FROM custom_pages'))->toBe('🖥️');
    }
    expect(fn () => $insert->execute(['other', null, '', '', 'EXAMPLE']))->toThrow(PDOException::class);
    ($this->runPatch)();
    expect($this->pdo->query('SHOW FULL COLUMNS FROM custom_pages')->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC))->toBe($columns);
});

test('charset migration skips customized defaults, indexes, generated fields and other charsets', function (): void {
    $this->pdo->exec("CREATE TABLE custom_pages (
        id INT PRIMARY KEY,
        title VARCHAR(255) NOT NULL DEFAULT 'custom',
        description VARCHAR(100) NOT NULL UNIQUE,
        keywords VARCHAR(255) GENERATED ALWAYS AS (title) STORED,
        content TEXT CHARACTER SET latin1 NOT NULL
    ) DEFAULT CHARSET=utf8");
    $logger = Mockery::mock();
    $logger->shouldReceive('withChannel')->with('update')->times(3)->andReturnSelf();
    $logger->shouldReceive('log')->with('warning', Mockery::type('string'), [])->times(3);
    $this->patcher->getDi()['logger'] = $logger;
    $before = $this->pdo->query('SHOW FULL COLUMNS FROM custom_pages')->fetchAll(PDO::FETCH_ASSOC);
    ($this->runPatch)();
    expect($this->pdo->query('SHOW FULL COLUMNS FROM custom_pages')->fetchAll(PDO::FETCH_ASSOC))->toBe($before);
});

test('charset migration is harmless when Custompages is not installed', function (): void {
    ($this->runPatch)();
    expect($this->pdo->query('SHOW TABLES')->fetchAll())->toBe([]);
});

test('new Doctrine page tables and connections support supplementary Unicode', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([PATH_MODS . '/Custompages/Entity'], true, cache: new ArrayAdapter());
    if (PHP_VERSION_ID >= 80400) {
        $config->enableNativeLazyObjects(true);
    } else {
        $config->setProxyDir(PATH_CACHE . '/doctrine/proxies');
        $config->setProxyNamespace('CharsetTestProxies');
    }
    $em = new EntityManager($this->connection, $config);
    $metadata = $em->getClassMetadata(Box\Mod\Custompages\Entity\CustomPage::class);
    (new SchemaTool($em))->createSchema([$metadata]);
    $page = (new Box\Mod\Custompages\Entity\CustomPage())
        ->setTitle('🖥️')->setDescription('🖥️')->setKeywords('🖥️')->setContent('🖥️')->setSlug('example');
    $em->persist($page);
    $em->flush();
    $em->clear();
    expect($em->find(Box\Mod\Custompages\Entity\CustomPage::class, $page->getId())->getContent())->toBe('🖥️');
});
