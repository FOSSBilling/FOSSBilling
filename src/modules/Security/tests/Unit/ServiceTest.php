<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;

function rateLimitCountersFixture(): array
{
    return [
        ['ip' => '10.0.0.2', 'policy' => 'b_policy', 'retry_after' => '2026-01-02T00:00:00+00:00', 'last_seen' => '2026-01-02T00:00:00+00:00'],
        ['ip' => '10.0.0.1', 'policy' => 'a_policy', 'retry_after' => '2026-01-01T00:00:00+00:00', 'last_seen' => '2026-01-03T00:00:00+00:00'],
        ['ip' => '10.0.0.1', 'policy' => 'c_policy', 'retry_after' => null, 'last_seen' => '2026-01-01T00:00:00+00:00'],
    ];
}

test('sort rate limit counters keeps default order without sort input', function (): void {
    $service = new Box\Mod\Security\Service();
    $service->setDi(container());

    $counters = rateLimitCountersFixture();

    expect($service->sortRateLimitCounters($counters, []))->toEqual($counters);
    expect($service->sortRateLimitCounters($counters, ['sort' => 'unknown_key', 'direction' => 'DESC']))->toEqual($counters);
});

test('sort rate limit counters sorts by allowlisted keys', function (): void {
    $service = new Box\Mod\Security\Service();
    $service->setDi(container());

    $counters = rateLimitCountersFixture();

    $byPolicy = $service->sortRateLimitCounters($counters, ['sort' => 'policy', 'direction' => 'DESC']);
    expect(array_column($byPolicy, 'policy'))->toEqual(['c_policy', 'b_policy', 'a_policy']);

    $byIp = $service->sortRateLimitCounters($counters, ['sort' => 'ip', 'direction' => 'ASC']);
    expect(array_column($byIp, 'ip'))->toEqual(['10.0.0.1', '10.0.0.1', '10.0.0.2']);

    $byLastSeen = $service->sortRateLimitCounters($counters, ['sort' => 'last_seen', 'direction' => 'ASC']);
    expect(array_column($byLastSeen, 'last_seen'))->toEqual([
        '2026-01-01T00:00:00+00:00',
        '2026-01-02T00:00:00+00:00',
        '2026-01-03T00:00:00+00:00',
    ]);
});

test('sort rate limit counters falls back to ip policy tiebreak', function (): void {
    $service = new Box\Mod\Security\Service();
    $service->setDi(container());

    $counters = [
        ['ip' => '10.0.0.2', 'policy' => 'a_policy', 'retry_after' => null, 'last_seen' => '2026-01-01T00:00:00+00:00'],
        ['ip' => '10.0.0.1', 'policy' => 'b_policy', 'retry_after' => null, 'last_seen' => '2026-01-01T00:00:00+00:00'],
    ];

    $sorted = $service->sortRateLimitCounters($counters, ['sort' => 'retry_after', 'direction' => 'ASC']);
    expect(array_column($sorted, 'ip'))->toEqual(['10.0.0.1', '10.0.0.2']);
});
