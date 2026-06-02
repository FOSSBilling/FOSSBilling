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

// Disable DEBUG mode to prevent FOSSBilling\Exception from logging stack traces
// Must be defined BEFORE load.php is included
if (!defined('DEBUG')) {
    define('DEBUG', false);
}

// Pre-declare translation functions to prevent Box_Translate from trying to redefine them
// These stubs will be used if the full translation system isn't initialized
if (!function_exists('__trans')) {
    function __trans(string $msgid, ?array $values = null): string
    {
        return $values ? strtr($msgid, $values) : $msgid;
    }
}
if (!function_exists('__pluralTrans')) {
    function __pluralTrans(string $msgid, string $msgidPlural, int $number, ?array $values = null): string
    {
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

// Load test datasets
require_once __DIR__ . '/Datasets/PeriodCodes.php';
require_once __DIR__ . '/Datasets/ValidationData.php';
require_once __DIR__ . '/Datasets/GeographicData.php';

// Define TestLogger class after autoloader is registered
// This must be done here because it extends Box_Log which is loaded via the autoloader
// Using eval() to defer class definition until runtime when Box_Log is available
// @phpstan-ignore-next-line
if (!class_exists(Tests\Helpers\TestLogger::class)) {
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

// Test-only compatibility shim for legacy tests that still construct the old RedBean model name
// while the Email module now uses a Doctrine entity.
if (!class_exists('Model_EmailTemplate')) {
    eval('
        class Model_EmailTemplate extends \Box\Mod\Email\Entity\EmailTemplate
        {
            public function __construct()
            {
                parent::__construct("");
            }

            public function __set(string $name, mixed $value): void
            {
                match ($name) {
                    "id" => $this->setLegacyId($value === null ? null : (int) $value),
                    "action_code" => $this->setActionCode((string) $value),
                    "category" => $this->setCategory($value === null ? null : (string) $value),
                    "enabled" => $this->setEnabled((bool) $value),
                    "subject" => $this->setSubject($value === null ? null : (string) $value),
                    "content" => $this->setContent($value === null ? null : (string) $value),
                    "description" => $this->setDescription($value === null ? null : (string) $value),
                    "vars" => $this->setVars($value === null ? null : (string) $value),
                    "is_custom" => $this->setIsCustom((bool) $value),
                    "is_overridden" => $this->setIsOverridden((bool) $value),
                    default => null,
                };
            }

            public function __get(string $name): mixed
            {
                return match ($name) {
                    "id" => $this->getId(),
                    "action_code" => $this->getActionCode(),
                    "category" => $this->getCategory(),
                    "enabled" => $this->isEnabled(),
                    "subject" => $this->getSubject(),
                    "content" => $this->getContent(),
                    "description" => $this->getDescription(),
                    "vars" => $this->getVars(),
                    "is_custom" => $this->isCustom(),
                    "is_overridden" => $this->isOverridden(),
                    default => null,
                };
            }

            public function loadBean(mixed $bean): void
            {
            }

            private function setLegacyId(?int $id): void
            {
                $property = new \ReflectionProperty(\Box\Mod\Email\Entity\EmailTemplate::class, "id");
                $property->setAccessible(true);
                $property->setValue($this, $id);
            }
        }
    ');
}

// Redirect error_log to /dev/null during tests to prevent "PHPUnit controlled exception" clutter
ini_set('error_log', '/dev/null');

// Configure Unit tests base with Mockery integration
uses(MockeryPHPUnitIntegration::class)
    ->beforeEach(function (): void {
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
        ->beforeEach(function () use ($appUrl, $testApiKey): void {
            Tests\Helpers\ApiClient::setBaseUrl($appUrl);
            Tests\Helpers\ApiClient::setApiKey($testApiKey);
        })
        ->in('E2E');
}

uses()
    ->beforeEach(function (): void {
        if (!getenv('APP_URL') || !getenv('TEST_API_KEY')) {
            $this->markTestSkipped('API tests require APP_URL and TEST_API_KEY.');
        }

        if (class_exists(APIHelper\Request::class)) {
            APIHelper\Request::resetCookies();
        }
    })
    ->in('Modules');

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

expect()->extend('toBeDomain', function (): void {
    $this->not->toBeEmpty();
    expect($this->value)->toBeString();
    expect(filter_var($this->value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))->not->toBeFalse();
});

expect()->extend('toBeEmail', function (): void {
    $this->not->toBeEmpty();
    expect($this->value)->toBeString();
    expect(filter_var($this->value, FILTER_VALIDATE_EMAIL))->not->toBeFalse();
});

expect()->extend('toBeUrl', function (): void {
    $this->not->toBeEmpty();
    expect($this->value)->toBeString();
    expect(filter_var($this->value, FILTER_VALIDATE_URL))->not->toBeFalse();
});

expect()->extend('toBeUuid', function (): void {
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
