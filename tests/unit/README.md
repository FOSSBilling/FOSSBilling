# Unit Tests

Unit tests for FOSSBilling core functionality.

## Running Unit Tests

```bash
./src/vendor/bin/phpunit tests/unit/ --no-configuration
```

## Directory Structure

```
tests/unit/
├── Box/
│   └── PeriodTest.php       # Box_Period tests
├── FOSSBilling/
│   ├── ToolsTest.php       # FOSSBilling\Tools tests
│   └── ValidateTest.php    # FOSSBilling\Validate tests
└── README.md               # This file
```

## Test Conventions

- Tests follow PSR-4 autoloading structure
- Each class has its own `*Test.php` file
- Tests use `declare(strict_types=1);`
- Tests extend `FOSSBilling\Tests\UnitTestCase`
- Use `setUp()` and `tearDown()` for fixture management

## Example

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

## See Also

- `tests/library/` - Test infrastructure
- `tests/e2e/` - End-to-end tests
- `tests/legacy/` - Deprecated legacy tests
