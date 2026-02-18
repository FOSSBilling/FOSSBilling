<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

// Skip E2E tests if environment is not configured
if (!getenv('APP_URL') || !getenv('TEST_API_KEY')) {
    return;
}

function systemIpLookupWorking(): bool
{
    $services = ['https://api64.ipify.org', 'https://ifconfig.io/ip', 'https://ip.hestiacp.com/'];
    foreach ($services as $service) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_FAILONERROR => true,
            CURLOPT_URL => $service,
        ]);
        $ip = curl_exec($ch);
        curl_close($ch);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }
    }

    return false;
}

test('clear cache', function () {
    $result = Tests\Helpers\ApiClient::request('admin/system/clear_cache');
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeBool();
});

test('error reporting toggle', function () {
    $before = Tests\Helpers\ApiClient::request('admin/system/error_reporting_enabled')->getResult();
    expect($before)->toBeBool();

    $result = Tests\Helpers\ApiClient::request('admin/system/toggle_error_reporting');
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeTrue();

    $after = Tests\Helpers\ApiClient::request('admin/system/error_reporting_enabled')->getResult();
    expect($after)->toBeBool();
    $this->assertNotEquals($after, $before);

    if ($after) {
        $result = Tests\Helpers\ApiClient::request('admin/system/toggle_error_reporting');
        expect($result->wasSuccessful())->toBeTrue($result);
        expect($result->getResult())->toBeTrue();
    }
});

test('get and set network interfaces', function () {
    $result = Tests\Helpers\ApiClient::request('admin/system/get_interface_ips');
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeArray();

    foreach ($result->getResult() as $ip) {
        expect((bool) filter_var($ip, FILTER_VALIDATE_IP))->toBeTrue();
    }

    if (systemIpLookupWorking()) {
        foreach ($result->getResult() as $ip) {
            $testResult = Tests\Helpers\ApiClient::request('admin/system/set_interface_ip', ['interface' => $ip]);
            expect($testResult->wasSuccessful())->toBeTrue($result);

            sleep(2);

            $result = Tests\Helpers\ApiClient::request('admin/system/env', ['ip' => true]);
            expect($result->wasSuccessful())->toBeTrue($result);
            expect((bool) filter_var($result->getResult(), FILTER_VALIDATE_IP))->toBeTrue();
        }
    }

    Tests\Helpers\ApiClient::request('admin/system/set_interface_ip', ['interface' => '0']);
});

test('interface is ignored when not valid', function () {
    $result = Tests\Helpers\ApiClient::request('admin/system/set_interface_ip', ['interface' => '12345']);
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeTrue();

    if (systemIpLookupWorking()) {
        sleep(2);
        $result = Tests\Helpers\ApiClient::request('admin/system/env', ['ip' => true]);
        expect($result->wasSuccessful())->toBeTrue($result);
        expect((bool) filter_var($result->getResult(), FILTER_VALIDATE_IP))->toBeTrue();
    }
});

test('custom interface', function () {
    $result = Tests\Helpers\ApiClient::request('admin/system/set_interface_ip', ['custom_interface' => '12345']);
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeTrue();

    if (systemIpLookupWorking()) {
        sleep(2);
        $result = Tests\Helpers\ApiClient::request('admin/system/env', ['ip' => true]);
        expect($result->wasSuccessful())->toBeTrue($result);
        expect($result->getResult())->toBeEmpty();
    }

    $result = Tests\Helpers\ApiClient::request('admin/system/set_interface_ip', ['custom_interface' => '', 'interface' => '0']);
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeTrue();
});
