<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Page\Service;

use function Tests\Helpers\container;

test('getPairs returns array', function (): void {
    $service = new Service();

    $themeService = Mockery::mock(Box\Mod\Theme\Service::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $themeService->shouldReceive('getCurrentClientAreaThemeCode');
    $expectation->atLeast()->once();
    $expectation->andReturn('huraga');

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $themeService);

    $service->setDi($di);
    $result = $service->getPairs();
    expect($result)->toBeArray();
});
