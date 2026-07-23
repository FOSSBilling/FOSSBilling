<?php

declare(strict_types=1);

use Box\Mod\Activity\Entity\ActivityAdminHistory;
use Box\Mod\Activity\Repository\ActivityAdminHistoryRepository;
use FOSSBilling\InformationException;

test('finds an admin activity history event by id', function (): void {
    $history = new ActivityAdminHistory();
    $repository = Mockery::mock(ActivityAdminHistoryRepository::class)->makePartial();
    $repository->shouldReceive('find')->once()->with(1)->andReturn($history);

    expect($repository->findOneByIdOrFail(1))->toBe($history);
});

test('fails when an admin activity history event does not exist', function (): void {
    $repository = Mockery::mock(ActivityAdminHistoryRepository::class)->makePartial();
    $repository->shouldReceive('find')->once()->with(99)->andReturn(null);

    $repository->findOneByIdOrFail(99);
})->throws(InformationException::class, 'Event not found');
