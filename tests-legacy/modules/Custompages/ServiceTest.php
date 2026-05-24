<?php

declare(strict_types=1);

namespace Box\Mod\Custompages;

use FOSSBilling\Pagination;
use FOSSBilling\PaginationOptions;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?Service $service;

    public function setUp(): void
    {
        $this->service = new Service();
    }

    public function testSearchPagesBuildsExpandedSearchQuery(): void
    {
        $pager = $this->createMock(Pagination::class);
        $pager->expects($this->once())
            ->method('getPaginatedResultSet')
            ->with(
                $this->callback(function (string $query): bool {
                    $this->assertStringContainsString('title LIKE :q', $query);
                    $this->assertStringContainsString('slug LIKE :q', $query);
                    $this->assertStringContainsString('description LIKE :q', $query);
                    $this->assertStringContainsString('keywords LIKE :q', $query);
                    $this->assertStringContainsString('content LIKE :q', $query);

                    return true;
                }),
                [':q' => '%landing%'],
                $this->isInstanceOf(PaginationOptions::class)
            )
            ->willReturn(['list' => []]);

        $di = $this->getDi();
        $di['pager'] = $pager;

        $this->service->setDi($di);
        $result = $this->service->searchPages(['search' => 'landing']);

        $this->assertSame(['list' => []], $result);
    }

    public function testSearchPagesBuildsFilterQuery(): void
    {
        $pager = $this->createMock(Pagination::class);
        $pager->expects($this->once())
            ->method('getPaginatedResultSet')
            ->with(
                $this->callback(function (string $query): bool {
                    $this->assertStringContainsString('id = :id', $query);
                    $this->assertStringContainsString('slug LIKE :slug', $query);

                    return true;
                }),
                [
                    ':id' => 12,
                    ':slug' => '%docs%',
                ],
                $this->isInstanceOf(PaginationOptions::class)
            )
            ->willReturn(['list' => []]);

        $di = $this->getDi();
        $di['pager'] = $pager;

        $this->service->setDi($di);
        $result = $this->service->searchPages([
            'id' => '12',
            'slug' => 'docs',
        ]);

        $this->assertSame(['list' => []], $result);
    }
}
