<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Unified Test Bootstrap
 *
 * Detects the test type being run and loads the appropriate bootstrap.
 */

putenv('APP_ENV=test');
define('PATH_TESTS', __DIR__);

require_once __DIR__ . '/../src/load.php';
require_once __DIR__ . '/../src/vendor/autoload.php';
$config = include __DIR__ . '/../src/config.php';

define('BB_DB_NAME', $config['db']['name']);
define('BB_DB_USER', $config['db']['user']);
define('BB_DB_PASSWORD', $config['db']['password']);
define('BB_DB_HOST', $config['db']['host']);
define('BB_DB_TYPE', $config['db']['driver']);

// Detect test type from environment or current file path
$testType = getenv('TEST_TYPE') ?: detectTestType();

define('IS_E2E_TEST', $testType === 'e2e');
define('IS_LEGACY_TEST', $testType === 'legacy');
define('IS_UNIT_TEST', $testType === 'unit');

// Load appropriate bootstrap
switch ($testType) {
    case 'e2e':
        requireE2EBootstrap();
        break;
    case 'unit':
        requireUnitBootstrap();
        break;
    case 'legacy':
    default:
        requireLegacyBootstrap();
        break;
}

function detectTestType(): string
{
    // Check if running through PHPUnit
    $argv = $_SERVER['argv'] ?? [];
    
    // Check for --testsuite flag
    foreach ($argv as $i => $arg) {
        if ($arg === '--testsuite' && isset($argv[$i + 1])) {
            return strtolower($argv[$i + 1]);
        }
        // Also check for path patterns in file arguments
        if (str_starts_with($arg, 'tests/')) {
            if (str_contains($arg, '/e2e/')) {
                return 'e2e';
            }
            if (str_contains($arg, '/unit/')) {
                return 'unit';
            }
            if (str_contains($arg, '/legacy/')) {
                return 'legacy';
            }
        }
    }
    
    // Check current file being executed
    $currentFile = $_SERVER['PHP_SELF'] ?? '';
    if (str_contains($currentFile, 'e2e')) {
        return 'e2e';
    }
    if (str_contains($currentFile, 'unit')) {
        return 'unit';
    }
    
    return 'legacy';
}

function requireE2EBootstrap(): void
{
    require_once __DIR__ . '/library/E2E/Traits/ApiResponse.php';
    require_once __DIR__ . '/library/E2E/ApiClient.php';
    require_once __DIR__ . '/library/E2E/Traits/ApiAssertions.php';
    require_once __DIR__ . '/library/E2E/TestCase.php';
}

function requireUnitBootstrap(): void
{
    // Unit tests use PSR-4 autoloading via composer
    // No additional bootstrap needed
}

function requireLegacyBootstrap(): void
{
    set_include_path(implode(PATH_SEPARATOR, [
        get_include_path(),
        PATH_TESTS . '/legacy/library',
        PATH_TESTS . '/legacy/includes',
        PATH_TESTS . '/legacy/includes/Vps',
    ]));

    require_once 'BBTestCase.php';
    require_once 'BBDatabaseTestCase.php';
    require_once 'BBDbApiTestCase.php';
    require_once 'ApiTestCase.php';
    require_once 'BBModTestCase.php';
    require_once PATH_TESTS . '/legacy/includes/Payment/Adapter/Dummy.php';
    require_once 'FakeTemplateWrapper.php';
    require_once 'DummyBean.php';

    $di = include PATH_ROOT . '/di.php';
    $di['translate']();
    $di['validator'] = fn(): \FOSSBilling\Validate => new \FOSSBilling\Validate();
    $di['tools'] = fn(): \FOSSBilling\Tools => new \FOSSBilling\Tools();

    $testsLoader = new \AntCMS\AntLoader([
        'mode' => 'filesystem',
    ]);
    $testsLoader->addNamespace('', DIRECTORY_SEPARATOR . 'library', 'psr0');
    $testsLoader->register();
}
