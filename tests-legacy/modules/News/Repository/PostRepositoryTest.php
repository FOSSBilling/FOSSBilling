<?php

declare(strict_types=1);

namespace Box\Mod\News\Repository;

use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class PostRepositoryTest extends \BBTestCase
{
    public function testGetSearchQueryBuilderBuildsAllSupportedFilters(): void
    {
        $whereCalls = [];
        $parameters = [];

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['andWhere', 'setParameter', 'orderBy'])
            ->getMock();

        $queryBuilder->expects($this->exactly(4))
            ->method('andWhere')
            ->willReturnCallback(function (string $clause) use (&$whereCalls, $queryBuilder) {
                $whereCalls[] = $clause;

                return $queryBuilder;
            });

        $queryBuilder->expects($this->exactly(4))
            ->method('setParameter')
            ->willReturnCallback(function (string $name, mixed $value) use (&$parameters, $queryBuilder) {
                $parameters[$name] = $value;

                return $queryBuilder;
            });

        $queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('p.createdAt', 'DESC')
            ->willReturn($queryBuilder);

        $repository = $this->getMockBuilder(PostRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('p')
            ->willReturn($queryBuilder);

        $result = $repository->getSearchQueryBuilder([
            'id' => '7',
            'status' => 'active',
            'search' => 'release',
            'section' => 'billing',
        ]);

        $this->assertSame($queryBuilder, $result);
        $this->assertSame([
            'p.id = :id',
            'p.status = :status',
            '(p.title LIKE :search OR p.slug LIKE :search OR COALESCE(p.description, \'\') LIKE :search OR COALESCE(p.section, \'\') LIKE :search OR COALESCE(p.content, \'\') LIKE :search)',
            'p.section LIKE :section',
        ], $whereCalls);
        $this->assertSame([
            'id' => 7,
            'status' => 'active',
            'search' => '%release%',
            'section' => '%billing%',
        ], $parameters);
    }
}
