<?php

declare(strict_types=1);

use Tests\Helpers\ApiClient;

test('guest cron behavior', function (): void {
    ApiClient::request('admin/cron/save_settings', ['ext' => 'mod_cron', 'guest_cron' => false]);
    $result = ApiClient::request('guest/cron/run');
    expect($result->wasSuccessful())->toBeFalse();

    ApiClient::request('admin/cron/save_settings', ['ext' => 'mod_cron', 'guest_cron' => true]);
    $config = ApiClient::request('admin/extension/config_get', ['ext' => 'mod_cron']);
    expect($config->wasSuccessful())->toBeTrue();

    $hash = $config->getResult()['cron_hash'] ?? '';
    expect($hash)->not->toBeEmpty();

    ApiClient::request('admin/system/update_params', ['last_cron_exec' => date('Y-m-d H:i:s', time() - 6400)]);
    $result = ApiClient::request('guest/cron/run', ['hash' => $hash]);
    expect($result->wasSuccessful())->toBeTrue();

    $result = ApiClient::request('guest/cron/run', ['hash' => $hash]);
    expect($result->wasSuccessful())->toBeTrue()
        ->and($result->getResult())->toBeFalse();

    $result = ApiClient::request('guest/cron/run', ['hash' => 'invalid']);
    expect($result->wasSuccessful())->toBeFalse();

    $result = ApiClient::request('guest/cron/run');
    expect($result->wasSuccessful())->toBeFalse();
});
