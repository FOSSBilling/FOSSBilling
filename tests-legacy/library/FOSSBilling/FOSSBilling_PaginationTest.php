<?php

declare(strict_types=1);

use FOSSBilling\InformationException;
use FOSSBilling\Pagination;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

#[Group('Core')]
final class FOSSBilling_PaginationTest extends BBTestCase
{
    private function createPagination(Request $request, Box_Database $db): Pagination
    {
        $di = $this->getDi();
        $di['request'] = $request;
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

    public function testGetPaginatedResultSetUsesDataPagination(): void
    {
        $pagination = $this->createPagination(
            new Request(['page' => 9, 'per_page' => 90]),
            $this->createDbMock('SELECT * FROM example LIMIT 25, 25')
        );

        $result = $pagination->getPaginatedResultSet('SELECT * FROM example', data: ['page' => 2, 'per_page' => 25]);

        $this->assertSame(2, $result['page']);
        $this->assertSame(25, $result['per_page']);
        $this->assertSame(4, $result['pages']);
    }

    public function testExplicitArgumentsWinOverDataPagination(): void
    {
        $pagination = $this->createPagination(
            new Request(['page' => 9, 'per_page' => 90]),
            $this->createDbMock('SELECT * FROM example LIMIT 20, 10')
        );

        $result = $pagination->getPaginatedResultSet('SELECT * FROM example', perPage: 10, page: 3, data: ['page' => 2, 'per_page' => 25]);

        $this->assertSame(3, $result['page']);
        $this->assertSame(10, $result['per_page']);
        $this->assertSame(10, $result['pages']);
    }

    public function testRequestQueryFallbackStillWorks(): void
    {
        $pagination = $this->createPagination(
            new Request(['page' => 4, 'per_page' => 20]),
            $this->createDbMock('SELECT * FROM example LIMIT 60, 20')
        );

        $result = $pagination->getPaginatedResultSet('SELECT * FROM example');

        $this->assertSame(4, $result['page']);
        $this->assertSame(20, $result['per_page']);
        $this->assertSame(5, $result['pages']);
    }

    public function testInvalidDataPageThrowsInformationException(): void
    {
        $db = $this->getMockBuilder(Box_Database::class)
            ->disableOriginalConstructor()
            ->getMock();
        $db->expects($this->never())->method('getAll');

        $pagination = $this->createPagination(new Request(), $db);

        $this->expectException(InformationException::class);
        $this->expectExceptionMessage('Page number (page) must be a positive integer.');

        $pagination->getPaginatedResultSet('SELECT * FROM example', data: ['page' => 0, 'per_page' => 25]);
    }
}
