<?php

declare(strict_types=1);

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

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

// Pre-declare translation functions to prevent Box_Translate from trying to redefine them
// These stubs will be used if the full translation system isn't initialized
if (!function_exists('__trans')) {
    function __trans(string $msgid, ?array $values = null): string {
        return $values ? strtr($msgid, $values) : $msgid;
    }
}
if (!function_exists('__pluralTrans')) {
    function __pluralTrans(string $msgid, string $msgidPlural, int $number, ?array $values = null): string {
        return $values ? strtr($msgid, $values) : $msgid;
    }
}

// Load application bootstrap
require_once __DIR__ . '/../src/load.php';
require_once __DIR__ . '/../src/vendor/autoload.php';

// Load test helpers
require_once __DIR__ . '/Helpers/Container.php';
require_once __DIR__ . '/Helpers/Factories.php';
require_once __DIR__ . '/Helpers/Api.php';
require_once __DIR__ . '/Helpers/DummyBean.php';

// Define TestLogger class after autoloader is registered
// This must be done here because it extends Box_Log which is loaded via the autoloader
// Using eval() to defer class definition until runtime when Box_Log is available
// @phpstan-ignore-next-line
if (!class_exists(\Tests\Helpers\TestLogger::class)) {
    // @phpstan-ignore-next-line
    eval('
        namespace Tests\Helpers;

        class TestLogger extends \Box_Log
        {
            public array $calls = [];

            public function __construct()
            {
                $this->calls = [];
            }

            public function __call($method, $params): void
            {
                $this->calls[] = ["method" => $method, "params" => $params];
            }
        }
    ');
}

// Redirect error_log to /dev/null during tests to prevent "PHPUnit controlled exception" clutter
ini_set('error_log', '/dev/null');

// Configure Unit tests base with Mockery integration
uses(MockeryPHPUnitIntegration::class)
    ->beforeEach(function () {
        // Unit test setup
    })
    ->in('Unit');

// Configure E2E tests base - skip if environment not configured
$appUrl = getenv('APP_URL');
$testApiKey = getenv('TEST_API_KEY');

if ($appUrl && $testApiKey) {
    uses()
        ->beforeEach(function () use ($appUrl, $testApiKey) {
            // E2E test setup - configure API client
            \Tests\Helpers\ApiClient::setBaseUrl($appUrl);
            \Tests\Helpers\ApiClient::setApiKey($testApiKey);
        })
        ->in('E2E', '../src/modules/*/tests/E2E');
} else {
    // E2E tests require environment configuration
    uses()
        ->beforeEach(function () {
            \PHPUnit\Framework\Assert::markTestSkipped('E2E tests require APP_URL and TEST_API_KEY environment variables');
        })
        ->in('E2E', '../src/modules/*/tests/E2E');
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
