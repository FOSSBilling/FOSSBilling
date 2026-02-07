# Test Library

Centralized test infrastructure for FOSSBilling.

## Directory Structure

```
tests/library/
├── UnitTestCase.php          # Base class for unit tests
├── Traits/
│   ├── Assertions.php        # Domain, Array, Numeric, String, DateTime assertions
│   ├── MockFactories.php     # Reusable mock factories
│   └── Fixtures.php         # Test data fixtures
├── E2E/                     # E2E test infrastructure
│   ├── TestCase.php         # Base E2E test case
│   ├── ApiClient.php        # HTTP client for API requests
│   ├── ApiResponse.php      # API response wrapper
│   └── ApiAssertions.php    # API-specific assertions
└── README.md                # This file
```

## Usage

### Unit Tests

Extend `FOSSBilling\Tests\UnitTestCase`:

```php
<?php

declare(strict_types=1);

namespace FOSSBilling\Tests\Unit\Box;

use FOSSBilling\Tests\UnitTestCase;

final class PeriodTest extends UnitTestCase
{
    public function testPeriodCreation(): void
    {
        $period = new \Box_Period('1M');
        $this->assertEquals(1, $period->getQty());
    }
}
```

### E2E Tests

Extend `FOSSBilling\Tests\E2E\TestCase`:

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

### Using Traits

Import traits from `FOSSBilling\Tests\Traits`:

```php
<?php

use FOSSBilling\Tests\Traits\MockFactories;
use FOSSBilling\Tests\Traits\Fixtures;

final class MyTest extends UnitTestCase
{
    use MockFactories;

    public function testWithMock(): void
    {
        $db = $this->createDatabaseMock();
        $client = Fixtures::createClientData();
        // ...
    }
}
```

## Available Assertions

### Domain Assertions (`Traits\DomainAssertions`)
- `assertValidDomain()` - Validate domain format
- `assertValidEmail()` - Validate email format
- `assertValidUrl()` - Validate URL format
- `assertValidIp()` - Validate IP address format

### Array Assertions (`Traits\ArrayAssertions`)
- `assertArrayHasIntKey()` - Key exists and is integer
- `assertArrayHasStringKey()` - Key exists and is string
- `assertArrayHasBoolKey()` - Key exists and is boolean
- `assertArrayIsAssociative()` - Array is associative
- `assertArrayIsSequential()` - Array is sequential

### Numeric Assertions (`Traits\NumericAssertions`)
- `assertPositiveNumber()` - Number > 0
- `assertNonNegativeNumber()` - Number >= 0
- `assertNegativeNumber()` - Number < 0
- `assertValidPrice()` - Price is valid
- `assertValidPercentage()` - Percentage is 0-100

### String Assertions (`Traits\StringAssertions`)
- `assertNotEmptyString()` - String is not empty
- `assertValidSlug()` - Slug format valid
- `assertValidUuid()` - UUID format valid

### DateTime Assertions (`Traits\DateTimeAssertions`)
- `assertValidDateTime()` - DateTime format valid
- `assertValidDate()` - Date format valid
- `assertValidTime()` - Time format valid
- `assertDateTimeInRange()` - DateTime in range

### API Assertions (`E2E\ApiAssertions`)
- `assertApiSuccess()` - Response indicates success
- `assertApiError()` - Response indicates error
- `assertApiResultIsArray()` - Result is array
- `assertApiResultIsInt()` - Result is integer
- `assertApiResultIsString()` - Result is string
- `assertApiResultIsBool()` - Result is boolean

## See Also

- `tests/unit/` - Unit tests
- `tests/e2e/` - End-to-end tests
- `tests/legacy/` - Deprecated legacy tests
