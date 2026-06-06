<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Twig\Extension\LegacyExtension;

beforeEach(function (): void {
    $this->extension = new LegacyExtension(null);
});

test('modAssetUrl builds URL with ucfirst module name', function (): void {
    expect($this->extension->modAssetUrl('img/logo.png', 'invoice'))
        ->toBe(SYSTEM_URL . 'modules/Invoice/assets/img/logo.png');
});

test('modAssetUrl returns empty string for null asset', function (): void {
    expect($this->extension->modAssetUrl(null, 'invoice'))->toBe('');
});

test('modAssetUrl returns empty string for null module', function (): void {
    expect($this->extension->modAssetUrl('img/logo.png', null))->toBe('');
});

test('modAssetUrl returns empty string when both are null', function (): void {
    expect($this->extension->modAssetUrl(null, null))->toBe('');
});

test('periodTitle returns empty string for null without calling the API', function (): void {
    $di = Mockery::mock(Pimple\Container::class);
    $di->shouldNotReceive('offsetGet');
    $extension = new LegacyExtension($di);

    expect($extension->periodTitle(null))->toBe('');
});
