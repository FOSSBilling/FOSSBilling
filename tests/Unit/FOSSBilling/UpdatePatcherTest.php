<?php

declare(strict_types=1);

use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Order\Entity\Order;
use Box\Mod\System\Entity\Session;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use FOSSBilling\UpdatePatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

test('currency formatting patch follows the client balance gateway repair', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 89);

    expect($patches)->toHaveKey(90)
        ->and($patches[90][1])->toBe('patch90')
        ->and($patches)->toHaveKey(91)
        ->and($patches[91][1])->toBe('patch91')
        ->and($patches)->not->toHaveKey(92)
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

test('client balance unique credit patch follows the manual currency rate patch, skipping the removed number 95', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 94);

    expect($patches)->not->toHaveKey(95)
        ->and($patches)->toHaveKey(96)
        ->and($patches[96][1])->toBe('patch96');
});

test('client balance unique credit patch is still offered to installs already at patch level 95', function (): void {
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
    $indexes = updatePatcherEntityIndexes(Order::class);

    expect($indexes)->toHaveKey('client_order_status_expires_at_idx')
        ->and($indexes['client_order_status_expires_at_idx'])->toBe(['status', 'expires_at']);
});

test('fresh installs index unpaid invoice lookups', function (): void {
    $indexes = updatePatcherEntityIndexes(Order::class);

    expect($indexes)->toHaveKey('client_order_unpaid_invoice_id_idx')
        ->and($indexes['client_order_unpaid_invoice_id_idx'])->toBe(['unpaid_invoice_id']);
});

test('fresh installs constrain client balance to one credit per invoice item', function (): void {
    $columns = [];
    foreach ((new ReflectionClass(ClientBalance::class))->getAttributes(ORM\UniqueConstraint::class) as $attribute) {
        $constraint = $attribute->newInstance();
        $columns[$constraint->name] = $constraint->columns ?? [];
    }

    expect($columns)->toHaveKey('uniq_invoice_item_credit');
    expect($columns['uniq_invoice_item_credit'])->toBe(['invoice_item_id']);
    expect(updatePatcherEntityColumnType(ClientBalance::class, 'invoiceItemId'))->toBe(Types::BIGINT);
});

test('fresh installs use Symfony session storage', function (): void {
    $indexes = updatePatcherEntityIndexes(Session::class);

    expect($indexes)->toHaveKey('session_lifetime_idx')
        ->and($indexes['session_lifetime_idx'])->toBe(['lifetime']);
    expect(updatePatcherEntityColumnType(Session::class, 'id'))->toBe(Types::BINARY);
    expect(updatePatcherEntityColumnType(Session::class, 'content'))->toBe(Types::BLOB);
    expect(updatePatcherEntityColumnType(Session::class, 'lifetime'))->toBe(Types::INTEGER);
});

/**
 * Index name => columns declared on an entity class. Fresh-install schema
 * is generated from this metadata, so these assertions test what new
 * installs actually get.
 *
 * @param class-string $class
 *
 * @return array<string, list<string>>
 */
function updatePatcherEntityIndexes(string $class): array
{
    $indexes = [];
    foreach ((new ReflectionClass($class))->getAttributes(ORM\Index::class) as $attribute) {
        $index = $attribute->newInstance();
        $indexes[$index->name] = $index->columns ?? [];
    }

    return $indexes;
}

/**
 * Doctrine column type declared on an entity property.
 *
 * @param class-string $class
 */
function updatePatcherEntityColumnType(string $class, string $property): ?string
{
    $attributes = (new ReflectionClass($class))->getProperty($property)->getAttributes(ORM\Column::class);
    if ($attributes === []) {
        return null;
    }

    return $attributes[0]->newInstance()->type;
}

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
    (new ReflectionMethod($patcher, 'patch109'))->invoke($patcher);
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

test('custom recurring billing periods patch is numbered 107, out of sequence', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 106);

    expect($patches)->toHaveKey(107)
        ->and($patches[107][1])->toBe('patch107');
});

test('custom recurring billing periods patch is not skipped by 0.8-next installs already at patch level 98', function (): void {
    // 0.8-next independently used patch number 98 for an unrelated migration (tld.periods),
    // which never touched product_payment. An install coming from that lineage already has
    // last_patch = 98, so the migration must live at a number above 98 (not 98 itself) or it
    // would silently never run here. See https://github.com/FOSSBilling/FOSSBilling/issues/4188.
    $allPatches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 0);
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 98);

    expect($allPatches)->not->toHaveKey(98)
        ->and($patches)->toHaveKey(107)
        ->and($patches[107][1])->toBe('patch107');
});

test('downloadable file and suspension grace patches are numbered 108 and 109, out of sequence', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 107);

    expect($patches)->toHaveKey(108)
        ->and($patches[108][1])->toBe('patch108')
        ->and($patches)->toHaveKey(109)
        ->and($patches[109][1])->toBe('patch109');
});

test('downloadable file and suspension grace patches are not skipped by 0.8-next installs already at patch level 98', function (): void {
    // Same collision as patch107 (see above), found auditing the rest of the sequence: 0.8-next
    // reused numbers 92 and 95 for unrelated migrations, but never ported the downloadable-file
    // table or suspension-grace-days columns at all. An install coming from that lineage already
    // has last_patch = 98, so both migrations must live above 98 or they'd never run here.
    $allPatches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 0);
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 98);

    expect($allPatches)->not->toHaveKey(92)
        ->and($allPatches)->not->toHaveKey(95)
        ->and($patches)->toHaveKey(108)
        ->and($patches[108][1])->toBe('patch108')
        ->and($patches)->toHaveKey(109)
        ->and($patches[109][1])->toBe('patch109');
});

test('foreign key width patch is numbered 110', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 109);

    expect($patches)->toHaveKey(110)
        ->and($patches[110][1])->toBe('patch110');
});

test('foreign key width patch widens narrow gateway_id columns but leaves already-wide columns alone', function (): void {
    // invoice.gateway_id and transaction.gateway_id were int(11) in the pre-cutover schema
    // while pay_gateway.id (which they reference) is bigint(20). email_queue.client_id and
    // email_queue.admin_id have the same mismatch against client.id/admin.id. This patch
    // widens any column still typed int and is a no-op for columns already bigint.
    $invoiceLength = Mockery::mock(PDOStatement::class);
    $invoiceLength->expects('execute')->with(['column' => 'gateway_id'])->andReturnTrue();
    $invoiceLength->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Field' => 'gateway_id', 'Type' => 'int(11)'],
    ]);

    $invoiceAlter = Mockery::mock(PDOStatement::class);
    $invoiceAlter->expects('execute')->with([])->andReturnTrue();

    $transactionLength = Mockery::mock(PDOStatement::class);
    $transactionLength->expects('execute')->with(['column' => 'gateway_id'])->andReturnTrue();
    $transactionLength->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Field' => 'gateway_id', 'Type' => 'int(11)'],
    ]);

    $transactionAlter = Mockery::mock(PDOStatement::class);
    $transactionAlter->expects('execute')->with([])->andReturnTrue();

    $emailQueueClientLength = Mockery::mock(PDOStatement::class);
    $emailQueueClientLength->expects('execute')->with(['column' => 'client_id'])->andReturnTrue();
    $emailQueueClientLength->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Field' => 'client_id', 'Type' => 'bigint(20)'],
    ]);

    $emailQueueAdminLength = Mockery::mock(PDOStatement::class);
    $emailQueueAdminLength->expects('execute')->with(['column' => 'admin_id'])->andReturnTrue();
    $emailQueueAdminLength->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Field' => 'admin_id', 'Type' => 'bigint(20)'],
    ]);

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `invoice` LIKE :column')->andReturn($invoiceLength);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `invoice` MODIFY COLUMN `gateway_id` bigint(20) DEFAULT NULL')
        ->andReturn($invoiceAlter);

    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `transaction` LIKE :column')->andReturn($transactionLength);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `transaction` MODIFY COLUMN `gateway_id` bigint(20) DEFAULT NULL')
        ->andReturn($transactionAlter);

    $pdo->expects('prepare')
        ->with('SHOW COLUMNS FROM `email_queue` LIKE :column')
        ->twice()
        ->andReturn($emailQueueClientLength, $emailQueueAdminLength);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch110'))->invoke($patcher);
});

