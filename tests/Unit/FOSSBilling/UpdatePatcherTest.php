<?php

declare(strict_types=1);

use FOSSBilling\UpdatePatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

test('downloadable file migration follows the client balance gateway repair', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 89);

    expect($patches)->toHaveKey(90)
        ->and($patches[90][1])->toBe('patch90')
        ->and($patches)->toHaveKey(91)
        ->and($patches[91][1])->toBe('patch91')
        ->and($patches)->toHaveKey(92)
        ->and($patches[92][1])->toBe('patch92')
        ->and($patches)->toHaveKey(93)
        ->and($patches[93][1])->toBe('patch93');
});

test('stock reservation backfill patch follows the TLD periods patch', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 99);

    expect($patches)->toHaveKey(100)
        ->and($patches[100][1])->toBe('patch100');
});

test('unpaid invoice id index patch follows the stock reservation backfill patch', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 100);

    expect($patches)->toHaveKey(101)
        ->and($patches[101][1])->toBe('patch101');
});

test('manual currency rate patch follows the currency formatting patch', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 93);

    expect($patches)->toHaveKey(94)
        ->and($patches[94][1])->toBe('patch94');
});

test('suspension grace patch follows the manual currency rate patch', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 94);

    expect($patches)->toHaveKey(95)
        ->and($patches[95][1])->toBe('patch95');
});

test('client balance unique credit patch follows the suspension grace patch', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 95);

    expect($patches)->toHaveKey(96)
        ->and($patches[96][1])->toBe('patch96');
});

test('invoice item attempts patch follows the client balance credit patch', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 96);

    expect($patches)->toHaveKey(97)
        ->and($patches[97][1])->toBe('patch97');
});

test('invoice item attempts patch adds the column for existing installs', function (): void {
    $columns = Mockery::mock(PDOStatement::class);
    $columns->expects('execute')->with([])->andReturnTrue();
    $columns->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $addColumn = Mockery::mock(PDOStatement::class);
    $addColumn->expects('execute')->with([])->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `invoice_item`')->andReturn($columns);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `invoice_item` ADD COLUMN `attempts` INT NOT NULL DEFAULT \'0\' AFTER `taxed`')
        ->andReturn($addColumn);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch97'))->invoke($patcher);
});

test('invoice item attempts patch is a no-op when the column already exists', function (): void {
    $columns = Mockery::mock(PDOStatement::class);
    $columns->expects('execute')->with([])->andReturnTrue();
    $columns->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([['Field' => 'attempts']]);

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `invoice_item`')->andReturn($columns);
    $pdo->shouldNotReceive('prepare')->with('ALTER TABLE `invoice_item` ADD COLUMN `attempts` INT NOT NULL DEFAULT \'0\' AFTER `taxed`');

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch97'))->invoke($patcher);
});

test('fresh installs start at the latest patch level', function (): void {
    $content = file_get_contents(Path::join(PATH_ROOT, 'install', 'sql', 'content.sql'));
    expect($content)->toBeString();

    preg_match("/\\(1,'last_patch','(\\d+)'/", $content, $matches);

    expect((int) ($matches[1] ?? 0))->toBe((new UpdatePatcher())->latestPatchLevel());
});

test('fresh installs index order suspension candidates', function (): void {
    $filesystem = new Filesystem();
    $structure = $filesystem->readFile(Path::join(PATH_ROOT, 'install', 'sql', 'structure.sql'));

    expect($structure)->toContain('KEY `client_order_status_expires_at_idx` (`status`, `expires_at`)');
});

test('fresh installs index unpaid invoice lookups', function (): void {
    $filesystem = new Filesystem();
    $structure = $filesystem->readFile(Path::join(PATH_ROOT, 'install', 'sql', 'structure.sql'));

    expect($structure)->toContain('KEY `client_order_unpaid_invoice_id_idx` (`unpaid_invoice_id`)');
});

