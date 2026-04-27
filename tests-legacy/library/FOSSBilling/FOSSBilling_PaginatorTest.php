<?php

declare(strict_types=1);

use FOSSBilling\Paginator;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class FOSSBilling_PaginatorTest extends PHPUnit\Framework\TestCase
{
    public function testStartingAndEndingPointsDoNotTriggerReferenceNotices(): void
    {
        $paginator = new Paginator(itemsCount: 100, currentPage: 2, limit: 10);

        $this->assertSame(1, $paginator->getStartingPoint());
        $this->assertSame(7, $paginator->getEndingPoint());
    }

    public function testToArrayIncludesStartAndEnd(): void
    {
        $paginator = new Paginator(itemsCount: 100, currentPage: 4, limit: 10);

        $this->assertSame([
            'currentpage' => 4,
            'numpages' => 10,
            'midrange' => 7,
            'range' => [1, 2, 3, 4, 5, 6, 7],
            'start' => 1,
            'end' => 7,
        ], $paginator->toArray());
    }
}