test('foreign key width patch widens a narrow column even when MySQL omits the display width', function (): void {
    // MySQL 8.0.19+ deprecates (and 8.4+ drops) integer display widths, so SHOW COLUMNS can
    // report a bare "int" with no "(11)" suffix. The patch must key off the base type name,
    // not a parsed display-width digit, or it would silently skip widening on newer servers.
    $invoiceLength = Mockery::mock(PDOStatement::class);
    $invoiceLength->expects('execute')->with(['column' => 'gateway_id'])->andReturnTrue();
    $invoiceLength->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Field' => 'gateway_id', 'Type' => 'int'],
    ]);

    $invoiceAlter = Mockery::mock(PDOStatement::class);
    $invoiceAlter->expects('execute')->with([])->andReturnTrue();

    $transactionLength = Mockery::mock(PDOStatement::class);
    $transactionLength->expects('execute')->with(['column' => 'gateway_id'])->andReturnTrue();
    $transactionLength->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Field' => 'gateway_id', 'Type' => 'bigint'],
    ]);

    $emailQueueClientLength = Mockery::mock(PDOStatement::class);
    $emailQueueClientLength->expects('execute')->with(['column' => 'client_id'])->andReturnTrue();
    $emailQueueClientLength->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Field' => 'client_id', 'Type' => 'bigint'],
    ]);

    $emailQueueAdminLength = Mockery::mock(PDOStatement::class);
    $emailQueueAdminLength->expects('execute')->with(['column' => 'admin_id'])->andReturnTrue();
    $emailQueueAdminLength->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Field' => 'admin_id', 'Type' => 'bigint'],
    ]);

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `invoice` LIKE :column')->andReturn($invoiceLength);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `invoice` MODIFY COLUMN `gateway_id` bigint(20) DEFAULT NULL')
        ->andReturn($invoiceAlter);

    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `transaction` LIKE :column')->andReturn($transactionLength);
    $pdo->shouldNotReceive('prepare')->with('ALTER TABLE `transaction` MODIFY COLUMN `gateway_id` bigint(20) DEFAULT NULL');

    $pdo->expects('prepare')
        ->with('SHOW COLUMNS FROM `email_queue` LIKE :column')
        ->twice()
        ->andReturn($emailQueueClientLength, $emailQueueAdminLength);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch110'))->invoke($patcher);
});

test('service apikey table patch is numbered 111', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 110);

    expect($patches)->toHaveKey(111)
        ->and($patches[111][1])->toBe('patch111');
});

test('service apikey table patch is a no-op when the table already exists', function (): void {
    // The Serviceapikey module (PR #4055) added the ServiceApiKey Doctrine entity but never
    // added it to the pre-cutover schema definition, so service_apikey was never created on any MySQL
    // install. This patch backfills it for existing installs, guarded so it never overwrites
    // a table that's somehow already there (e.g. an install that already ran this patch).
    $tableExists = Mockery::mock(PDOStatement::class);
    $tableExists->expects('execute')->with(['table' => 'service_apikey'])->andReturnTrue();
    $tableExists->expects('fetchColumn')->andReturn('1');

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')
        ->with('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1')
        ->andReturn($tableExists);
    $pdo->shouldNotReceive('prepare')->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'CREATE TABLE `service_apikey`')));

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch111'))->invoke($patcher);
});

test('service apikey table patch creates the table when it is missing', function (): void {
    $tableExists = Mockery::mock(PDOStatement::class);
    $tableExists->expects('execute')->with(['table' => 'service_apikey'])->andReturnTrue();
    $tableExists->expects('fetchColumn')->andReturn(false);

    $createTable = Mockery::mock(PDOStatement::class);
    $createTable->expects('execute')->with([])->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')
        ->with('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1')
        ->andReturn($tableExists);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'CREATE TABLE `service_apikey`')
            && str_contains($sql, '`client_id` BIGINT DEFAULT NULL')
            && str_contains($sql, 'KEY `client_id_idx` (`client_id`)')))
        ->andReturn($createTable);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch111'))->invoke($patcher);
});

test('require transfer code patch is numbered 112', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 111);

    expect($patches)->toHaveKey(112)
        ->and($patches[112][1])->toBe('patch112');
});

test('require transfer code patch adds the column for existing installs', function (): void {
    $columns = Mockery::mock(PDOStatement::class);
    $columns->expects('execute')->with([])->andReturnTrue();
    $columns->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $addColumn = Mockery::mock(PDOStatement::class);
    $addColumn->expects('execute')->with([])->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `tld`')->andReturn($columns);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `tld` ADD COLUMN `require_transfer_code` tinyint(1) DEFAULT NULL AFTER `allow_transfer`')
        ->andReturn($addColumn);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch112'))->invoke($patcher);
});

test('require transfer code patch is a no-op when the column already exists', function (): void {
    $columns = Mockery::mock(PDOStatement::class);
    $columns->expects('execute')->with([])->andReturnTrue();
    $columns->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([['Field' => 'require_transfer_code']]);

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `tld`')->andReturn($columns);
    $pdo->shouldNotReceive('prepare')->with('ALTER TABLE `tld` ADD COLUMN `require_transfer_code` tinyint(1) DEFAULT NULL AFTER `allow_transfer`');

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch112'))->invoke($patcher);
});

test('admin salt column drop patch is numbered 113', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 112);

    expect($patches)->toHaveKey(113)
        ->and($patches[113][1])->toBe('patch113');
});

test('admin salt column drop patch is a no-op when the column is already gone', function (): void {
    $columns = Mockery::mock(PDOStatement::class);
    $columns->expects('execute')->with([])->andReturnTrue();
    $columns->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `admin`')->andReturn($columns);
    $pdo->shouldNotReceive('prepare')->with('ALTER TABLE `admin` DROP COLUMN `salt`');

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch113'))->invoke($patcher);
});

test('admin salt column drop patch drops the column when it still exists', function (): void {
    $columns = Mockery::mock(PDOStatement::class);
    $columns->expects('execute')->with([])->andReturnTrue();
    $columns->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([['Field' => 'salt']]);

    $dropColumn = Mockery::mock(PDOStatement::class);
    $dropColumn->expects('execute')->with([])->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `admin`')->andReturn($columns);
    $pdo->expects('prepare')->with('ALTER TABLE `admin` DROP COLUMN `salt`')->andReturn($dropColumn);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch113'))->invoke($patcher);
});

test('cart unique session_id patch is numbered 114', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 113);

    expect($patches)->toHaveKey(114)
        ->and($patches[114][1])->toBe('patch114');
});

test('cart unique session_id patch is a no-op when the index is already unique and there are no duplicates', function (): void {
    $duplicates = Mockery::mock(PDOStatement::class);
    $duplicates->expects('execute')->with([])->andReturnTrue();
    $duplicates->expects('fetchAll')->with(PDO::FETCH_COLUMN)->andReturn([]);

    $indexes = Mockery::mock(PDOStatement::class);
    $indexes->expects('execute')->with([])->andReturnTrue();
    $indexes->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Key_name' => 'session_id_idx', 'Non_unique' => '0'],
    ]);

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'SELECT c.id FROM cart c')))
        ->andReturn($duplicates);
    $pdo->expects('prepare')->with('SHOW INDEX FROM `cart`')->andReturn($indexes);
    $pdo->shouldNotReceive('prepare')->with('ALTER TABLE `cart` DROP INDEX `session_id_idx`');
    $pdo->shouldNotReceive('prepare')->with('ALTER TABLE `cart` ADD UNIQUE INDEX `session_id_idx` (`session_id`)');

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch114'))->invoke($patcher);
});

test('cart unique session_id patch reconciles duplicate sessions then converts the index to unique', function (): void {
    // Two carts (ids 5 and 9) share a session_id; id 9 is the newer one and is kept.
    $duplicates = Mockery::mock(PDOStatement::class);
    $duplicates->expects('execute')->with([])->andReturnTrue();
    $duplicates->expects('fetchAll')->with(PDO::FETCH_COLUMN)->andReturn([5]);

    $deleteCartProduct = Mockery::mock(PDOStatement::class);
    $deleteCartProduct->expects('execute')->with([5])->andReturnTrue();

    $deleteCart = Mockery::mock(PDOStatement::class);
    $deleteCart->expects('execute')->with([5])->andReturnTrue();

    $indexes = Mockery::mock(PDOStatement::class);
    $indexes->expects('execute')->with([])->andReturnTrue();
    $indexes->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Key_name' => 'session_id_idx', 'Non_unique' => '1'],
    ]);

    $dropIndex = Mockery::mock(PDOStatement::class);
    $dropIndex->expects('execute')->with([])->andReturnTrue();

    $addIndex = Mockery::mock(PDOStatement::class);
    $addIndex->expects('execute')->with([])->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'SELECT c.id FROM cart c')))
        ->andReturn($duplicates);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'DELETE FROM `cart_product` WHERE `cart_id` IN (?)')))
        ->andReturn($deleteCartProduct);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'DELETE FROM `cart` WHERE `id` IN (?)')))
        ->andReturn($deleteCart);
    $pdo->expects('prepare')->with('SHOW INDEX FROM `cart`')->andReturn($indexes);
    $pdo->expects('prepare')->with('ALTER TABLE `cart` DROP INDEX `session_id_idx`')->andReturn($dropIndex);
    $pdo->expects('prepare')->with('ALTER TABLE `cart` ADD UNIQUE INDEX `session_id_idx` (`session_id`)')->andReturn($addIndex);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch114'))->invoke($patcher);
});

