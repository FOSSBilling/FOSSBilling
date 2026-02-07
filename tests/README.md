# FOSSBilling Test Suite

This directory contains the complete test suite for FOSSBilling.

## Running Tests

### All Tests

```bash
./src/vendor/bin/phpunit
```

### Specific Test Types

```bash
# Unit tests
./src/vendor/bin/phpunit --testsuite Unit

# E2E tests
./src/vendor/bin/phpunit --testsuite E2E

# Legacy tests
./src/vendor/bin/phpunit --testsuite Legacy
```

### Single Test File

```bash
./src/vendor/bin/phpunit tests/unit/FOSSBilling/ToolsTest.php
```

## Directory Structure

```
tests/
├── bootstrap.php              # Unified bootstrap (auto-detects test type)
├── README.md                  # This file
│
├── library/                    # Centralized test infrastructure
│   ├── UnitTestCase.php       # Base unit test case
│   ├── Traits/
│   │   ├── Assertions.php     # Domain, Array, Numeric, String, DateTime
│   │   ├── MockFactories.php  # Reusable mocks
│   │   └── Fixtures.php      # Test fixtures
│   ├── E2E/                   # E2E-specific infrastructure
│   │   ├── TestCase.php
│   │   ├── ApiClient.php
│   │   ├── ApiResponse.php
│   │   └── ApiAssertions.php
│   └── README.md
│
├── unit/                       # Unit tests (PSR-4 structure)
│   ├── Box/
│   └── FOSSBilling/
│   └── README.md
│
├── e2e/                        # End-to-end API tests
│   ├── Modules/
│   └── README.md
│
└── legacy/                     # Deprecated legacy tests
    ├── modules/
    ├── library/
    └── README.md
```

## Test Types

### Unit Tests (`tests/unit/`)
Tests for individual classes and methods. Fast, isolated, no external dependencies.
Uses PSR-4 autoloading via `FOSSBilling\Tests\` namespace.

### E2E Tests (`tests/e2e/`)
Full API endpoint tests. Require running FOSSBilling instance.

### Legacy Tests (`tests/legacy/`)
Old test patterns. Being phased out - write new tests in `tests/unit/`.

## Writing New Tests

### Unit Test Example

```php
<?php

declare(strict_types=1);

namespace FOSSBilling\Tests\FOSSBilling;

use FOSSBilling\Tests\UnitTestCase;

final class ToolsTest extends UnitTestCase
{
    public function testSlugGeneration(): void
    {
        $tools = new \FOSSBilling\Tools();
        $slug = $tools->slugify('Hello World!');
        $this->assertEquals('hello-world', $slug);
    }
}
```

### E2E Test Example

```php
<?php

declare(strict_types=1);

namespace FOSSBilling\Tests\E2E\Modules\Cart;

use FOSSBilling\Tests\E2E\TestCase;
use FOSSBilling\Tests\E2E\ApiClient;

final class ApiGuestTest extends TestCase
{
    public function testCartTransferOnLogin(): void
    {
        $response = ApiClient::request('guest/cart/get');
        $this->assertApiSuccess($response);
    }
}
```

## Configuration

- `phpunit.xml.dist` - Main PHPUnit configuration
- `tests/bootstrap.php` - Unified bootstrap (auto-detects test type)
- `tests/library/E2E/*.php` - E2E test infrastructure
- `tests/library/Traits/*.php` - Shared test traits

## Environment Variables

- `APP_ENV` - Set to 'test' by bootstrap
- `APP_URL` - Base URL for E2E tests
- `TEST_API_KEY` - API key for E2E test authentication
