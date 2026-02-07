# E2E Tests

End-to-end tests for FOSSBilling API endpoints.

## Running E2E Tests

```bash
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
- `ApiResponse.php` - API response wrapper
- `ApiAssertions.php` - Custom assertions

## Writing New Tests

Extend the base `TestCase` class and use `ApiClient`:

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
