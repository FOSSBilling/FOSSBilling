<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;
use Box\Mod\Page\Service;

test('getPairs returns array', function (): void {
    $service = new Service();

    $themeService = Mockery::mock(\Box\Mod\Theme\Service::class);
    $themeService->shouldReceive('getCurrentClientAreaThemeCode')
        ->atLeast()
        ->once()
        ->andReturn('huraga');

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $themeService);

    $service->setDi($di);
    $result = $service->getPairs();
    expect($result)->toBeArray();
});