/**
 * These patches are raw MySQL/MariaDB DDL with no PostgreSQL/SQLite equivalent - see
 * UpdatePatcher::isMysqlDriver(). Swaps the real config.php's `db.driver`, mirroring
 * DriverManagerFactoryTest's config-swap pattern, since getDatabaseConfig() reads it directly.
 */
function withNonMysqlDbDriver(Closure $callback): void
{
    withDbDriverConfig(['driver' => 'pdo_sqlite', 'path' => '/tmp/does-not-matter.sqlite'], $callback);
}

/**
 * Same config-swap as {@see withNonMysqlDbDriver()}, but forcing `pdo_mysql` regardless of what
 * the ambient test config already has - so a test doesn't silently depend on that.
 */
function withMysqlDbDriver(Closure $callback): void
{
    withDbDriverConfig(['driver' => 'pdo_mysql', 'host' => '127.0.0.1', 'port' => 3306, 'name' => 'does_not_matter', 'user' => 'root', 'password' => ''], $callback);
}

function withDbDriverConfig(array $dbConfig, Closure $callback): void
{
    $filesystem = new Filesystem();
    $original = $filesystem->readFile(PATH_CONFIG);
    $config = FOSSBilling\Config::getConfig();
    $config['db'] = $dbConfig;
    $filesystem->dumpFile(PATH_CONFIG, '<?php return ' . var_export($config, true) . ';');
    clearstatcache(true, PATH_CONFIG);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate(PATH_CONFIG, true);
    }

    try {
        $callback();
    } finally {
        $filesystem->dumpFile(PATH_CONFIG, $original);
        clearstatcache(true, PATH_CONFIG);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(PATH_CONFIG, true);
        }
    }
}

/**
 * A PDOStatement stub whose execute() always succeeds, for tests that don't care about the
 * theme-migration calls' specific SQL/params - only that they don't blow up an otherwise
 * unrelated PDO mock, since migrateThemePackageLayout() now runs unconditionally regardless of
 * driver (see the dedicated tests above asserting its exact SQL/params).
 *
 * Also tolerates seedInvoiceNoteSettings() and storeSchemaMetadataHash() bookkeeping: both run
 * unconditionally on every platform (portable check-then-write SQL, no MySQL-only syntax), so
 * any applyCorePatches() test would trip over them otherwise. fetchColumn() reports "missing"
 * so the seed path exercises its INSERT branch.
 */
function mockPdoAllowingThemeMigrationCalls(): Mockery\MockInterface
{
    $statement = Mockery::mock(PDOStatement::class);
    $statement->shouldReceive('execute')->andReturnTrue();
    $statement->shouldReceive('fetchColumn')->andReturn(false);

    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldReceive('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_starts_with($sql, 'UPDATE setting')
            || str_starts_with($sql, 'UPDATE extension_meta')
            || $sql === "DELETE FROM extension_meta WHERE extension = 'mod_hook' AND meta_key = 'listener'"
            || $sql === "DELETE FROM extension WHERE type = 'hook'"))
        ->andReturn($statement);
    $pdo->shouldReceive('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_starts_with($sql, 'SELECT value FROM setting') || str_starts_with($sql, 'INSERT INTO setting')))
        ->andReturn($statement);

    return $pdo;
}

/**
 * Allow the portable retired-hook cleanup statements in otherwise strict applyCorePatches()
 * PDO mocks. The SQLite and strict-mock tests below cover the cleanup's behavior and SQL.
 */
function allowRetiredHookCleanupCalls(Mockery\MockInterface $pdo): void
{
    $statement = Mockery::mock(PDOStatement::class);
    $statement->shouldReceive('execute')->with([])->andReturnTrue();

    $pdo->shouldReceive('prepare')
        ->with("DELETE FROM extension_meta WHERE extension = 'mod_hook' AND meta_key = 'listener'")
        ->andReturn($statement);
    $pdo->shouldReceive('prepare')
        ->with("DELETE FROM extension WHERE type = 'hook'")
        ->andReturn($statement);
}

test('applyCorePatches never runs a legacy MySQL patch on a non-MySQL driver, even if the patch level looks stale', function (): void {
    withNonMysqlDbDriver(function (): void {
        // mockPdoAllowingThemeMigrationCalls() accepts the portable migrations and retired-hook
        // cleanup; anything else (backtick-quoted identifiers, ALTER TABLE, SHOW COLUMNS, ...) would
        // mean a legacy MySQL-only patch ran, which this test exists to catch.
        $pdo = mockPdoAllowingThemeMigrationCalls();
        $pdo->shouldNotReceive('query');

        $di = new Pimple\Container();
        $di['pdo'] = $pdo;
        $di['logger'] = new Tests\Helpers\TestLogger();

        $patcher = new UpdatePatcher();
        $patcher->setDi($di);

        // force: true is exactly the path finalizeUpdateLocked() calls unconditionally on every
        // request when finalization state is missing/stale - this must never touch the database
        // via a legacy MySQL-only patch. With no entity manager in $di, there is also nothing for
        // the portable schema sync to run against, so this is a full no-op end to end beyond the
        // portable theme-migration calls asserted above.
        $patcher->applyCorePatches(force: true);
    });
});

test('applyCorePatches removes retired hook data on non-MySQL drivers and remains idempotent', function (): void {
    withNonMysqlDbDriver(function (): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE setting (param TEXT PRIMARY KEY, value TEXT, public INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE extension_meta (id INTEGER PRIMARY KEY, extension TEXT, rel_type TEXT, rel_id TEXT, meta_key TEXT, meta_value TEXT)');
        $pdo->exec('CREATE TABLE extension (id INTEGER PRIMARY KEY, type TEXT, name TEXT, status TEXT, version TEXT)');
        $pdo->exec("INSERT INTO extension_meta (extension, rel_type, rel_id, meta_key, meta_value) VALUES
            ('mod_hook', 'listener', 'legacy-listener', 'listener', '{}'),
            ('mod_hook', 'config', '', 'config', '{}'),
            ('mod_invoice', 'listener', 'unrelated-listener', 'listener', '{}')");
        $pdo->exec("INSERT INTO extension (type, name, status, version) VALUES
            ('hook', 'legacy-hook-package', 'installed', '1.0.0'),
            ('mod', 'invoice', 'installed', '1.0.0')");

        $di = new Pimple\Container();
        $di['pdo'] = $pdo;
        $di['logger'] = new Tests\Helpers\TestLogger();

        $patcher = new UpdatePatcher();
        $patcher->setDi($di);

        // The portable cleanup runs from applyCorePatches on every driver. Running the
        // upgrade twice also proves the deletes are safe when no retired rows remain.
        $patcher->applyCorePatches(force: true);
        $patcher->applyCorePatches(force: true);

        expect((int) $pdo->query("SELECT COUNT(*) FROM extension_meta WHERE extension = 'mod_hook' AND meta_key = 'listener'")->fetchColumn())->toBe(0)
            ->and((int) $pdo->query("SELECT COUNT(*) FROM extension WHERE type = 'hook'")->fetchColumn())->toBe(0)
            ->and((int) $pdo->query("SELECT COUNT(*) FROM extension_meta WHERE extension = 'mod_hook' AND meta_key = 'config'")->fetchColumn())->toBe(1)
            ->and((int) $pdo->query("SELECT COUNT(*) FROM extension_meta WHERE extension = 'mod_invoice' AND meta_key = 'listener'")->fetchColumn())->toBe(1)
            ->and((int) $pdo->query("SELECT COUNT(*) FROM extension WHERE type = 'mod' AND name = 'invoice'")->fetchColumn())->toBe(1);
    });
});

test('retired hook cleanup uses both portable delete statements without parameters', function (): void {
    withNonMysqlDbDriver(function (): void {
        $listenerStatement = Mockery::mock(PDOStatement::class);
        $listenerStatement->expects('execute')->with([])->andReturnTrue();
        $packageStatement = Mockery::mock(PDOStatement::class);
        $packageStatement->expects('execute')->with([])->andReturnTrue();

        $pdo = Mockery::mock(PDO::class);
        $pdo->expects('prepare')
            ->with("DELETE FROM extension_meta WHERE extension = 'mod_hook' AND meta_key = 'listener'")
            ->andReturn($listenerStatement);
        $pdo->expects('prepare')
            ->with("DELETE FROM extension WHERE type = 'hook'")
            ->andReturn($packageStatement);

        $di = new Pimple\Container();
        $di['pdo'] = $pdo;

        $patcher = new UpdatePatcher();
        $patcher->setDi($di);
        (new ReflectionMethod($patcher, 'removeRetiredHookData'))->invoke($patcher);
    });
});

test('migrateThemePackageLayout renames admin_default/huraga when only the old directory exists', function (): void {
    $adminDefault = Path::join(PATH_THEMES, 'admin_default');
    $huraga = Path::join(PATH_THEMES, 'huraga');
    $defaultAdmin = Path::join(PATH_THEMES, 'default', 'admin');
    $defaultClient = Path::join(PATH_THEMES, 'default', 'client');

    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->with($adminDefault)->andReturnTrue();
    $filesystem->shouldReceive('exists')->with($huraga)->andReturnTrue();
    $filesystem->shouldReceive('exists')->with($defaultAdmin)->andReturnFalse();
    $filesystem->shouldReceive('exists')->with($defaultClient)->andReturnFalse();
    $filesystem->expects('mkdir')->with(Path::join(PATH_THEMES, 'default'))->twice();
    $filesystem->expects('rename')->with($adminDefault, $defaultAdmin)->once();
    $filesystem->expects('rename')->with($huraga, $defaultClient)->once();
    $filesystem->shouldNotReceive('remove');

    $di = new Pimple\Container();
    $di['pdo'] = mockPdoAllowingThemeMigrationCalls();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['filesystem'] = $filesystem;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'migrateThemePackageLayout'))->invoke($patcher);
});

