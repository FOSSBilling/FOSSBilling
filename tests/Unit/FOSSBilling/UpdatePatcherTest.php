<?php

declare(strict_types=1);

use FOSSBilling\UpdatePatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

test('client balance gateway repair follows the legacy email template repair', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 89);

    expect($patches)->toHaveKey(90)
        ->and($patches[90][1])->toBe('patch90')
        ->and($patches)->toHaveKey(91)
        ->and($patches[91][1])->toBe('patch91');
});

test('manual currency rate patch follows the currency formatting patch', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 95);

    expect($patches)->toHaveKey(96)
        ->and($patches[96][1])->toBe('patch96');
});

test('invoice item attempts patch follows the manual currency rate patch', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 96);

    expect($patches)->toHaveCount(1)
        ->toHaveKey(97)
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

test('custom pages slug unique patch follows the client balance gateway repair', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 91);

    expect($patches)->toHaveKey(92)
        ->and($patches[92][1])->toBe('patch92');
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
    (new ReflectionMethod($patcher, 'patch92'))->invoke($patcher);
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
    (new ReflectionMethod($patcher, 'patch92'))->invoke($patcher);
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
    (new ReflectionMethod($patcher, 'patch92'))->invoke($patcher);
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
    (new ReflectionMethod($patcher, 'patch92'))->invoke($patcher);
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
    (new ReflectionMethod($patcher, 'patch92'))->invoke($patcher);
});

test('client group patch follows the custom pages slug unique patch', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 92);

    expect($patches)->toHaveKey(93)
        ->and($patches[93][1])->toBe('patch93');
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
    (new ReflectionMethod($patcher, 'patch93'))->invoke($patcher);
});

test('client balance unique credit patch follows the client group patch', function (): void {
    $patches = (new ReflectionMethod(UpdatePatcher::class, 'getPatches'))->invoke(new UpdatePatcher(), 93);

    expect($patches)->toHaveKey(94)
        ->and($patches[94][1])->toBe('patch94');
});

test('fresh installs constrain client balance to one credit per invoice item', function (): void {
    $filesystem = new Filesystem();
    $structure = $filesystem->readFile(Path::join(PATH_ROOT, 'install', 'sql', 'structure.sql'));

    expect($structure)->toContain('`invoice_item_id` bigint(20) DEFAULT NULL')
        ->and($structure)->toContain('UNIQUE KEY `uniq_invoice_item_credit` (`invoice_item_id`)');
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
    (new ReflectionMethod($patcher, 'patch94'))->invoke($patcher);
});
