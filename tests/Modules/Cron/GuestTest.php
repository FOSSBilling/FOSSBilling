<?php

declare(strict_types=1);

use APIHelper\Request;

test('guest cron behavior', function (): void {
    Request::makeRequest('admin/cron/save_settings', ['ext' => 'mod_cron', 'guest_cron' => false]);
    $result = Request::makeRequest('guest/cron/run');
    expect($result->wasSuccessful())->toBeFalse();

    Request::makeRequest('admin/cron/save_settings', ['ext' => 'mod_cron', 'guest_cron' => true]);
    $config = Request::makeRequest('admin/extension/config_get', ['ext' => 'mod_cron']);
    expect($config->wasSuccessful())->toBeTrue();

    $hash = $config->getResult()['cron_hash'] ?? '';
    expect($hash)->not->toBeEmpty();

    Request::makeRequest('admin/system/update_params', ['last_cron_exec' => date('Y-m-d H:i:s', time() - 6400)]);
    $result = Request::makeRequest('guest/cron/run', ['hash' => $hash]);
    expect($result->wasSuccessful())->toBeTrue();

    $result = Request::makeRequest('guest/cron/run', ['hash' => $hash]);
    expect($result->wasSuccessful())->toBeTrue()
        ->and($result->getResult())->toBeFalse();

    $result = Request::makeRequest('guest/cron/run', ['hash' => 'invalid']);
    expect($result->wasSuccessful())->toBeFalse();

    $result = Request::makeRequest('guest/cron/run');
    expect($result->wasSuccessful())->toBeFalse();
});