test('migrateThemePackageLayout mirrors leftover old directories into the new one before discarding them', function (): void {
    $adminDefault = Path::join(PATH_THEMES, 'admin_default');
    $huraga = Path::join(PATH_THEMES, 'huraga');
    $defaultAdmin = Path::join(PATH_THEMES, 'default', 'admin');
    $defaultClient = Path::join(PATH_THEMES, 'default', 'client');

    // Simulates a `git pull`/checkout deploy: the tracked files already moved via the checkout
    // itself, so both old and new paths exist. What's left at the old path is mostly gitignored
    // leftovers (rebuilt assets/build/, huraga's settings_data.json cache, which regenerates on
    // its own), but can also be genuinely untracked local customizations - an `html_custom`
    // override directory, extra files dropped into `custom-icons` - that a checkout never
    // touches. mirror() must copy anything still at the old path over (without clobbering what
    // the checkout already placed at the new path) before the old directory is discarded.
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->with($adminDefault)->andReturnTrue();
    $filesystem->shouldReceive('exists')->with($huraga)->andReturnTrue();
    $filesystem->shouldReceive('exists')->with($defaultAdmin)->andReturnTrue();
    $filesystem->shouldReceive('exists')->with($defaultClient)->andReturnTrue();
    $filesystem->shouldNotReceive('mkdir');
    $filesystem->shouldNotReceive('rename');
    $filesystem->expects('mirror')->with($adminDefault, $defaultAdmin, null, ['override' => false])->once()->ordered();
    $filesystem->expects('remove')->with($adminDefault)->once()->ordered();
    $filesystem->expects('mirror')->with($huraga, $defaultClient, null, ['override' => false])->once()->ordered();
    $filesystem->expects('remove')->with($huraga)->once()->ordered();

    $di = new Pimple\Container();
    $di['pdo'] = mockPdoAllowingThemeMigrationCalls();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['filesystem'] = $filesystem;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'migrateThemePackageLayout'))->invoke($patcher);
});

test('migrateThemePackageLayout does nothing on a fresh install where neither old directory ever existed', function (): void {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->andReturnFalse();
    $filesystem->shouldNotReceive('mkdir');
    $filesystem->shouldNotReceive('rename');
    $filesystem->shouldNotReceive('remove');

    $di = new Pimple\Container();
    $di['pdo'] = mockPdoAllowingThemeMigrationCalls();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['filesystem'] = $filesystem;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'migrateThemePackageLayout'))->invoke($patcher);
});

test('applyCorePatches migrates the theme setting values on a non-MySQL driver, since patch115 never runs there', function (): void {
    withNonMysqlDbDriver(function (): void {
        $adminThemeStatement = Mockery::mock(PDOStatement::class);
        $adminThemeStatement->expects('execute')
            ->with(['new_value' => 'default/admin', 'param' => 'admin_theme', 'old_value' => 'admin_default'])
            ->andReturnTrue();
        $clientThemeStatement = Mockery::mock(PDOStatement::class);
        $clientThemeStatement->expects('execute')
            ->with(['new_value' => 'default/client', 'param' => 'theme', 'old_value' => 'huraga'])
            ->andReturnTrue();
        $extensionMetaStatement = Mockery::mock(PDOStatement::class);
        $extensionMetaStatement->shouldReceive('execute')->andReturnTrue();

        $pdo = Mockery::mock(PDO::class);
        $pdo->expects('prepare')
            ->with('UPDATE setting SET value = :new_value WHERE param = :param AND value = :old_value')
            ->twice()
            ->andReturn($adminThemeStatement, $clientThemeStatement);
        $pdo->shouldReceive('prepare')
            ->with(Mockery::on(fn (string $sql): bool => str_starts_with($sql, 'UPDATE extension_meta')))
            ->andReturn($extensionMetaStatement);

        // seedInvoiceNoteSettings() also runs unconditionally from applyCorePatches() - allow
        // its check-then-insert bookkeeping without asserting on it here.
        $seedStatement = Mockery::mock(PDOStatement::class);
        $seedStatement->shouldReceive('execute')->andReturnTrue();
        $seedStatement->shouldReceive('fetchColumn')->andReturn(false);
        $pdo->shouldReceive('prepare')
            ->with(Mockery::on(fn (string $sql): bool => str_starts_with($sql, 'SELECT value FROM setting') || str_starts_with($sql, 'INSERT INTO setting')))
            ->andReturn($seedStatement);
        allowRetiredHookCleanupCalls($pdo);

        $di = new Pimple\Container();
        $di['pdo'] = $pdo;
        $di['logger'] = new Tests\Helpers\TestLogger();

        $patcher = new UpdatePatcher();
        $patcher->setDi($di);

        $patcher->applyCorePatches(force: true);
    });
});

test('applyCorePatches migrates saved theme settings/presets in extension_meta on a non-MySQL driver', function (): void {
    withNonMysqlDbDriver(function (): void {
        // rel_id/meta_key rename runs once per theme (admin_default, then huraga), so each SQL
        // text is prepared twice - once per code pair, each with its own params.
        $settingsAdmin = Mockery::mock(PDOStatement::class);
        $settingsAdmin->expects('execute')->with(['new_code' => 'default/admin', 'old_code' => 'admin_default'])->andReturnTrue();
        $settingsClient = Mockery::mock(PDOStatement::class);
        $settingsClient->expects('execute')->with(['new_code' => 'default/client', 'old_code' => 'huraga'])->andReturnTrue();
        $presetAdmin = Mockery::mock(PDOStatement::class);
        $presetAdmin->expects('execute')->with(['new_code' => 'default/admin', 'old_code' => 'admin_default'])->andReturnTrue();
        $presetClient = Mockery::mock(PDOStatement::class);
        $presetClient->expects('execute')->with(['new_code' => 'default/client', 'old_code' => 'huraga'])->andReturnTrue();
        $otherStatement = Mockery::mock(PDOStatement::class);
        $otherStatement->shouldReceive('execute')->andReturnTrue();

        $pdo = Mockery::mock(PDO::class);
        $pdo->expects('prepare')
            ->with("UPDATE extension_meta SET rel_id = :new_code WHERE extension = 'mod_theme' AND rel_type = 'settings' AND rel_id = :old_code")
            ->twice()
            ->andReturn($settingsAdmin, $settingsClient);
        $pdo->expects('prepare')
            ->with("UPDATE extension_meta SET meta_key = :new_code WHERE extension = 'mod_theme' AND rel_type = 'preset' AND rel_id = 'current' AND meta_key = :old_code")
            ->twice()
            ->andReturn($presetAdmin, $presetClient);
        $pdo->shouldReceive('prepare')
            ->with(Mockery::on(fn (string $sql): bool => str_starts_with($sql, 'UPDATE setting')))
            ->andReturn($otherStatement);

        // seedInvoiceNoteSettings() also runs unconditionally from applyCorePatches() - allow
        // its check-then-insert bookkeeping without asserting on it here.
        $seedStatement = Mockery::mock(PDOStatement::class);
        $seedStatement->shouldReceive('execute')->andReturnTrue();
        $seedStatement->shouldReceive('fetchColumn')->andReturn(false);
        $pdo->shouldReceive('prepare')
            ->with(Mockery::on(fn (string $sql): bool => str_starts_with($sql, 'SELECT value FROM setting') || str_starts_with($sql, 'INSERT INTO setting')))
            ->andReturn($seedStatement);
        allowRetiredHookCleanupCalls($pdo);

        $di = new Pimple\Container();
        $di['pdo'] = $pdo;
        $di['logger'] = new Tests\Helpers\TestLogger();

        $patcher = new UpdatePatcher();
        $patcher->setDi($di);

        $patcher->applyCorePatches(force: true);
    });
});

