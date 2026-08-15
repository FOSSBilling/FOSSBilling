<?php

declare(strict_types=1);

use Box\Mod\Custompages\Entity\CustomPage;
use Box\Mod\Custompages\Service;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use FOSSBilling\Pagination;
use FOSSBilling\PaginationOptions;

function buildCustompagesService(object $repo, ?EntityManagerInterface $em = null, ?Pimple\Container $extra = null): Service
{
    $di = new Pimple\Container();

    $em ??= Mockery::mock(EntityManagerInterface::class);
    $em->allows('getRepository')->with(CustomPage::class)->andReturn($repo);

    $di['em'] = $em;
    // The production logger follows PSR-3; this test only needs a lightweight stub.
    $di['logger'] = Mockery::mock()->shouldIgnoreMissing();
    $di['tools'] = Mockery::mock(FOSSBilling\Tools::class);
    $di['tools']->allows('slug')->andReturnUsing(fn ($s): string => strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', (string) $s)));

    if ($extra !== null) {
        foreach ($extra->keys() as $key) {
            $di[$key] = $extra[$key];
        }
    }

    $service = new Service();
    $service->setDi($di);

    return $service;
}

test('search pages delegates to repository query builder and doctrine paginator', function (): void {
    $qb = Mockery::mock(QueryBuilder::class);
    $repo = Mockery::mock(Box\Mod\Custompages\Repository\CustomPageRepository::class);
    $repo->expects('getSearchQueryBuilder')->with(['search' => 'landing'])->andReturn($qb);

    $pager = Mockery::mock(Pagination::class);
    $pager->expects('paginateDoctrineQuery')->with($qb, Mockery::on(fn ($o): bool => $o instanceof PaginationOptions))->andReturn(['list' => [], 'total' => 0]);

    $extra = new Pimple\Container();
    $extra['pager'] = $pager;

    $service = buildCustompagesService($repo, extra: $extra);

    expect($service->searchPages(['search' => 'landing']))->toBe(['list' => [], 'total' => 0]);
});

test('get page by id returns api array when found', function (): void {
    $page = Tests\Helpers\createEntity(CustomPage::class, [
        'id' => 7,
        'title' => 'About',
        'description' => 'desc',
        'keywords' => 'kw',
        'content' => 'body',
        'slug' => 'about',
    ]);

    $repo = Mockery::mock(Box\Mod\Custompages\Repository\CustomPageRepository::class);
    $repo->expects('find')->with(7)->andReturn($page);

    $service = buildCustompagesService($repo);

    expect($service->getPage(7))->toBe([
        'id' => 7,
        'title' => 'About',
        'description' => 'desc',
        'keywords' => 'kw',
        'content' => 'body',
        'slug' => 'about',
        'created_at' => null,
    ]);
});

test('get page by slug uses slug finder', function (): void {
    $page = Tests\Helpers\createEntity(CustomPage::class, ['id' => 3, 'title' => 'T', 'description' => '', 'keywords' => '', 'content' => 'C', 'slug' => 'docs']);

    $repo = Mockery::mock(Box\Mod\Custompages\Repository\CustomPageRepository::class);
    $repo->expects('findOneBySlug')->with('docs')->andReturn($page);

    $service = buildCustompagesService($repo);

    expect($service->getPage('docs', 'slug'))->toBe([
        'id' => 3,
        'title' => 'T',
        'description' => '',
        'keywords' => '',
        'content' => 'C',
        'slug' => 'docs',
        'created_at' => null,
    ]);
});

test('get page returns null when not found', function (): void {
    $repo = Mockery::mock(Box\Mod\Custompages\Repository\CustomPageRepository::class);
    $repo->expects('find')->with(99)->andReturn(null);

    $service = buildCustompagesService($repo);

    expect($service->getPage(99))->toBeNull();
});

test('get page rejects unknown column type', function (): void {
    $repo = Mockery::mock(Box\Mod\Custompages\Repository\CustomPageRepository::class);

    $service = buildCustompagesService($repo);

    expect(fn (): ?array => $service->getPage(1, 'title'))->toThrow(FOSSBilling\Exception::class);
});

test('create page generates unique slug and inserts via dbal', function (): void {
    $repo = Mockery::mock(Box\Mod\Custompages\Repository\CustomPageRepository::class);
    $repo->expects('findOneBySlug')->with('about-us')->andReturn(null);

    $captured = null;
    $connection = Mockery::mock(Connection::class);
    $connection->expects('insert')->once()->andReturnUsing(function (string $table, array $data) use (&$captured): int {
        expect($table)->toBe('custom_pages');
        $captured = $data;

        return 1;
    });
    $connection->expects('lastInsertId')->once()->andReturn(42);

    $logger = Mockery::mock();
    $logger->expects('info')->with('Created new custom page #{id}', ['id' => 42])->once();

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->allows('getRepository')->with(CustomPage::class)->andReturn($repo);
    $em->allows('getConnection')->andReturn($connection);

    $di = new Pimple\Container();
    $di['em'] = $em;
    $di['logger'] = $logger;
    $di['tools'] = Mockery::mock(FOSSBilling\Tools::class);
    $di['tools']->allows('slug')->with('About Us')->andReturn('about-us');

    $service = new Service();
    $service->setDi($di);

    $id = $service->createPage('About Us', null, null, 'page body');

    expect($id)->toBe(42);
    expect($captured['title'])->toBe('About Us');
    expect($captured['description'])->toBe('');
    expect($captured['keywords'])->toBe('');
    expect($captured['content'])->toBe('page body');
    expect($captured['slug'])->toBe('about-us');
});

test('create page appends incrementing suffix until slug is unique', function (): void {
    $existing = Tests\Helpers\createEntity(CustomPage::class, ['id' => 1, 'slug' => 'page']);

    $repo = Mockery::mock(Box\Mod\Custompages\Repository\CustomPageRepository::class);
    $repo->expects('findOneBySlug')->with('page')->andReturn($existing);
    $repo->expects('findOneBySlug')->with('page-1')->andReturn($existing);
    $repo->expects('findOneBySlug')->with('page-2')->andReturn(null);

    $captured = null;
    $connection = Mockery::mock(Connection::class);
    $connection->expects('insert')->once()->andReturnUsing(function (string $table, array $data) use (&$captured): int {
        $captured = $data;

        return 1;
    });
    $connection->allows('lastInsertId')->andReturn(3);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->allows('getRepository')->with(CustomPage::class)->andReturn($repo);
    $em->allows('getConnection')->andReturn($connection);

    $di = new Pimple\Container();
    $di['em'] = $em;
    $di['logger'] = Mockery::mock()->shouldIgnoreMissing();
    $di['tools'] = Mockery::mock(FOSSBilling\Tools::class);
    $di['tools']->allows('slug')->with('Page')->andReturn('page');

    $service = new Service();
    $service->setDi($di);

    $service->createPage('Page', '', '', 'content');

    expect($captured['slug'])->toBe('page-2');
});

test('update page throws when page not found', function (): void {
    $repo = Mockery::mock(Box\Mod\Custompages\Repository\CustomPageRepository::class);
    $repo->expects('find')->with(5)->andReturn(null);

    $service = buildCustompagesService($repo);

    expect(fn (): int => $service->updatePage(5, 't', '', '', 'c', 'slug'))->toThrow(FOSSBilling\Exception::class, 'Custom page not found');
});

test('update page throws on duplicate slug with legacy code', function (): void {
    $page = Tests\Helpers\createEntity(CustomPage::class, ['id' => 5, 'title' => 'T', 'description' => '', 'keywords' => '', 'content' => 'C', 'slug' => 'old']);
    $existing = Tests\Helpers\createEntity(CustomPage::class, ['id' => 9, 'slug' => 'taken']);

    $repo = Mockery::mock(Box\Mod\Custompages\Repository\CustomPageRepository::class);
    $repo->expects('find')->with(5)->andReturn($page);
    $repo->expects('findOneBySlugExcludingId')->with('taken', 5)->andReturn($existing);

    $service = buildCustompagesService($repo);

    expect(fn (): int => $service->updatePage(5, 'T', '', '', 'C', 'taken'))
        ->toThrow(fn (FOSSBilling\Exception $e): bool => $e->getCode() === 9999);
});

test('update page applies setters and returns id', function (): void {
    $page = Tests\Helpers\createEntity(CustomPage::class, ['id' => 5, 'title' => 'Old', 'description' => '', 'keywords' => '', 'content' => 'old', 'slug' => 'old']);

    $repo = Mockery::mock(Box\Mod\Custompages\Repository\CustomPageRepository::class);
    $repo->expects('find')->with(5)->andReturn($page);
    $repo->expects('findOneBySlugExcludingId')->with('new', 5)->andReturn(null);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->allows('getRepository')->with(CustomPage::class)->andReturn($repo);
    $em->expects('flush')->once();

    $di = new Pimple\Container();
    $di['em'] = $em;
    $di['logger'] = Mockery::mock()->shouldIgnoreMissing();
    $di['tools'] = Mockery::mock(FOSSBilling\Tools::class);
    $di['tools']->allows('slug')->with('New Slug')->andReturn('new');

    $service = new Service();
    $service->setDi($di);

    $id = $service->updatePage(5, 'New Title', 'd', 'k', 'new content', 'New Slug');

    expect($id)->toBe(5);
    expect($page->getTitle())->toBe('New Title');
    expect($page->getDescription())->toBe('d');
    expect($page->getKeywords())->toBe('k');
    expect($page->getContent())->toBe('new content');
    expect($page->getSlug())->toBe('new');
});

test('delete page by scalar removes the entity', function (): void {
    $page = Tests\Helpers\createEntity(CustomPage::class, ['id' => 3, 'title' => 'T', 'description' => '', 'keywords' => '', 'content' => 'C', 'slug' => 's']);

    $repo = Mockery::mock(Box\Mod\Custompages\Repository\CustomPageRepository::class);
    $repo->expects('find')->with(3)->andReturn($page);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->allows('getRepository')->with(CustomPage::class)->andReturn($repo);
    $em->expects('remove')->with($page)->once();
    $em->expects('flush')->once();

    $di = new Pimple\Container();
    $di['em'] = $em;
    $di['logger'] = Mockery::mock()->shouldIgnoreMissing();
    $di['tools'] = Mockery::mock(FOSSBilling\Tools::class);

    $service = new Service();
    $service->setDi($di);

    $service->deletePage(3);
});

test('delete page by scalar is a no-op when not found', function (): void {
    $repo = Mockery::mock(Box\Mod\Custompages\Repository\CustomPageRepository::class);
    $repo->expects('find')->with(404)->andReturn(null);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->allows('getRepository')->with(CustomPage::class)->andReturn($repo);
    $em->expects('remove')->never();
    $em->expects('flush')->never();

    $di = new Pimple\Container();
    $di['em'] = $em;
    $di['logger'] = Mockery::mock()->shouldIgnoreMissing();
    $di['tools'] = Mockery::mock(FOSSBilling\Tools::class);

    $service = new Service();
    $service->setDi($di);

    $service->deletePage(404);
});

test('delete page by array delegates to bulk delete', function (): void {
    $repo = Mockery::mock(Box\Mod\Custompages\Repository\CustomPageRepository::class);
    $repo->expects('deleteByIds')->with([1, 2, 3])->andReturn(3);

    $service = buildCustompagesService($repo);

    $service->deletePage(['1', '2', '3']);
});

test('create page retries on a concurrent slug conflict and succeeds on the next candidate', function (): void {
    // Attempt 1: 'about' looks free, but a concurrent insert claims it (constraint violation).
    // Attempt 2: generateUniqueSlug now sees 'about' taken and moves on to 'about-1'.
    $existing = Tests\Helpers\createEntity(CustomPage::class, ['id' => 9, 'slug' => 'about']);

    $repo = Mockery::mock(Box\Mod\Custompages\Repository\CustomPageRepository::class);
    $repo->shouldReceive('findOneBySlug')->andReturn(null, $existing, null);

    $insertCount = 0;
    $captured = null;
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('insert')->andReturnUsing(function (string $table, array $data) use (&$insertCount, &$captured): int {
        ++$insertCount;
        $captured = $data;
        if ($insertCount === 1) {
            throw Mockery::mock(UniqueConstraintViolationException::class);
        }

        return 1;
    });
    $connection->expects('lastInsertId')->once()->andReturn(7);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->allows('getRepository')->with(CustomPage::class)->andReturn($repo);
    $em->allows('getConnection')->andReturn($connection);
    $em->shouldNotReceive('persist');
    $em->shouldNotReceive('flush');
    $em->shouldNotReceive('clear');

    $di = new Pimple\Container();
    $di['em'] = $em;
    $di['logger'] = Mockery::mock()->shouldIgnoreMissing();
    $di['tools'] = Mockery::mock(FOSSBilling\Tools::class);
    $di['tools']->allows('slug')->with('About')->andReturn('about');

    $service = new Service();
    $service->setDi($di);

    expect($service->createPage('About', '', '', 'content'))->toBe(7);
    expect($captured['slug'])->toBe('about-1');
});

test('create page gives up after repeated slug conflicts', function (): void {
    $repo = Mockery::mock(Box\Mod\Custompages\Repository\CustomPageRepository::class);
    $repo->shouldReceive('findOneBySlug')->andReturn(null);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('insert')->andThrow(Mockery::mock(UniqueConstraintViolationException::class));
    $connection->shouldNotReceive('lastInsertId');

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->allows('getRepository')->with(CustomPage::class)->andReturn($repo);
    $em->allows('getConnection')->andReturn($connection);
    $em->shouldNotReceive('persist');
    $em->shouldNotReceive('flush');
    $em->shouldNotReceive('clear');

    $di = new Pimple\Container();
    $di['em'] = $em;
    $di['logger'] = Mockery::mock()->shouldIgnoreMissing();
    $di['tools'] = Mockery::mock(FOSSBilling\Tools::class);
    $di['tools']->allows('slug')->with('Title')->andReturn('title');

    $service = new Service();
    $service->setDi($di);

    expect(fn (): int => $service->createPage('Title', '', '', 'content'))
        ->toThrow(FOSSBilling\Exception::class, 'Unable to generate a unique slug');
});

test('update page surfaces a concurrent constraint violation as the uniqueness error', function (): void {
    $page = Tests\Helpers\createEntity(CustomPage::class, ['id' => 5, 'title' => 'Old', 'description' => '', 'keywords' => '', 'content' => 'old', 'slug' => 'old']);

    $repo = Mockery::mock(Box\Mod\Custompages\Repository\CustomPageRepository::class);
    $repo->expects('find')->with(5)->andReturn($page);
    // App-level check passes (no conflict visible yet), but the DB rejects the concurrent slug.
    $repo->expects('findOneBySlugExcludingId')->with('new', 5)->andReturn(null);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->allows('getRepository')->with(CustomPage::class)->andReturn($repo);
    $em->expects('flush')->once()->andThrow(Mockery::mock(UniqueConstraintViolationException::class));

    $di = new Pimple\Container();
    $di['em'] = $em;
    $di['logger'] = Mockery::mock()->shouldIgnoreMissing();
    $di['tools'] = Mockery::mock(FOSSBilling\Tools::class);
    $di['tools']->allows('slug')->with('New Slug')->andReturn('new');

    $service = new Service();
    $service->setDi($di);

    expect(fn (): int => $service->updatePage(5, 'New', '', '', 'content', 'New Slug'))
        ->toThrow(fn (FOSSBilling\Exception $e): bool => $e->getCode() === 9999);
});

test('create page truncates a long title slug to fit varchar 255', function (): void {
    $longSlug = str_repeat('a', 260);

    $repo = Mockery::mock(Box\Mod\Custompages\Repository\CustomPageRepository::class);
    $repo->expects('findOneBySlug')->with(str_repeat('a', 255))->andReturn(null);

    $captured = null;
    $connection = Mockery::mock(Connection::class);
    $connection->expects('insert')->once()->andReturnUsing(function (string $table, array $data) use (&$captured): int {
        $captured = $data;

        return 1;
    });
    $connection->allows('lastInsertId')->andReturn(1);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->allows('getRepository')->with(CustomPage::class)->andReturn($repo);
    $em->allows('getConnection')->andReturn($connection);

    $di = new Pimple\Container();
    $di['em'] = $em;
    $di['logger'] = Mockery::mock()->shouldIgnoreMissing();
    $di['tools'] = Mockery::mock(FOSSBilling\Tools::class);
    $di['tools']->allows('slug')->with('Long Title')->andReturn($longSlug);

    $service = new Service();
    $service->setDi($di);

    $service->createPage('Long Title', '', '', 'content');

    expect(strlen($captured['slug']))->toBe(255);
});

test('create page reserves room for the suffix when truncating a conflicting long slug', function (): void {
    // A 254-char slug that already exists; the suffixed candidate must truncate the base
    // to leave room for "-1", yielding a 255-char slug (253 a's + "-1").
    $base = str_repeat('a', 254);
    $existing = Tests\Helpers\createEntity(CustomPage::class, ['id' => 1, 'slug' => $base]);

    $repo = Mockery::mock(Box\Mod\Custompages\Repository\CustomPageRepository::class);
    $repo->shouldReceive('findOneBySlug')->andReturn($existing, null);

    $captured = null;
    $connection = Mockery::mock(Connection::class);
    $connection->expects('insert')->once()->andReturnUsing(function (string $table, array $data) use (&$captured): int {
        $captured = $data;

        return 1;
    });
    $connection->allows('lastInsertId')->andReturn(1);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->allows('getRepository')->with(CustomPage::class)->andReturn($repo);
    $em->allows('getConnection')->andReturn($connection);

    $di = new Pimple\Container();
    $di['em'] = $em;
    $di['logger'] = Mockery::mock()->shouldIgnoreMissing();
    $di['tools'] = Mockery::mock(FOSSBilling\Tools::class);
    $di['tools']->allows('slug')->with('Long Title')->andReturn($base);

    $service = new Service();
    $service->setDi($di);

    $service->createPage('Long Title', '', '', 'content');

    expect($captured['slug'])->toBe(str_repeat('a', 253) . '-1');
    expect(strlen($captured['slug']))->toBe(255);
});
