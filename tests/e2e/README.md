# E2E Tests

End-to-end tests for FOSSBilling API endpoints.

## Running E2E Tests

E2E tests require a running FOSSBilling instance and will be automatically
skipped if required credentials are not configured.

```bash
./src/vendor/bin/phpunit --testsuite E2E
```

### Environment Variables Required

E2E tests require two environment variables. If either is missing, all E2E tests
are automatically skipped with a clear message:

| Variable | Description | Required For |
|----------|-------------|--------------|
| `APP_URL` | Base URL of your FOSSBilling installation (e.g., `http://localhost`) | All E2E tests |
| `TEST_API_KEY` | Administrator API key for authentication | All E2E tests |

**Tests are skipped (not failed) when credentials are missing**, allowing the
full test suite to run in environments without a running instance.

To run E2E tests locally or in CI:

```bash
export APP_URL="http://localhost"
export TEST_API_KEY="your-admin-api-key"
./src/vendor/bin/phpunit --testsuite E2E
```

## Directory Structure

```
tests/e2e/
├── README.md              # This file
└── Modules/               # API endpoint tests
    ├── Cart/
    │   └── ApiGuestTest.php
    ├── Client/
    │   └── ApiGuestTest.php
    └── ...
```

## Test Infrastructure

Located in `tests/library/E2E/`:

- `TestCase.php` - Base test case class
- `ApiClient.php` - HTTP client for API requests
- `Traits/ApiResponse.php` - API response wrapper
- `Traits/ApiAssertions.php` - Custom assertions

## Writing New Tests

Extend the base `TestCase` class and use `ApiClient`:

```php
<?php

declare(strict_types=1);

namespace FOSSBilling\Tests\E2E\Modules\Cart;

use FOSSBilling\Tests\Library\E2E\TestCase;
use FOSSBilling\Tests\Library\E2E\ApiClient;

final class ApiGuestTest extends TestCase
{
    public function testCartTransferOnLogin(): void
    {
        $response = ApiClient::request('guest/cart/get');
        $this->assertApiSuccess($response);
    }
}
```

## API Assertions

- `assertApiSuccess($response)` - Verifies success
- `assertApiError($response)` - Verifies error
- `assertApiResultIsArray($response)` - Result is array
- `assertApiResultIsInt($response)` - Result is integer
- `assertApiResultIsString($response)` - Result is string
- `assertApiResultIsBool($response)` - Result is boolean

## Environment Variables

- `APP_URL` - Base URL of FOSSBilling installation
- `TEST_API_KEY` - API key for authentication

## See Also

- `tests/README.md` - Complete test suite documentation
- `tests/library/` - Test infrastructure
- `tests/unit/` - Unit tests
- `tests/legacy/` - Deprecated legacy tests