test('fresh installs constrain client balance to one credit per invoice item', function (): void {
    $filesystem = new Filesystem();
    $structure = $filesystem->readFile(Path::join(PATH_ROOT, 'install', 'sql', 'structure.sql'));

    expect($structure)->toContain('`invoice_item_id` bigint(20) DEFAULT NULL')
        ->and($structure)->toContain('UNIQUE KEY `uniq_invoice_item_credit` (`invoice_item_id`)');
});

test('fresh installs use Symfony session storage', function (): void {
    $filesystem = new Filesystem();
    $structure = $filesystem->readFile(Path::join(PATH_ROOT, 'install', 'sql', 'structure.sql'));

    expect($structure)->toContain('`id` varbinary(128) NOT NULL')
        ->and($structure)->toContain('`content` blob NOT NULL')
        ->and($structure)->toContain('`lifetime` int(11) unsigned NOT NULL')
        ->and($structure)->toContain('PRIMARY KEY (`id`)')
        ->and($structure)->toContain('KEY `session_lifetime_idx` (`lifetime`)');
});

test('session storage migration follows the obsolete core file cleanup', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 104);

    expect($patches)->toHaveKey(105)
        ->and($patches[105][1])->toBe('patch105')
        ->and($patches)->toHaveKey(106)
        ->and($patches[106][1])->toBe('patch106');
});

test('obsolete empty directories are removed without deleting hidden files', function (): void {
    $filesystem = new Filesystem();
    $root = Path::join(sys_get_temp_dir(), 'fossbilling-update-patcher-' . bin2hex(random_bytes(8)));
    $emptyDirectory = Path::join($root, 'empty');
    $hiddenFileDirectory = Path::join($root, 'hidden-file');
    $hiddenFile = Path::join($hiddenFileDirectory, '.keep');

    $filesystem->mkdir([$emptyDirectory, $hiddenFileDirectory]);
    $filesystem->dumpFile($hiddenFile, 'keep');

    try {
        $patcher = new UpdatePatcher();
        (new ReflectionMethod($patcher, 'removeEmptyDirectories'))->invoke($patcher, [$emptyDirectory, $hiddenFileDirectory]);

        expect($filesystem->exists($emptyDirectory))->toBeFalse()
            ->and($filesystem->exists($hiddenFileDirectory))->toBeTrue()
            ->and($filesystem->exists($hiddenFile))->toBeTrue();
    } finally {
        $filesystem->remove($root);
    }
});

test('client balance unique credit patch adds column and index for existing installs', function (): void {
    $balanceColumns = Mockery::mock(PDOStatement::class);
    $balanceColumns->expects('execute')->with([])->andReturnTrue();
    $balanceColumns->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $balanceIndexes = Mockery::mock(PDOStatement::class);
    $balanceIndexes->expects('execute')->with([])->andReturnTrue();
    $balanceIndexes->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $addColumn = Mockery::mock(PDOStatement::class);
    $addColumn->expects('execute')->with([])->andReturnTrue();

    $addIndex = Mockery::mock(PDOStatement::class);
    $addIndex->expects('execute')->with([])->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `client_balance`')->andReturn($balanceColumns);
    $pdo->expects('prepare')->with('SHOW INDEX FROM `client_balance`')->andReturn($balanceIndexes);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `client_balance` ADD COLUMN `invoice_item_id` BIGINT DEFAULT NULL AFTER `rel_id`')
        ->andReturn($addColumn);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `client_balance` ADD UNIQUE INDEX `uniq_invoice_item_credit` (`invoice_item_id`)')
        ->andReturn($addIndex);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch96'))->invoke($patcher);
});

