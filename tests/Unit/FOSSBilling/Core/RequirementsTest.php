<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\Core\Doctrine\DriverManagerFactory;
use FOSSBilling\Core\System\Requirements;

test('required_extensions no longer hardcodes a single PDO driver', function (): void {
    $requirements = new Requirements();

    expect($requirements->php_reqs['required_extensions'])->not->toContain('pdo_mysql');
});

test('checkCompat reports every supported database driver extension individually', function (): void {
    $result = (new Requirements())->checkCompat();

    foreach (DriverManagerFactory::SUPPORTED_DRIVERS as $driver) {
        expect($result['required_extensions'])->toHaveKey($driver)
            ->and($result['required_extensions'][$driver])->toBe(extension_loaded($driver));
    }
});

test('checkCompat does not fail overall on the database driver check when at least one supported driver extension is loaded', function (): void {
    $requirements = new Requirements();
    // Isolate the driver rule from every other requirement checkCompat() folds into can_install
    // (folder/file writability, other required extensions) - both are public, mutable properties,
    // so this is real isolation, not a mock standing in for the real check. can_install can then
    // only be false here if the "at least one supported driver" rule itself is what failed - the
    // PHP-version check stays live, but this project already requires PHP 8.3+ to run at all
    // (composer.json's php-64bit platform requirement), so it can never be what fails this test.
    $requirements->writable = ['folders' => [], 'files' => []];
    $requirements->php_reqs['required_extensions'] = [];

    $result = $requirements->checkCompat();

    $reportedDrivers = array_intersect_key(
        $result['required_extensions'],
        array_flip(DriverManagerFactory::SUPPORTED_DRIVERS),
    );

    // Precondition: this test environment has pdo_mysql, pdo_pgsql, and pdo_sqlite all loaded, so
    // the "at least one" rule can never be the reason can_install is false here.
    expect(in_array(true, $reportedDrivers, true))->toBeTrue()
        ->and($result['can_install'])->toBeTrue();
});
