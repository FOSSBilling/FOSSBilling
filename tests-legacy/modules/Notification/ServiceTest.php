<?php

declare(strict_types=1);

namespace Box\Mod\Notification;

use Box\Mod\Extension\Entity\ExtensionMeta;
use Box\Mod\Extension\Repository\ExtensionMetaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    public function testGetSearchQueryBuilderAppliesSupportedFilters(): void
    {
        $whereCalls = [];
        $parameters = [];

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['andWhere', 'setParameter', 'orderBy'])
            ->getMock();

        $queryBuilder->expects($this->exactly(5))
            ->method('andWhere')
            ->willReturnCallback(function (string $clause) use (&$whereCalls, $queryBuilder) {
                $whereCalls[] = $clause;

                return $queryBuilder;
            });

        $queryBuilder->expects($this->exactly(5))
            ->method('setParameter')
            ->willReturnCallback(function (string $name, mixed $value) use (&$parameters, $queryBuilder) {
                $parameters[$name] = $value;

                return $queryBuilder;
            });

        $queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('n.id', 'DESC')
            ->willReturn($queryBuilder);

        $repository = $this->getMockBuilder(ExtensionMetaRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilderForExtension'])
            ->getMock();

        $repository->expects($this->once())
            ->method('createQueryBuilderForExtension')
            ->with('mod_notification', 'n')
            ->willReturn($queryBuilder);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('getRepository')
            ->with(ExtensionMeta::class)
            ->willReturn($repository);

        $di = $this->getDi();
        $di['em'] = $em;

        $service = new Service();
        $service->setDi($di);

        $result = $service->getSearchQueryBuilder([
            'id' => '9',
            'search' => 'backup',
            'date_from' => '2026-02-10',
            'date_to' => '2026-02-11',
        ]);

        $this->assertSame($queryBuilder, $result);
        $this->assertSame([
            'n.metaKey = :metaKey',
            'n.id = :id',
            'n.metaValue LIKE :search',
            'n.createdAt >= :date_from',
            'n.createdAt <= :date_to',
        ], $whereCalls);
        $this->assertSame('message', $parameters['metaKey']);
        $this->assertSame(9, $parameters['id']);
        $this->assertSame('%backup%', $parameters['search']);
        $this->assertInstanceOf(\DateTime::class, $parameters['date_from']);
        $this->assertInstanceOf(\DateTime::class, $parameters['date_to']);
        $this->assertSame('2026-02-10 00:00:00', $parameters['date_from']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-11 23:59:59', $parameters['date_to']->format('Y-m-d H:i:s'));
    }
}