test('suspension grace patch indexes existing order tables', function (): void {
    $productColumns = Mockery::mock(PDOStatement::class);
    $productColumns->expects('execute')->with([])->andReturnTrue();
    $productColumns->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Field' => 'suspension_grace_days'],
    ]);

    $orderColumns = Mockery::mock(PDOStatement::class);
    $orderColumns->expects('execute')->with([])->andReturnTrue();
    $orderColumns->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Field' => 'suspension_grace_days'],
    ]);

    $orderIndexes = Mockery::mock(PDOStatement::class);
    $orderIndexes->expects('execute')->with([])->andReturnTrue();
    $orderIndexes->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $addIndex = Mockery::mock(PDOStatement::class);
    $addIndex->expects('execute')->with([])->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `product`')->andReturn($productColumns);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `client_order`')->andReturn($orderColumns);
    $pdo->expects('prepare')->with('SHOW INDEX FROM `client_order`')->andReturn($orderIndexes);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `client_order` ADD INDEX `client_order_status_expires_at_idx` (`status`, `expires_at`)')
        ->andReturn($addIndex);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch95'))->invoke($patcher);
});

test('client balance gateway patch restores one-time payments', function (): void {
    $statement = Mockery::mock(PDOStatement::class);
    $statement->expects('execute')
        ->with(['gateway' => 'ClientBalance'])
        ->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')
        ->with('UPDATE pay_gateway SET allow_single = 1 WHERE gateway = :gateway AND allow_single = 0')
        ->andReturn($statement);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch91'))->invoke($patcher);
});

test('legacy email patch restores untouched 0.7.2 defaults without replacing customizations', function (): void {
    $filesystem = new Filesystem();
    $template = $filesystem->readFile(Path::join(PATH_TESTS, 'Fixtures', 'Email', 'mod_invoice_created_0.7.2.html.twig'));

    preg_match('#{%\s*block subject\s*%}(.*?){%\s*endblock\s*%}#s', $template, $subjectMatches);
    preg_match('/{%.?block content.?%}((.*?\n)+){%.?endblock.?%}/m', $template, $contentMatches);

    $subject = $subjectMatches[1] ?? '';
    $legacyContent = $contentMatches[1] ?? '';
    expect(hash('sha256', $legacyContent))->toBe('3b9677641c2eb3e8b34abae05596593a66d7014cd5ad40220c1a1ea8614e5b43');

    $selectStatement = Mockery::mock(PDOStatement::class);
    $selectStatement->expects('execute')->with([])->andReturnTrue();
    $selectStatement->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['id' => 1, 'action_code' => 'mod_invoice_created', 'subject' => $subject, 'content' => $legacyContent],
        ['id' => 2, 'action_code' => 'mod_invoice_created', 'subject' => $subject, 'content' => $legacyContent . '<p>Custom footer</p>'],
    ]);

    $updateStatement = Mockery::mock(PDOStatement::class);
    $updateStatement->expects('execute')->with(Mockery::on(function (array $params): bool {
        expect($params['id'])->toBe(1)
            ->and($params['content'])->not->toMatch('/\|\s*link\b/')
            ->and($params['content'])->not->toMatch('/\|\s*money\b/');

        return true;
    }))->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')
        ->with('SELECT id, action_code, subject, content FROM email_template WHERE is_overridden = 1 AND is_custom = 0')
        ->andReturn($selectStatement);
    $pdo->expects('prepare')
        ->with('UPDATE email_template SET is_overridden = 0, subject = :subject, content = :content WHERE id = :id')
        ->andReturn($updateStatement);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch90'))->invoke($patcher);
});

test('stock reservation backfill patch is a no-op when there is nothing to backfill', function (): void {
    $selectOrders = Mockery::mock(PDOStatement::class);
    $selectOrders->expects('execute')->with([])->andReturnTrue();
    $selectOrders->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'NOT EXISTS')))
        ->andReturn($selectOrders);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch100'))->invoke($patcher);
});

test('stock reservation backfill patch orders candidates by creation time, not id', function (): void {
    // An order's id doesn't necessarily reflect when it was created (e.g. an imported or
    // manually restored order), so "oldest first" has to mean created_at, with id only as a
    // deterministic tie-breaker for orders created at the same instant.
    $selectOrders = Mockery::mock(PDOStatement::class);
    $selectOrders->expects('execute')->with([])->andReturnTrue();
    $selectOrders->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'ORDER BY co.product_id ASC, co.created_at ASC, co.id ASC')))
        ->andReturn($selectOrders);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch100'))->invoke($patcher);
});