test('applyCorePatches runs a portable schema sync instead of legacy patches on a non-MySQL driver', function (): void {
    withNonMysqlDbDriver(function (): void {
        $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $entityManager = FOSSBilling\Doctrine\EntityManagerFactory::create($connection);
        FOSSBilling\Doctrine\SchemaInstaller::createSchema($entityManager);

        // Simulate a PostgreSQL/SQLite install that predates a table current entity metadata
        // knows about - the same situation a real upgrade would hit. `currency` is a core-module
        // table (see ModuleEntityScope), so it's always in scope for the ambient sync below,
        // unlike an extension's own table - see the gating tests further down.
        $connection->executeStatement('DROP TABLE currency');

        $pdo = mockPdoAllowingThemeMigrationCalls();

        $di = new Pimple\Container();
        $di['pdo'] = $pdo;
        $di['em'] = $entityManager;
        $di['logger'] = new Tests\Helpers\TestLogger();

        $patcher = new UpdatePatcher();
        $patcher->setDi($di);

        $patcher->applyCorePatches(force: true);

        expect($connection->createSchemaManager()->tablesExist(['currency']))->toBeTrue();
    });
});

test('applyCorePatches also runs a portable schema sync after legacy patches on a MySQL driver', function (): void {
    withMysqlDbDriver(function (): void {
        $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $entityManager = FOSSBilling\Doctrine\EntityManagerFactory::create($connection);
        FOSSBilling\Doctrine\SchemaInstaller::createSchema($entityManager);
        $connection->executeStatement('DROP TABLE currency');

        // The legacy patch loop still needs a patch level to compare against - report the latest
        // one so getPatches() finds nothing pending and the loop body never runs. That isolates
        // this test to proving the sync step runs afterward, not re-testing the patches themselves.
        // A bare mock (not mockPdoAllowingThemeMigrationCalls(), whose matchers would shadow it)
        // answers every prepare with the same stub: fetchColumn() always reports the patch level
        // (truthy), so the unconditional seedInvoiceNoteSettings() skips its inserts and
        // storeSchemaMetadataHash() takes its UPDATE branch - all harmless here.
        $latestPatchLevel = (new UpdatePatcher())->latestPatchLevel();
        $statement = Mockery::mock(PDOStatement::class);
        $statement->shouldReceive('execute')->andReturn(true);
        $statement->shouldReceive('fetchColumn')->andReturn((string) $latestPatchLevel);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')->andReturn($statement);

        $di = new Pimple\Container();
        $di['pdo'] = $pdo;
        $di['em'] = $entityManager;
        $di['logger'] = new Tests\Helpers\TestLogger();

        $patcher = new UpdatePatcher();
        $patcher->setDi($di);

        $patcher->applyCorePatches(force: true);

        expect($connection->createSchemaManager()->tablesExist(['currency']))->toBeTrue();
    });
});

/*
 * Regression coverage for the gating ModuleEntityScope adds: the ambient sync above must not undo
 * SchemaInstaller's fresh-install gating by unconditionally recreating an inactive extension's
 * table on every request, as if it had been activated.
 */
test('applyCorePatches never recreates an inactive extension\'s table via the ambient schema sync', function (): void {
    withNonMysqlDbDriver(function (): void {
        $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $entityManager = FOSSBilling\Doctrine\EntityManagerFactory::create($connection);
        FOSSBilling\Doctrine\SchemaInstaller::createSchema($entityManager);

        // custompages is neither a core module nor one of content.sql's default-active
        // extensions, so a fresh install never creates its table - nothing here to "predate".
        expect($connection->createSchemaManager()->tablesExist(['custom_pages']))->toBeFalse();

        $pdo = mockPdoAllowingThemeMigrationCalls();
        $pdo->shouldNotReceive('query');

        $di = new Pimple\Container();
        $di['pdo'] = $pdo;
        $di['em'] = $entityManager;
        $di['logger'] = new Tests\Helpers\TestLogger();

        $patcher = new UpdatePatcher();
        $patcher->setDi($di);

        $patcher->applyCorePatches(force: true);

        expect($connection->createSchemaManager()->tablesExist(['custom_pages']))->toBeFalse();
    });
});

test('applyCorePatches does resync an extension\'s table once it is marked installed', function (): void {
    withNonMysqlDbDriver(function (): void {
        $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $entityManager = FOSSBilling\Doctrine\EntityManagerFactory::create($connection);
        FOSSBilling\Doctrine\SchemaInstaller::createSchema($entityManager);

        // Simulate the module having been activated (its own install() hook already ran once,
        // creating its table) and then predating a later metadata change - the same situation
        // a currently-installed extension's table missing a new column would hit.
        FOSSBilling\Doctrine\SchemaSynchronizer::syncEntities($entityManager, [Box\Mod\Custompages\Entity\CustomPage::class]);
        $connection->executeStatement("INSERT INTO extension (type, name, status, version) VALUES ('mod', 'custompages', 'installed', '1.0.0')");
        $connection->executeStatement('DROP TABLE custom_pages');

        $pdo = mockPdoAllowingThemeMigrationCalls();
        $pdo->shouldNotReceive('query');

        $di = new Pimple\Container();
        $di['pdo'] = $pdo;
        $di['em'] = $entityManager;
        $di['logger'] = new Tests\Helpers\TestLogger();

        $patcher = new UpdatePatcher();
        $patcher->setDi($di);

        $patcher->applyCorePatches(force: true);

        expect($connection->createSchemaManager()->tablesExist(['custom_pages']))->toBeTrue();
    });
});

/*
 * Regression test: scope discovery (installedExtensionModules()'s schema introspection and
 * query, plus metadata loading) has to fail inside the same try/catch as the sync call itself -
 * it can throw for the same reasons the sync can (an unreachable database above all), and this
 * method's whole contract is "errors are logged, not thrown". A connection that fails outright
 * (rather than one that's merely missing a table) reproduces exactly that: the failure happens
 * during installedExtensionModules()'s own tablesExist() call, before SchemaSynchronizer::
 * syncEntities() is ever reached.
 */
test('applyCorePatches logs, rather than throws, when scope discovery itself fails to reach the database', function (): void {
    withNonMysqlDbDriver(function (): void {
        $brokenConnection = Doctrine\DBAL\DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => '/definitely-not-a-real-directory-987654321/db.sqlite',
        ]);
        $entityManager = FOSSBilling\Doctrine\EntityManagerFactory::create($brokenConnection);

        $pdo = mockPdoAllowingThemeMigrationCalls();
        $pdo->shouldNotReceive('query');

        $logger = new Tests\Helpers\TestLogger();
        $di = new Pimple\Container();
        $di['pdo'] = $pdo;
        $di['em'] = $entityManager;
        $di['logger'] = $logger;

        $patcher = new UpdatePatcher();
        $patcher->setDi($di);

        // Must not throw - the whole point of the try/catch this scope discovery has to live
        // inside.
        $patcher->applyCorePatches(force: true);

        $errorCalls = array_values(array_filter($logger->calls, static fn (array $call): bool => $call['method'] === 'error'));
        expect($errorCalls)->not->toBe([])
            ->and($errorCalls[0]['params'][0])->toContain('Schema sync against the configured database failed');
    });
});

test('availablePatches reports 0 on a non-MySQL driver regardless of the last_patch value', function (): void {
    withNonMysqlDbDriver(function (): void {
        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldNotReceive('prepare');
        $pdo->shouldNotReceive('query');

        $di = new Pimple\Container();
        $di['pdo'] = $pdo;

        $patcher = new UpdatePatcher();
        $patcher->setDi($di);

        expect($patcher->availablePatches())->toBe(0);
    });
});

test('legacy entity decode patch follows the theme package layout patch', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 115);

    expect($patches)->toHaveKey(116)
        ->and($patches[116][1])->toBe('patch116');
});

