# Legacy Tests Directory - DEPRECATED

**This directory contains deprecated legacy tests.**

## Deprecation Notice

The tests in this directory are legacy tests that use outdated testing patterns:

- Complex mock setups without clear behavior testing
- Duplicate test code across methods
- Tight coupling to implementation details
- Missing or weak assertions

## Migration Status

These tests are being gradually migrated to:
- `tests/unit/` - Modern unit tests
- `tests/integration/` - Integration tests

## For Contributors

When working on tests:

1. **New tests** should be written in `tests/unit/` or `tests/integration/`
2. **Modified legacy tests** should be migrated to the new structure
3. **Legacy test patterns** should not be replicated in new tests

## See Also

- `tests/library/TestCase.php` - Base test case with utilities
- `tests/library/MockFactories.php` - Reusable mock factories
- `tests/library/Assertions.php` - Custom assertions
- `tests/library/Fixtures.php` - Test data fixtures