test('stock reservation backfill patch reserves stock for a pending order and decrements it', function (): void {
    $selectOrders = Mockery::mock(PDOStatement::class);
    $selectOrders->expects('execute')->with([])->andReturnTrue();
    $selectOrders->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['order_id' => 5, 'product_id' => 1, 'quantity' => 2],
    ]);

    $decrementStock = Mockery::mock(PDOStatement::class);
    $decrementStock->expects('execute')->with(Mockery::on(function (array $params): bool {
        expect($params[0])->toBe(5)
            ->and($params[1])->toBe(2)
            ->and($params[2])->toBeString()
            ->and($params[3])->toBe(1)
            ->and($params[4])->toBe(2);

        return true;
    }))->andReturnTrue();
    $decrementStock->expects('rowCount')->andReturn(1);

    $insertMeta = Mockery::mock(PDOStatement::class);
    $insertMeta->expects('execute')->with(Mockery::on(function (array $params): bool {
        expect($params['order_id'])->toBe(5)
            ->and($params['name'])->toBe('stock_reserved_qty')
            ->and($params['value'])->toBe('2');

        return true;
    }))->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('beginTransaction')->once()->andReturnTrue();
    $pdo->expects('commit')->once()->andReturnTrue();
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'NOT EXISTS') && !str_contains($sql, 'UPDATE')))
        ->andReturn($selectOrders);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'UPDATE product p')))
        ->andReturn($decrementStock);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'INSERT INTO client_order_meta')))
        ->andReturn($insertMeta);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch100'))->invoke($patcher);
});

test('stock reservation backfill patch skips orders with a non-positive quantity', function (): void {
    // Matches Product\Service::reserveStockForOrder(): a zero/negative quantity is never
    // reserved, not rounded up to one unit. Skipped before a transaction is even opened.
    $selectOrders = Mockery::mock(PDOStatement::class);
    $selectOrders->expects('execute')->with([])->andReturnTrue();
    $selectOrders->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['order_id' => 5, 'product_id' => 1, 'quantity' => 0],
    ]);

    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldNotReceive('beginTransaction');
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'NOT EXISTS') && !str_contains($sql, 'UPDATE')))
        ->andReturn($selectOrders);
    $pdo->shouldNotReceive('prepare')->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'UPDATE product p')));
    $pdo->shouldNotReceive('prepare')->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'INSERT INTO client_order_meta')));

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch100'))->invoke($patcher);
});

test('stock reservation backfill patch rolls back if a statement fails partway through', function (): void {
    $selectOrders = Mockery::mock(PDOStatement::class);
    $selectOrders->expects('execute')->with([])->andReturnTrue();
    $selectOrders->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['order_id' => 5, 'product_id' => 1, 'quantity' => 2],
    ]);

    $decrementStock = Mockery::mock(PDOStatement::class);
    $decrementStock->expects('execute')->andThrow(new PDOException('gone away'));

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('beginTransaction')->once()->andReturnTrue();
    $pdo->expects('rollBack')->once()->andReturnTrue();
    $pdo->shouldNotReceive('commit');
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'NOT EXISTS') && !str_contains($sql, 'UPDATE')))
        ->andReturn($selectOrders);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'UPDATE product p')))
        ->andReturn($decrementStock);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);

    expect(fn (): mixed => (new ReflectionMethod($patcher, 'patch100'))->invoke($patcher))
        ->toThrow(PDOException::class, 'gone away');
});

