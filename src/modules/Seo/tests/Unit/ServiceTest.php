<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

use Box\Mod\Cron\Event\BeforeAdminCronRunEvent;
use Box\Mod\Seo\Service;

use function Tests\Helpers\container;

test('the typed cron listener pings the sitemap with SEO settings', function (): void {
    $settings = ['sitemap_google' => 'on'];
    $extension = Mockery::mock(Box\Mod\Extension\Service::class);
    $extension->shouldReceive('getConfig')->once()->with('mod_seo')->andReturn($settings);

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('pingSitemap')->once()->with($settings)->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(static fn (string $module): object => $extension);
    $service->setDi($di);

    $service->pingSitemapOnCron(new BeforeAdminCronRunEvent());
});
