<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific
| PHPUnit test case class. By default, that class is "PHPUnit\Framework\TestCase".
|
| You can define custom test case classes in this file and use them via the
| `uses()` function.
|
*/

// Global test configuration
putenv('APP_ENV=test');
define('PATH_TESTS', __DIR__);

// Load application bootstrap
require_once __DIR__ . '/../src/load.php';
require_once __DIR__ . '/../src/vendor/autoload.php';

// Load test helpers
require_once __DIR__ . '/Helpers/Container.php';
require_once __DIR__ . '/Helpers/Factories.php';
require_once __DIR__ . '/Helpers/Api.php';

// Configure Unit tests base
uses()
    ->beforeEach(function () {
        // Unit test setup
    })
    ->in('Unit');

// Configure E2E tests - requires live instance with API access
// Run with: ./vendor/bin/pest --testsuite=E2E
// Requires APP_URL and TEST_API_KEY environment variables
$appUrl = getenv('APP_URL');
$testApiKey = getenv('TEST_API_KEY');

if ($appUrl && $testApiKey) {
    uses()
        ->beforeEach(function () use ($appUrl, $testApiKey) {
            \Tests\Helpers\ApiClient::setBaseUrl($appUrl);
            \Tests\Helpers\ApiClient::setApiKey($testApiKey);
        })
        ->in('E2E');
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain
| conditions. The "expect()" function gives you access to a set of useful
| expectation methods.
|
*/

expect()->extend('toBeDomain', function () {
    $this->not->toBeEmpty();
    expect($this->value)->toBeString();
    expect(filter_var($this->value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))->not->toBeFalse();
});

expect()->extend('toBeEmail', function () {
    $this->not->toBeEmpty();
    expect($this->value)->toBeString();
    expect(filter_var($this->value, FILTER_VALIDATE_EMAIL))->not->toBeFalse();
});

expect()->extend('toBeUrl', function () {
    $this->not->toBeEmpty();
    expect($this->value)->toBeString();
    expect(filter_var($this->value, FILTER_VALIDATE_URL))->not->toBeFalse();
});

expect()->extend('toBeUuid', function () {
    $this->not->toBeEmpty();
    expect($this->value)->toBeString();
    expect(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $this->value))->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code
| specific to your project that you don't want to repeat in every file.
| Here you can define custom helper functions and expect() extensions.
|
*/

function createTestContainer(): \Pimple\Container
{
    $di = new \Pimple\Container();
    $di['config'] = [
        'salt' => 'test_salt_' . uniqid(),
        'url' => 'http://localhost/',
    ];
    $di['validator'] = fn (): \FOSSBilling\Validate => new \FOSSBilling\Validate();
    $di['tools'] = fn (): \FOSSBilling\Tools => new \FOSSBilling\Tools();

    return $di;
}