test('stock reservation backfill patch stops once a product is already oversold', function (): void {
    // Two pending orders for the same product, but only one unit left. Order 6 has the higher
    // id but was actually created first (e.g. an imported order) - the ORDER BY created_at
    // clause means it's the one processed first and granted the reservation via a guarded
    // decrement; order 5's decrement affects zero rows once stock is gone, so it's rolled back
    // and left exactly as it was pre-patch.
    $selectOrders = Mockery::mock(PDOStatement::class);
    $selectOrders->expects('execute')->with([])->andReturnTrue();
    $selectOrders->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['order_id' => 6, 'product_id' => 1, 'quantity' => 1],
        ['order_id' => 5, 'product_id' => 1, 'quantity' => 1],
    ]);

    $firstDecrement = Mockery::mock(PDOStatement::class);
    $firstDecrement->expects('execute')->with(Mockery::on(function (array $params): bool {
        expect($params[0])->toBe(6)->and($params[3])->toBe(1);

        return true;
    }))->andReturnTrue();
    $firstDecrement->expects('rowCount')->andReturn(1);

    $secondDecrement = Mockery::mock(PDOStatement::class);
    $secondDecrement->expects('execute')->with(Mockery::on(function (array $params): bool {
        expect($params[0])->toBe(5)->and($params[3])->toBe(1);

        return true;
    }))->andReturnTrue();
    $secondDecrement->expects('rowCount')->andReturn(0);

    $insertMeta = Mockery::mock(PDOStatement::class);
    $insertMeta->expects('execute')->with(Mockery::on(function (array $params): bool {
        // Only order 6 may be reserved; order 5's decrement never succeeded.
        expect($params['order_id'])->toBe(6);

        return true;
    }))->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('beginTransaction')->twice()->andReturnTrue();
    $pdo->expects('commit')->once()->andReturnTrue();
    $pdo->expects('rollBack')->once()->andReturnTrue();
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'NOT EXISTS') && !str_contains($sql, 'UPDATE')))
        ->andReturn($selectOrders);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'UPDATE product p')))
        ->twice()
        ->andReturn($firstDecrement, $secondDecrement);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'INSERT INTO client_order_meta')))
        ->andReturn($insertMeta);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch100'))->invoke($patcher);
});

test('unpaid invoice id index patch adds the index for existing installs', function (): void {
    $indexes = Mockery::mock(PDOStatement::class);
    $indexes->expects('execute')->with([])->andReturnTrue();
    $indexes->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $addIndex = Mockery::mock(PDOStatement::class);
    $addIndex->expects('execute')->with([])->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')->with('SHOW INDEX FROM `client_order`')->andReturn($indexes);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `client_order` ADD INDEX `client_order_unpaid_invoice_id_idx` (`unpaid_invoice_id`)')
        ->andReturn($addIndex);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch101'))->invoke($patcher);
});

test('unpaid invoice id index patch is a no-op when the index already exists', function (): void {
    $indexes = Mockery::mock(PDOStatement::class);
    $indexes->expects('execute')->with([])->andReturnTrue();
    $indexes->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Key_name' => 'client_order_unpaid_invoice_id_idx'],
    ]);

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')->with('SHOW INDEX FROM `client_order`')->andReturn($indexes);
    $pdo->shouldNotReceive('prepare')->with('ALTER TABLE `client_order` ADD INDEX `client_order_unpaid_invoice_id_idx` (`unpaid_invoice_id`)');

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch101'))->invoke($patcher);
});

test('custom pages slug unique patch follows the unpaid invoice id index patch', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 101);

    expect($patches)->toHaveKey(102)
        ->and($patches[102][1])->toBe('patch102');
});

test('custom pages slug unique patch is a no-op when the table does not exist', function (): void {
    $tableExists = Mockery::mock(PDOStatement::class);
    $tableExists->expects('execute')->with(['table' => 'custom_pages'])->andReturnTrue();
    $tableExists->expects('fetchColumn')->andReturn(false);

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')
        ->with('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1')
        ->andReturn($tableExists);
    $pdo->shouldNotReceive('prepare')->with('ALTER TABLE `custom_pages` ADD UNIQUE INDEX `uniq_custom_pages_slug` (`slug`)');

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch102'))->invoke($patcher);
});

