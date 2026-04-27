<?php

declare(strict_types=1);

use FOSSBilling\InformationException;
use FOSSBilling\Pagination;
use FOSSBilling\PaginationOptions;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class FOSSBilling_PaginationTest extends BBTestCase
{
    private function createPagination(Box_Database $db): Pagination
    {
        $di = $this->getDi();
        $di['db'] = $db;

        $pagination = new Pagination();
        $pagination->setDi($di);

        return $pagination;
    }

    private function createDbMock(string $paginatedQuery, int $total = 100): Box_Database
    {
        $db = $this->getMockBuilder(Box_Database::class)
            ->disableOriginalConstructor()
            ->getMock();

        $db->expects($this->once())
            ->method('getAll')
            ->with($paginatedQuery, [])
            ->willReturn([]);

        $db->expects($this->once())
            ->method('getCell')
            ->with('SELECT COUNT(1) FROM (SELECT * FROM example) AS sub', [])
            ->willReturn($total);

        return $db;
    }

    public function testGetPaginatedResultSetUsesPaginationOptions(): void
    {
        $pagination = $this->createPagination(
            $this->createDbMock('SELECT * FROM example LIMIT 25, 25')
        );

        $result = $pagination->getPaginatedResultSet('SELECT * FROM example', [], PaginationOptions::fromArray(['page' => 2, 'per_page' => 25]));

        $this->assertSame(2, $result['page']);
        $this->assertSame(25, $result['per_page']);
        $this->assertSame(4, $result['pages']);
    }

    public function testGetPaginatedResultSetUsesPaginationOptionsConstructor(): void
    {
        $pagination = $this->createPagination(
            $this->createDbMock('SELECT * FROM example LIMIT 20, 10')
        );

        $result = $pagination->getPaginatedResultSet('SELECT * FROM example', [], new PaginationOptions(page: 3, perPage: 10));

        $this->assertSame(3, $result['page']);
        $this->assertSame(10, $result['per_page']);
        $this->assertSame(10, $result['pages']);
    }

    public function testNonIntegerPaginationOptionsDataUsesDefaults(): void
    {
        $pagination = $this->createPagination(
            $this->createDbMock('SELECT * FROM example LIMIT 0, 100')
        );

        $result = $pagination->getPaginatedResultSet('SELECT * FROM example', [], PaginationOptions::fromArray(['page' => 'abc', 'per_page' => '']));

        $this->assertSame(1, $result['page']);
        $this->assertSame(100, $result['per_page']);
        $this->assertSame(1, $result['pages']);
    }

    public function testPaginationOptionsSupportsCustomParameterNames(): void
    {
        $pagination = PaginationOptions::fromArray(['p' => 3, 'limit' => 15], 'p', 'limit');

        $this->assertSame(3, $pagination->page);
        $this->assertSame(15, $pagination->perPage);
        $this->assertSame('p', $pagination->pageParam);
        $this->assertSame('limit', $pagination->perPageParam);
    }

    public function testInvalidDataPageThrowsInformationException(): void
    {
        $db = $this->getMockBuilder(Box_Database::class)
            ->disableOriginalConstructor()
            ->getMock();
        $db->expects($this->never())->method('getAll');

        $this->expectException(InformationException::class);
        $this->expectExceptionMessage('Page number (page) must be a positive integer.');

        $pagination = $this->createPagination($db);
        $pagination->getPaginatedResultSet('SELECT * FROM example', [], PaginationOptions::fromArray(['page' => 0, 'per_page' => 25]));
    }
}
