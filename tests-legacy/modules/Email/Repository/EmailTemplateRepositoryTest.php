<?php

declare(strict_types=1);

namespace Box\Mod\Email\Repository;

use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class EmailTemplateRepositoryTest extends \BBTestCase
{
    public function testGetSearchQueryBuilderAppliesStructuredFilters(): void
    {
        $whereCalls = [];
        $parameters = [];

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['orderBy', 'addOrderBy', 'andWhere', 'setParameter'])
            ->getMock();

        $queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('t.category', 'ASC')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('addOrderBy')
            ->with('t.actionCode', 'ASC')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->exactly(6))
            ->method('andWhere')
            ->willReturnCallback(function (string $clause) use (&$whereCalls, $queryBuilder) {
                $whereCalls[] = $clause;

                return $queryBuilder;
            });

        $queryBuilder->expects($this->exactly(6))
            ->method('setParameter')
            ->willReturnCallback(function (string $name, mixed $value) use (&$parameters, $queryBuilder) {
                $parameters[$name] = $value;

                return $queryBuilder;
            });

        $repository = $this->getMockBuilder(EmailTemplateRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('t')
            ->willReturn($queryBuilder);

        $result = $repository->getSearchQueryBuilder([
            'id' => '12',
            'code' => 'invoice_paid',
            'category' => 'billing',
            'enabled' => '0',
            'type' => 'custom',
            'search' => 'receipt',
        ]);

        $this->assertSame($queryBuilder, $result);
        $this->assertCount(6, $whereCalls);
        $this->assertSame('t.id = :id', $whereCalls[0]);
        $this->assertSame('t.actionCode LIKE :code', $whereCalls[1]);
        $this->assertSame('COALESCE(t.category, \'\') LIKE :category', $whereCalls[2]);
        $this->assertSame('t.enabled = :enabled', $whereCalls[3]);
        $this->assertSame('t.isCustom = :is_custom', $whereCalls[4]);
        $this->assertStringContainsString('t.actionCode LIKE :search', $whereCalls[5]);
        $this->assertStringContainsString('COALESCE(t.subject, \'\') LIKE :search', $whereCalls[5]);
        $this->assertStringContainsString('COALESCE(t.description, \'\') LIKE :search', $whereCalls[5]);
        $this->assertSame(12, $parameters['id']);
        $this->assertSame('%invoice_paid%', $parameters['code']);
        $this->assertSame('%billing%', $parameters['category']);
        $this->assertFalse($parameters['enabled']);
        $this->assertTrue($parameters['is_custom']);
        $this->assertSame('%receipt%', $parameters['search']);
    }
}