test('custom pages slug unique patch reconciles duplicates then adds the index', function (): void {
    $tableExists = Mockery::mock(PDOStatement::class);
    $tableExists->expects('execute')->with(['table' => 'custom_pages'])->andReturnTrue();
    $tableExists->expects('fetchColumn')->andReturn('1');

    $duplicates = Mockery::mock(PDOStatement::class);
    $duplicates->expects('execute')->with([])->andReturnTrue();
    $duplicates->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['id' => 1, 'slug' => 'about'],
        ['id' => 2, 'slug' => 'about'],
    ]);

    // The first suffixed candidate ("about-2") is free, so a single probe resolves it.
    $probe = Mockery::mock(PDOStatement::class);
    $probe->expects('execute')->with(['slug' => 'about-2'])->andReturnTrue();
    $probe->expects('fetchColumn')->andReturn(false);

    $update2 = Mockery::mock(PDOStatement::class);
    $update2->expects('execute')->with(['slug' => 'about-2', 'id' => 2])->andReturnTrue();

    $indexes = Mockery::mock(PDOStatement::class);
    $indexes->expects('execute')->with([])->andReturnTrue();
    $indexes->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $addIndex = Mockery::mock(PDOStatement::class);
    $addIndex->expects('execute')->with([])->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')
        ->with('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1')
        ->andReturn($tableExists);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'SELECT c.id, c.slug')))
        ->andReturn($duplicates);
    $pdo->expects('prepare')
        ->with('SELECT id FROM custom_pages WHERE slug = :slug LIMIT 1')
        ->andReturn($probe);
    $pdo->expects('prepare')
        ->with('UPDATE custom_pages SET slug = :slug WHERE id = :id')
        ->andReturn($update2);
    $pdo->expects('prepare')->with('SHOW INDEX FROM `custom_pages`')->andReturn($indexes);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `custom_pages` ADD UNIQUE INDEX `uniq_custom_pages_slug` (`slug`)')
        ->andReturn($addIndex);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch102'))->invoke($patcher);
});

test('custom pages slug unique patch skips an occupied suffix before renaming', function (): void {
    $tableExists = Mockery::mock(PDOStatement::class);
    $tableExists->expects('execute')->with(['table' => 'custom_pages'])->andReturnTrue();
    $tableExists->expects('fetchColumn')->andReturn('1');

    $duplicates = Mockery::mock(PDOStatement::class);
    $duplicates->expects('execute')->with([])->andReturnTrue();
    $duplicates->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['id' => 1, 'slug' => 'about'],
        ['id' => 2, 'slug' => 'about'],
    ]);

    // "about-2" is already owned by a non-duplicate row (id 4), so reconciliation
    // must probe it as taken and move on to "about-3".
    $probeTaken = Mockery::mock(PDOStatement::class);
    $probeTaken->expects('execute')->with(['slug' => 'about-2'])->andReturnTrue();
    $probeTaken->expects('fetchColumn')->andReturn('4');

    $probeFree = Mockery::mock(PDOStatement::class);
    $probeFree->expects('execute')->with(['slug' => 'about-3'])->andReturnTrue();
    $probeFree->expects('fetchColumn')->andReturn(false);

    $update = Mockery::mock(PDOStatement::class);
    $update->expects('execute')->with(['slug' => 'about-3', 'id' => 2])->andReturnTrue();

    $indexes = Mockery::mock(PDOStatement::class);
    $indexes->expects('execute')->with([])->andReturnTrue();
    $indexes->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $addIndex = Mockery::mock(PDOStatement::class);
    $addIndex->expects('execute')->with([])->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')
        ->with('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1')
        ->andReturn($tableExists);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'SELECT c.id, c.slug')))
        ->andReturn($duplicates);
    $pdo->expects('prepare')
        ->with('SELECT id FROM custom_pages WHERE slug = :slug LIMIT 1')
        ->twice()
        ->andReturn($probeTaken, $probeFree);
    $pdo->expects('prepare')
        ->with('UPDATE custom_pages SET slug = :slug WHERE id = :id')
        ->andReturn($update);
    $pdo->expects('prepare')->with('SHOW INDEX FROM `custom_pages`')->andReturn($indexes);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `custom_pages` ADD UNIQUE INDEX `uniq_custom_pages_slug` (`slug`)')
        ->andReturn($addIndex);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch102'))->invoke($patcher);
});

