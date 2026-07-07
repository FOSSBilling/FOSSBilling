<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\Entity\SupportTicket;
use Box\Mod\Support\Repository\SupportTicketRepository;

test('findByClientId delegates to findBy', function (): void {
    $repo = Mockery::mock(SupportTicketRepository::class)->makePartial();
    $repo->shouldReceive('findBy')
        ->once()
        ->with(['clientId' => 5])
        ->andReturn([]);

    expect($repo->findByClientId(5))->toBe([]);
});

test('findByIds delegates to findBy', function (): void {
    $repo = Mockery::mock(SupportTicketRepository::class)->makePartial();
    $repo->shouldReceive('findBy')
        ->once()
        ->with(['id' => [2, 3]])
        ->andReturn([]);

    expect($repo->findByIds([2, 3]))->toBe([]);
});

test('findByIds returns empty array without calling findBy for empty ids', function (): void {
    $repo = Mockery::mock(SupportTicketRepository::class)->makePartial();
    $repo->shouldReceive('findBy')->never();

    expect($repo->findByIds([]))->toBe([]);
});

test('hasPendingTaskForClient checks pending task criteria', function (): void {
    $repo = Mockery::mock(SupportTicketRepository::class)->makePartial();
    $repo->shouldReceive('findOneBy')
        ->once()
        ->with([
            'clientId' => 1,
            'relId' => 7,
            'relType' => SupportTicket::REL_TYPE_ORDER,
            'relTask' => SupportTicket::REL_TASK_UPGRADE,
            'relStatus' => SupportTicket::REL_STATUS_PENDING,
        ])
        ->andReturn(new SupportTicket());

    expect($repo->hasPendingTaskForClient(1, 7, SupportTicket::REL_TYPE_ORDER, SupportTicket::REL_TASK_UPGRADE))->toBeTrue();
});