test('legacy entity decode repair restores raw invoice and notification values', function (): void {
    // Regression test for issue #4305. Uses real SQLite to prove the repair is
    // portable SQL. patch116 runs exactly once per install, so rows written
    // raw afterwards are never scanned.
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE invoice (id INTEGER PRIMARY KEY, seller_company TEXT, seller_company_vat TEXT, seller_company_number TEXT, seller_address TEXT, seller_phone TEXT, seller_email TEXT)');
    $pdo->exec("INSERT INTO invoice (seller_company, seller_company_vat, seller_company_number, seller_address, seller_phone, seller_email) VALUES ('A &amp; B Ltd', 'GB&amp;123', NULL, '5 &lt;Main&gt; St', 'O&#039;Brien', 'a&amp;b@example.com')");
    $pdo->exec("INSERT INTO invoice (seller_company) VALUES ('Plain Company')");
    $pdo->exec('CREATE TABLE extension_meta (id INTEGER PRIMARY KEY, extension TEXT, rel_type TEXT, rel_id TEXT, meta_key TEXT, meta_value TEXT)');
    $pdo->exec("INSERT INTO extension_meta (extension, rel_type, rel_id, meta_key, meta_value) VALUES ('mod_notification', 'staff', '1', 'message', 'Call A &amp; B about the invoice')");
    $pdo->exec("INSERT INTO extension_meta (extension, rel_type, rel_id, meta_key, meta_value) VALUES ('mod_notification', 'staff', '1', 'message', 'Plain note')");

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    // Invoke the repair directly: patch116's setPatchLevel() bookkeeping is
    // MySQL-only SQL (`ON DUPLICATE KEY UPDATE`), so the data assertions run
    // here while the mocked rollback test below covers the patch wiring.
    $repair = new ReflectionMethod($patcher, 'decodeLegacyServiceEscapedEntities');

    $repair->invoke($patcher);

    $invoice = $pdo->query('SELECT * FROM invoice WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    expect($invoice['seller_company'])->toBe('A & B Ltd')
        ->and($invoice['seller_company_vat'])->toBe('GB&123')
        ->and($invoice['seller_company_number'])->toBeNull()
        ->and($invoice['seller_address'])->toBe('5 <Main> St')
        ->and($invoice['seller_phone'])->toBe("O'Brien")
        ->and($invoice['seller_email'])->toBe('a&b@example.com');

    $plain = $pdo->query('SELECT seller_company FROM invoice WHERE id = 2')->fetchColumn();
    expect($plain)->toBe('Plain Company');

    $note = $pdo->query("SELECT meta_value FROM extension_meta WHERE meta_key = 'message' AND id = 1")->fetchColumn();
    expect($note)->toBe('Call A & B about the invoice');

    $plainNote = $pdo->query("SELECT meta_value FROM extension_meta WHERE meta_key = 'message' AND id = 2")->fetchColumn();
    expect($plainNote)->toBe('Plain note');

    // Second run must change nothing.
    $repair->invoke($patcher);

    expect($pdo->query('SELECT * FROM invoice WHERE id = 1')->fetch(PDO::FETCH_ASSOC))->toBe($invoice)
        ->and($pdo->query("SELECT meta_value FROM extension_meta WHERE meta_key = 'message' AND id = 1")->fetchColumn())->toBe('Call A & B about the invoice');
});

test('legacy entity decode patch rolls back row repairs when the patch level cannot be recorded', function (): void {
    // Without atomicity, rows committed before a failed setPatchLevel() would
    // be decoded a second time on retry. Mirrors the stock backfill rollback
    // test above.
    $selectInvoices = Mockery::mock(PDOStatement::class);
    $selectInvoices->expects('execute')->with([])->andReturnTrue();
    $selectInvoices->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['id' => 1, 'seller_company' => 'A &amp; B Ltd', 'seller_company_vat' => null, 'seller_company_number' => null, 'seller_address' => null, 'seller_phone' => null, 'seller_email' => null],
    ]);

    $updateInvoice = Mockery::mock(PDOStatement::class);
    $updateInvoice->expects('execute')->with(['seller_company' => 'A & B Ltd', 'id' => 1])->andReturnTrue();

    $selectNotes = Mockery::mock(PDOStatement::class);
    $selectNotes->expects('execute')->with([])->andReturnTrue();
    $selectNotes->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('beginTransaction')->once()->andReturnTrue();
    $pdo->expects('rollBack')->once()->andReturnTrue();
    $pdo->shouldNotReceive('commit');
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_starts_with($sql, 'SELECT id, seller_company')))
        ->andReturn($selectInvoices);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_starts_with($sql, 'UPDATE invoice SET')))
        ->andReturn($updateInvoice);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_starts_with($sql, 'SELECT id, meta_value')))
        ->andReturn($selectNotes);
    $pdo->expects('prepare')
        ->with(Mockery::on(fn (string $sql): bool => str_starts_with($sql, 'INSERT INTO setting')))
        ->andThrow(new RuntimeException('level write failed'));

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);

    expect(fn (): mixed => (new ReflectionMethod($patcher, 'patch116'))->invoke($patcher))
        ->toThrow(FOSSBilling\Exception::class, 'There was an error while applying database patches');
});

test('news post description patch follows the session schema patch', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 116);

    expect($patches)->toHaveKey(117)
        ->and($patches[117][1])->toBe('patch117');
});

test('news post description patch adds the column for existing installs', function (): void {
    $columns = Mockery::mock(PDOStatement::class);
    $columns->expects('execute')->with([])->andReturnTrue();
    $columns->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $addColumn = Mockery::mock(PDOStatement::class);
    $addColumn->expects('execute')->with([])->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `post`')->andReturn($columns);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `post` ADD COLUMN `description` TEXT DEFAULT NULL AFTER `title`')
        ->andReturn($addColumn);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch117'))->invoke($patcher);
});

test('news post description patch is a no-op when the column already exists', function (): void {
    $columns = Mockery::mock(PDOStatement::class);
    $columns->expects('execute')->with([])->andReturnTrue();
    $columns->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([['Field' => 'description']]);

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `post`')->andReturn($columns);
    $pdo->shouldNotReceive('prepare')->with('ALTER TABLE `post` ADD COLUMN `description` TEXT DEFAULT NULL AFTER `title`');

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch117'))->invoke($patcher);
});

test('credit and debit note patch follows the news post description patch', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 118);

    expect($patches)->toHaveKey(119)
        ->and($patches[119][1])->toBe('patch119');
});

test('credit and debit note patch adds the missing columns and indexes for existing installs', function (): void {
    // Regression test for https://github.com/FOSSBilling/FOSSBilling/issues/4392: the
    // credit/debit-note releases added entity columns with no MySQL patch, so installs that
    // never ran the ambient schema sync crash on invoice listings with "Unknown column
    // 'credit_note_for_invoice_id'".
    $invoiceColumnsA = Mockery::mock(PDOStatement::class);
    $invoiceColumnsA->expects('execute')->with([])->andReturnTrue();
    $invoiceColumnsA->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $invoiceColumnsB = Mockery::mock(PDOStatement::class);
    $invoiceColumnsB->expects('execute')->with([])->andReturnTrue();
    $invoiceColumnsB->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $invoiceIndexesA = Mockery::mock(PDOStatement::class);
    $invoiceIndexesA->expects('execute')->with([])->andReturnTrue();
    $invoiceIndexesA->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $invoiceIndexesB = Mockery::mock(PDOStatement::class);
    $invoiceIndexesB->expects('execute')->with([])->andReturnTrue();
    $invoiceIndexesB->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $itemColumns = Mockery::mock(PDOStatement::class);
    $itemColumns->expects('execute')->with([])->andReturnTrue();
    $itemColumns->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $addCreditColumn = Mockery::mock(PDOStatement::class);
    $addCreditColumn->expects('execute')->with([])->andReturnTrue();
    $addCreditIndex = Mockery::mock(PDOStatement::class);
    $addCreditIndex->expects('execute')->with([])->andReturnTrue();
    $addDebitColumn = Mockery::mock(PDOStatement::class);
    $addDebitColumn->expects('execute')->with([])->andReturnTrue();
    $addDebitIndex = Mockery::mock(PDOStatement::class);
    $addDebitIndex->expects('execute')->with([])->andReturnTrue();
    $addRefundedItemColumn = Mockery::mock(PDOStatement::class);
    $addRefundedItemColumn->expects('execute')->with([])->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `invoice`')->twice()->andReturn($invoiceColumnsA, $invoiceColumnsB);
    $pdo->expects('prepare')->with('SHOW INDEX FROM `invoice`')->twice()->andReturn($invoiceIndexesA, $invoiceIndexesB);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `invoice_item`')->once()->andReturn($itemColumns);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `invoice` ADD COLUMN `credit_note_for_invoice_id` bigint(20) DEFAULT NULL AFTER `status`')
        ->andReturn($addCreditColumn);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `invoice` ADD INDEX `invoice_credit_note_for_idx` (`credit_note_for_invoice_id`)')
        ->andReturn($addCreditIndex);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `invoice` ADD COLUMN `debit_note_for_invoice_id` bigint(20) DEFAULT NULL AFTER `credit_note_for_invoice_id`')
        ->andReturn($addDebitColumn);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `invoice` ADD INDEX `invoice_debit_note_for_idx` (`debit_note_for_invoice_id`)')
        ->andReturn($addDebitIndex);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `invoice_item` ADD COLUMN `refunded_item_id` bigint(20) DEFAULT NULL AFTER `rel_id`')
        ->andReturn($addRefundedItemColumn);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch119'))->invoke($patcher);
});