test('custom pages slug unique patch truncates long duplicate slugs to fit varchar 255', function (): void {
    $longSlug = str_repeat('a', 255);

    $tableExists = Mockery::mock(PDOStatement::class);
    $tableExists->expects('execute')->with(['table' => 'custom_pages'])->andReturnTrue();
    $tableExists->expects('fetchColumn')->andReturn('1');

    $duplicates = Mockery::mock(PDOStatement::class);
    $duplicates->expects('execute')->with([])->andReturnTrue();
    $duplicates->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['id' => 1, 'slug' => $longSlug],
        ['id' => 2, 'slug' => $longSlug],
    ]);

    // Candidate is the base truncated to leave room for "-2": 253 a's + "-2" = 255 chars.
    $expectedSlug = str_repeat('a', 253) . '-2';

    $probe = Mockery::mock(PDOStatement::class);
    $probe->expects('execute')->with(['slug' => $expectedSlug])->andReturnTrue();
    $probe->expects('fetchColumn')->andReturn(false);

    $update = Mockery::mock(PDOStatement::class);
    $update->expects('execute')->with(['slug' => $expectedSlug, 'id' => 2])->andReturnTrue();

    $indexes = Mockery::mock(PDOStatement::class);
    $indexes->expects('execute')->with([])->andReturnTrue();
    $indexes->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $addIndex = Mockery::mock(PDOStatement::class);
    $addIndex->expects('execute')->with([])->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')
        ->with('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1')
        ->andReturn($tableExists);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'SELECT c.id, c.slug')))
        ->andReturn($duplicates);
    $pdo->expects('prepare')
        ->with('SELECT id FROM custom_pages WHERE slug = :slug LIMIT 1')
        ->andReturn($probe);
    $pdo->expects('prepare')
        ->with('UPDATE custom_pages SET slug = :slug WHERE id = :id')
        ->andReturn($update);
    $pdo->expects('prepare')->with('SHOW INDEX FROM `custom_pages`')->andReturn($indexes);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `custom_pages` ADD UNIQUE INDEX `uniq_custom_pages_slug` (`slug`)')
        ->andReturn($addIndex);

    expect(strlen($expectedSlug))->toBe(255);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch102'))->invoke($patcher);
});

test('custom pages slug unique patch is a no-op when the index already exists', function (): void {
    $tableExists = Mockery::mock(PDOStatement::class);
    $tableExists->expects('execute')->with(['table' => 'custom_pages'])->andReturnTrue();
    $tableExists->expects('fetchColumn')->andReturn('1');

    $duplicates = Mockery::mock(PDOStatement::class);
    $duplicates->expects('execute')->with([])->andReturnTrue();
    $duplicates->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $indexes = Mockery::mock(PDOStatement::class);
    $indexes->expects('execute')->with([])->andReturnTrue();
    $indexes->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Key_name' => 'uniq_custom_pages_slug'],
    ]);

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')
        ->with('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1')
        ->andReturn($tableExists);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'SELECT c.id, c.slug')))
        ->andReturn($duplicates);
    $pdo->expects('prepare')->with('SHOW INDEX FROM `custom_pages`')->andReturn($indexes);
    $pdo->shouldNotReceive('prepare')->with('ALTER TABLE `custom_pages` ADD UNIQUE INDEX `uniq_custom_pages_slug` (`slug`)');

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch102'))->invoke($patcher);
});

test('client group patch follows the money column decimal patch', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 103);

    expect($patches)->toHaveKey(104)
        ->and($patches[104][1])->toBe('patch104');
});

test('client group patch normalizes legacy zero group ids to null', function (): void {
    $statement = Mockery::mock(PDOStatement::class);
    $statement->expects('execute')->with([])->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')
        ->with('UPDATE `client` SET `client_group_id` = NULL WHERE `client_group_id` = 0;')
        ->andReturn($statement);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch104'))->invoke($patcher);
});
