<?php

declare(strict_types=1);

use Box\Mod\Extension\Entity\ExtensionMeta;
use Box\Mod\Extension\Repository\ExtensionMetaRepository;
use Box\Mod\Notification\Event\AfterAdminNotificationAddEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

test('get search query builder applies supported filters', function (): void {
    $whereCalls = [];
    $parameters = [];

    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('andWhere')->times(5)->andReturnUsing(function (string $clause) use (&$whereCalls, $queryBuilder) {
        $whereCalls[] = $clause;

        return $queryBuilder;
    });
    $queryBuilder->shouldReceive('setParameter')->times(5)->andReturnUsing(function (string $name, mixed $value) use (&$parameters, $queryBuilder) {
        $parameters[$name] = $value;

        return $queryBuilder;
    });
    $queryBuilder->shouldReceive('orderBy')->with('n.id', 'DESC')->once()->andReturn($queryBuilder);

    $repository = Mockery::mock(ExtensionMetaRepository::class)->makePartial()->shouldIgnoreMissing();
    $repository->shouldReceive('createQueryBuilderForExtension')->with('mod_notification', 'n')->once()->andReturn($queryBuilder);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(ExtensionMeta::class)->once()->andReturn($repository);

    $di = new Pimple\Container();
    $di['em'] = $em;

    $service = new Box\Mod\Notification\Service();
    $service->setDi($di);

    $result = $service->getSearchQueryBuilder([
        'id' => '9',
        'search' => 'backup',
        'date_from' => '2026-02-10',
        'date_to' => '2026-02-11',
    ]);

    expect($result)->toBe($queryBuilder);
    expect($whereCalls)->toBe([
        'n.metaKey = :metaKey',
        'n.id = :id',
        'n.metaValue LIKE :search',
        'n.createdAt >= :date_from',
        'n.createdAt <= :date_to',
    ]);
    expect($parameters['metaKey'])->toBe('message');
    expect($parameters['id'])->toBe(9);
    expect($parameters['search'])->toBe('%backup%');
    expect($parameters['date_from'])->toBeInstanceOf(DateTime::class);
    expect($parameters['date_to'])->toBeInstanceOf(DateTime::class);
    expect($parameters['date_from']->format('Y-m-d H:i:s'))->toBe('2026-02-10 00:00:00');
    expect($parameters['date_to']->format('Y-m-d H:i:s'))->toBe('2026-02-11 23:59:59');
});

test('sorts notification search query', function (array $filter, string $expectedOrder, string $expectedDirection, ?string $expectedTieBreakerDirection): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('andWhere')->once()->with('n.metaKey = :metaKey')->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('setParameter')->once()->with('metaKey', 'message')->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('orderBy')->with($expectedOrder, $expectedDirection)->once()->andReturn($queryBuilder);
    if ($expectedTieBreakerDirection !== null) {
        $queryBuilder->shouldReceive('addOrderBy')->with('n.id', $expectedTieBreakerDirection)->once()->andReturn($queryBuilder);
    } else {
        $queryBuilder->shouldReceive('addOrderBy')->never();
    }

    $repository = Mockery::mock(ExtensionMetaRepository::class)->makePartial()->shouldIgnoreMissing();
    $repository->shouldReceive('createQueryBuilderForExtension')->with('mod_notification', 'n')->once()->andReturn($queryBuilder);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(ExtensionMeta::class)->once()->andReturn($repository);

    $di = new Pimple\Container();
    $di['em'] = $em;

    $service = new Box\Mod\Notification\Service();
    $service->setDi($di);

    expect($service->getSearchQueryBuilder($filter))->toBe($queryBuilder);
})->with([
    'created at ascending' => [['sort' => 'created_at'], 'n.createdAt', 'ASC', 'ASC'],
    'created at descending' => [['sort' => 'created_at', 'direction' => 'DESC'], 'n.createdAt', 'DESC', 'DESC'],
    'updated at' => [['sort' => 'updated_at', 'direction' => 'desc'], 'n.updatedAt', 'DESC', 'DESC'],
    'id' => [['sort' => 'id'], 'n.id', 'ASC', null],
    'invalid sort falls back to default' => [['sort' => 'n.metaValue; DROP TABLE extension_meta'], 'n.id', 'DESC', null],
    'invalid direction falls back to ascending' => [['sort' => 'created_at', 'direction' => 'sideways'], 'n.createdAt', 'ASC', 'ASC'],
]);

test('create dispatches the typed event after persisting the notification', function (): void {
    $calls = (object) ['steps' => [], 'events' => []];
    $eventDispatcher = new readonly class($calls) {
        public function __construct(private object $calls)
        {
        }

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->calls->steps[] = 'typed';
            $this->calls->events[] = $event;

            return $event;
        }
    };

    $repository = Mockery::mock(ExtensionMetaRepository::class)->makePartial()->shouldIgnoreMissing();
    $entityManager = Mockery::mock(EntityManagerInterface::class);
    $entityManager->shouldReceive('getRepository')->with(ExtensionMeta::class)->once()->andReturn($repository);
    $entityManager->shouldReceive('persist')->once()->with(Mockery::type(ExtensionMeta::class))->andReturnUsing(function (ExtensionMeta $meta) use ($calls): void {
        $calls->steps[] = 'persist';
        (new ReflectionProperty(ExtensionMeta::class, 'id'))->setValue($meta, 42);
    });
    $entityManager->shouldReceive('flush')->once()->andReturnUsing(function () use ($calls): void {
        $calls->steps[] = 'flush';
    });

    $di = new Pimple\Container();
    $di['em'] = $entityManager;
    $di['event_dispatcher'] = $eventDispatcher;

    $service = new Box\Mod\Notification\Service();
    $service->setDi($di);

    expect($service->create('Maintenance tonight'))->toBe(42)
        ->and($calls->steps)->toBe(['persist', 'flush', 'typed'])
        ->and($calls->events)->toHaveCount(1)
        ->and($calls->events[0])->toBeInstanceOf(AfterAdminNotificationAddEvent::class)
        ->and($calls->events[0]->notificationId)->toBe(42);
});
