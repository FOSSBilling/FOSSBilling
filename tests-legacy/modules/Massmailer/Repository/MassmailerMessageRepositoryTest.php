<?php

declare(strict_types=1);

namespace Box\Mod\Massmailer\Repository;

use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class MassmailerMessageRepositoryTest extends \BBTestCase
{
    public function testGetSearchQueryBuilderGroupsSearchClauseWhenStatusFilterIsPresent(): void
    {
        $whereCalls = [];
        $parameters = [];

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['andWhere', 'setParameter', 'orderBy'])
            ->getMock();

        $queryBuilder->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnCallback(function (string $clause) use (&$whereCalls, $queryBuilder) {
                $whereCalls[] = $clause;

                return $queryBuilder;
            });

        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnCallback(function (string $name, mixed $value) use (&$parameters, $queryBuilder) {
                $parameters[$name] = $value;

                return $queryBuilder;
            });

        $queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('m.createdAt', 'DESC')
            ->willReturn($queryBuilder);

        $repository = $this->getMockBuilder(MassmailerMessageRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('m')
            ->willReturn($queryBuilder);

        $result = $repository->getSearchQueryBuilder([
            'status' => 'draft',
            'search' => 'newsletter',
        ]);

        $this->assertSame($queryBuilder, $result);
        $this->assertSame([
            'm.status = :status',
            '(m.subject LIKE :search OR m.content LIKE :search OR m.fromEmail LIKE :search OR m.fromName LIKE :search)',
        ], $whereCalls);
        $this->assertSame([
            'status' => 'draft',
            'search' => '%newsletter%',
        ], $parameters);
    }
}