test('credit and debit note patch is a no-op when the columns and indexes already exist', function (): void {
    $invoiceColumnsA = Mockery::mock(PDOStatement::class);
    $invoiceColumnsA->expects('execute')->with([])->andReturnTrue();
    $invoiceColumnsA->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Field' => 'credit_note_for_invoice_id'],
        ['Field' => 'debit_note_for_invoice_id'],
    ]);

    $invoiceColumnsB = Mockery::mock(PDOStatement::class);
    $invoiceColumnsB->expects('execute')->with([])->andReturnTrue();
    $invoiceColumnsB->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Field' => 'credit_note_for_invoice_id'],
        ['Field' => 'debit_note_for_invoice_id'],
    ]);

    $invoiceIndexesA = Mockery::mock(PDOStatement::class);
    $invoiceIndexesA->expects('execute')->with([])->andReturnTrue();
    $invoiceIndexesA->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Key_name' => 'invoice_credit_note_for_idx'],
        ['Key_name' => 'invoice_debit_note_for_idx'],
    ]);

    $invoiceIndexesB = Mockery::mock(PDOStatement::class);
    $invoiceIndexesB->expects('execute')->with([])->andReturnTrue();
    $invoiceIndexesB->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Key_name' => 'invoice_credit_note_for_idx'],
        ['Key_name' => 'invoice_debit_note_for_idx'],
    ]);

    $itemColumns = Mockery::mock(PDOStatement::class);
    $itemColumns->expects('execute')->with([])->andReturnTrue();
    $itemColumns->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([['Field' => 'refunded_item_id']]);

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `invoice`')->twice()->andReturn($invoiceColumnsA, $invoiceColumnsB);
    $pdo->expects('prepare')->with('SHOW INDEX FROM `invoice`')->twice()->andReturn($invoiceIndexesA, $invoiceIndexesB);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `invoice_item`')->once()->andReturn($itemColumns);
    $pdo->shouldNotReceive('prepare')->with(Mockery::on(fn (string $sql): bool => str_starts_with($sql, 'ALTER TABLE')));

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch119'))->invoke($patcher);
});

test('invoice note settings seeding inserts missing rows without touching customized values', function (): void {
    $selectSeries = Mockery::mock(PDOStatement::class);
    $selectSeries->expects('execute')->with(['param' => 'invoice_dn_series'])->andReturnTrue();
    $selectSeries->expects('fetchColumn')->andReturn(false);

    $selectNumber = Mockery::mock(PDOStatement::class);
    $selectNumber->expects('execute')->with(['param' => 'invoice_dn_starting_number'])->andReturnTrue();
    $selectNumber->expects('fetchColumn')->andReturn('5');

    $insert = Mockery::mock(PDOStatement::class);
    $insert->expects('execute')->with(Mockery::on(function (array $params): bool {
        expect($params['param'])->toBe('invoice_dn_series')
            ->and($params['value'])->toBe('DN-');

        return true;
    }))->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')
        ->with('SELECT value FROM setting WHERE param = :param')
        ->twice()
        ->andReturn($selectSeries, $selectNumber);
    $pdo->expects('prepare')
        ->with('INSERT INTO setting (param, value, public, created_at, updated_at) VALUES (:param, :value, 0, :created_at, :updated_at)')
        ->once()
        ->andReturn($insert);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'seedInvoiceNoteSettings'))->invoke($patcher);
});

test('ensureSchemaInSync does nothing without an entity manager', function (): void {
    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldNotReceive('prepare');

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);

    expect($patcher->ensureSchemaInSync())->toBeFalse();
});

test('ensureSchemaInSync logs, rather than throws, when the database is unreachable', function (): void {
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $entityManager = FOSSBilling\Doctrine\EntityManagerFactory::create($connection);

    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldReceive('prepare')->andThrow(new RuntimeException('connection refused'));

    $logger = new Tests\Helpers\TestLogger();
    $di = new Pimple\Container();
    $di['pdo'] = $pdo;
    $di['em'] = $entityManager;
    $di['logger'] = $logger;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);

    expect($patcher->ensureSchemaInSync())->toBeFalse();

    $errorCalls = array_values(array_filter($logger->calls, static fn (array $call): bool => $call['method'] === 'error'));
    expect($errorCalls)->not->toBe([])
        ->and($errorCalls[0]['params'][0])->toContain('Ambient schema sync failed');
});

test('isSchemaOutOfSync reports no drift without an entity manager or database', function (): void {
    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldNotReceive('prepare');

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);

    expect($patcher->isSchemaOutOfSync())->toBeFalse();

    $unreachable = Mockery::mock(PDO::class);
    $unreachable->shouldReceive('prepare')->andThrow(new RuntimeException('connection refused'));

    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $brokenDi = new Pimple\Container();
    $brokenDi['pdo'] = $unreachable;
    $brokenDi['em'] = FOSSBilling\Doctrine\EntityManagerFactory::create($connection);
    $brokenDi['logger'] = new Tests\Helpers\TestLogger();

    $brokenPatcher = new UpdatePatcher();
    $brokenPatcher->setDi($brokenDi);

    // Best-effort check: an unreadable database reports "in sync" rather than throwing, so the
    // per-request call outside the finalization lock stays cheap and silent.
    expect($brokenPatcher->isSchemaOutOfSync())->toBeFalse();
});

test('ensureSchemaInSync backs off after a failed attempt until the cooldown ends', function (): void {
    $dbFile = Path::join(sys_get_temp_dir(), 'fossbilling-schema-cooldown-' . bin2hex(random_bytes(8)) . '.sqlite');

    try {
        $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $dbFile]);
        $entityManager = FOSSBilling\Doctrine\EntityManagerFactory::create($connection);
        FOSSBilling\Doctrine\SchemaInstaller::createSchema($entityManager);

        $connection->executeStatement('DROP INDEX invoice_credit_note_for_idx');
        $connection->executeStatement('ALTER TABLE invoice DROP COLUMN credit_note_for_invoice_id');

        $columnNames = static fn (): array => array_column(
            $connection->fetchAllAssociative('PRAGMA table_info(invoice)'),
            'name'
        );

        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $di = new Pimple\Container();
        $di['pdo'] = $pdo;
        $di['em'] = $entityManager;
        $di['logger'] = new Tests\Helpers\TestLogger();

        $patcher = new UpdatePatcher();
        $patcher->setDi($di);

        expect($patcher->isSchemaOutOfSync())->toBeTrue();

        // A failed attempt for the current hash suppresses retries: no sync runs, so the column
        // stays missing instead of redoing full introspection on every request. The out-of-lock
        // check reports "in sync" too, so the finalization lock isn't taken for no work.
        $currentHash = FOSSBilling\Doctrine\EntityManagerFactory::entityDefinitionsHash();
        (new ReflectionMethod($patcher, 'recordFailedSyncAttempt'))->invoke($patcher, $currentHash);

        expect($patcher->isSchemaOutOfSync())->toBeFalse();
        expect($patcher->ensureSchemaInSync())->toBeFalse()
            ->and($columnNames())->not->toContain('credit_note_for_invoice_id');

        // Once the cooldown expires the same hash retries and the sync completes.
        $pdo->exec("UPDATE setting SET updated_at = '2000-01-01 00:00:00' WHERE param = 'schema_metadata_hash_failed'");

        expect($patcher->isSchemaOutOfSync())->toBeTrue();
        expect($patcher->ensureSchemaInSync())->toBeTrue()
            ->and($columnNames())->toContain('credit_note_for_invoice_id');
    } finally {
        (new Filesystem())->remove($dbFile);
    }
});

test('ensureSchemaInSync restores note columns missing from an older schema, then goes quiet', function (): void {
    // End-to-end upgrade path for https://github.com/FOSSBilling/FOSSBilling/issues/4392:
    // current entity metadata against a live schema predating the credit/debit-note releases.
    // A file-backed SQLite database is shared between the legacy PDO service (hash + settings
    // bookkeeping) and the Doctrine connection (schema sync), like production shares MySQL.
    $dbFile = Path::join(sys_get_temp_dir(), 'fossbilling-schema-sync-' . bin2hex(random_bytes(8)) . '.sqlite');

    try {
        $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $dbFile]);
        $entityManager = FOSSBilling\Doctrine\EntityManagerFactory::create($connection);
        FOSSBilling\Doctrine\SchemaInstaller::createSchema($entityManager);

        $connection->executeStatement('DROP INDEX invoice_credit_note_for_idx');
        $connection->executeStatement('DROP INDEX invoice_debit_note_for_idx');
        $connection->executeStatement('ALTER TABLE invoice DROP COLUMN credit_note_for_invoice_id');
        $connection->executeStatement('ALTER TABLE invoice DROP COLUMN debit_note_for_invoice_id');
        $connection->executeStatement('ALTER TABLE invoice_item DROP COLUMN refunded_item_id');

        $columnNames = static fn (string $table): array => array_column(
            $connection->fetchAllAssociative("PRAGMA table_info({$table})"),
            'name'
        );
        expect($columnNames('invoice'))->not->toContain('credit_note_for_invoice_id');

        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $di = new Pimple\Container();
        $di['pdo'] = $pdo;
        $di['em'] = $entityManager;
        $di['logger'] = new Tests\Helpers\TestLogger();

        $patcher = new UpdatePatcher();
        $patcher->setDi($di);

        expect($patcher->ensureSchemaInSync())->toBeTrue()
            ->and($columnNames('invoice'))->toContain('credit_note_for_invoice_id', 'debit_note_for_invoice_id')
            ->and($columnNames('invoice_item'))->toContain('refunded_item_id');

        // Debit-note settings rows an upgrade would otherwise never receive.
        expect($pdo->query("SELECT value FROM setting WHERE param = 'invoice_dn_series'")->fetchColumn())->toBe('DN-')
            ->and($pdo->query("SELECT value FROM setting WHERE param = 'invoice_dn_starting_number'")->fetchColumn())->toBe('1');

        // The recorded hash now matches, so the next request is a single-SELECT no-op.
        expect($patcher->ensureSchemaInSync())->toBeFalse();
    } finally {
        (new Filesystem())->remove($dbFile);
    }
});

test('invoice reissue patch follows the credit and debit note patch', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 119);

    expect($patches)->toHaveKey(120)
        ->and($patches[120][1])->toBe('patch120');
});

test('invoice reissue patch adds the missing replacement columns and indexes for existing installs', function (): void {
    // Regression test for https://github.com/FOSSBilling/FOSSBilling/issues/4392: the
    // invoice reissue release added entity columns with no MySQL patch, so installs
    // that never ran the ambient schema sync crash on invoice listings with
    // "Unknown column 'replaces_invoice_id'".
    $invoiceColumnsA = Mockery::mock(PDOStatement::class);
    $invoiceColumnsA->expects('execute')->with([])->andReturnTrue();
    $invoiceColumnsA->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $invoiceColumnsB = Mockery::mock(PDOStatement::class);
    $invoiceColumnsB->expects('execute')->with([])->andReturnTrue();
    $invoiceColumnsB->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $invoiceIndexesA = Mockery::mock(PDOStatement::class);
    $invoiceIndexesA->expects('execute')->with([])->andReturnTrue();
    $invoiceIndexesA->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $invoiceIndexesB = Mockery::mock(PDOStatement::class);
    $invoiceIndexesB->expects('execute')->with([])->andReturnTrue();
    $invoiceIndexesB->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([]);

    $addReplacesColumn = Mockery::mock(PDOStatement::class);
    $addReplacesColumn->expects('execute')->with([])->andReturnTrue();
    $addReplacesIndex = Mockery::mock(PDOStatement::class);
    $addReplacesIndex->expects('execute')->with([])->andReturnTrue();
    $addReplacedByColumn = Mockery::mock(PDOStatement::class);
    $addReplacedByColumn->expects('execute')->with([])->andReturnTrue();
    $addReplacedByIndex = Mockery::mock(PDOStatement::class);
    $addReplacedByIndex->expects('execute')->with([])->andReturnTrue();

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `invoice`')->twice()->andReturn($invoiceColumnsA, $invoiceColumnsB);
    $pdo->expects('prepare')->with('SHOW INDEX FROM `invoice`')->twice()->andReturn($invoiceIndexesA, $invoiceIndexesB);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `invoice` ADD COLUMN `replaces_invoice_id` bigint(20) DEFAULT NULL AFTER `debit_note_for_invoice_id`')
        ->andReturn($addReplacesColumn);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `invoice` ADD INDEX `invoice_replaces_invoice_idx` (`replaces_invoice_id`)')
        ->andReturn($addReplacesIndex);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `invoice` ADD COLUMN `replaced_by_invoice_id` bigint(20) DEFAULT NULL AFTER `replaces_invoice_id`')
        ->andReturn($addReplacedByColumn);
    $pdo->expects('prepare')
        ->with('ALTER TABLE `invoice` ADD INDEX `invoice_replaced_by_invoice_idx` (`replaced_by_invoice_id`)')
        ->andReturn($addReplacedByIndex);

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch120'))->invoke($patcher);
});

test('invoice reissue patch is a no-op when the replacement columns and indexes already exist', function (): void {
    $invoiceColumnsA = Mockery::mock(PDOStatement::class);
    $invoiceColumnsA->expects('execute')->with([])->andReturnTrue();
    $invoiceColumnsA->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Field' => 'replaces_invoice_id'],
        ['Field' => 'replaced_by_invoice_id'],
    ]);

    $invoiceColumnsB = Mockery::mock(PDOStatement::class);
    $invoiceColumnsB->expects('execute')->with([])->andReturnTrue();
    $invoiceColumnsB->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Field' => 'replaces_invoice_id'],
        ['Field' => 'replaced_by_invoice_id'],
    ]);

    $invoiceIndexesA = Mockery::mock(PDOStatement::class);
    $invoiceIndexesA->expects('execute')->with([])->andReturnTrue();
    $invoiceIndexesA->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Key_name' => 'invoice_replaces_invoice_idx'],
        ['Key_name' => 'invoice_replaced_by_invoice_idx'],
    ]);

    $invoiceIndexesB = Mockery::mock(PDOStatement::class);
    $invoiceIndexesB->expects('execute')->with([])->andReturnTrue();
    $invoiceIndexesB->expects('fetchAll')->with(PDO::FETCH_ASSOC)->andReturn([
        ['Key_name' => 'invoice_replaces_invoice_idx'],
        ['Key_name' => 'invoice_replaced_by_invoice_idx'],
    ]);

    $pdo = Mockery::mock(PDO::class);
    $pdo->expects('prepare')->with('SHOW COLUMNS FROM `invoice`')->twice()->andReturn($invoiceColumnsA, $invoiceColumnsB);
    $pdo->expects('prepare')->with('SHOW INDEX FROM `invoice`')->twice()->andReturn($invoiceIndexesA, $invoiceIndexesB);
    $pdo->shouldNotReceive('prepare')->with(Mockery::on(fn (string $sql): bool => str_starts_with($sql, 'ALTER TABLE')));

    $di = new Pimple\Container();
    $di['pdo'] = $pdo;

    $patcher = new UpdatePatcher();
    $patcher->setDi($di);
    (new ReflectionMethod($patcher, 'patch120'))->invoke($patcher);
});

test('ensureSchemaInSync restores reissue columns missing from an older schema, then goes quiet', function (): void {
    // Same upgrade path as the note-columns test above, for the invoice reissue
    // release: https://github.com/FOSSBilling/FOSSBilling/issues/4392
    $dbFile = Path::join(sys_get_temp_dir(), 'fossbilling-schema-sync-reissue-' . bin2hex(random_bytes(8)) . '.sqlite');

    try {
        $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $dbFile]);
        $entityManager = FOSSBilling\Doctrine\EntityManagerFactory::create($connection);
        FOSSBilling\Doctrine\SchemaInstaller::createSchema($entityManager);

        $connection->executeStatement('DROP INDEX invoice_replaces_invoice_idx');
        $connection->executeStatement('DROP INDEX invoice_replaced_by_invoice_idx');
        $connection->executeStatement('ALTER TABLE invoice DROP COLUMN replaces_invoice_id');
        $connection->executeStatement('ALTER TABLE invoice DROP COLUMN replaced_by_invoice_id');

        $columnNames = static fn (): array => array_column(
            $connection->fetchAllAssociative('PRAGMA table_info(invoice)'),
            'name'
        );
        expect($columnNames())->not->toContain('replaces_invoice_id', 'replaced_by_invoice_id');

        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $di = new Pimple\Container();
        $di['pdo'] = $pdo;
        $di['em'] = $entityManager;
        $di['logger'] = new Tests\Helpers\TestLogger();

        $patcher = new UpdatePatcher();
        $patcher->setDi($di);

        expect($patcher->ensureSchemaInSync())->toBeTrue()
            ->and($columnNames())->toContain('replaces_invoice_id', 'replaced_by_invoice_id');

        // The recorded hash now matches, so the next request is a single-SELECT no-op.
        expect($patcher->ensureSchemaInSync())->toBeFalse();
    } finally {
        (new Filesystem())->remove($dbFile);
    }
});
