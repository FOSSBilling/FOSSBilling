<?php

declare(strict_types=1);

namespace ThemeTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase
{
    private const string SEMVER_PATTERN = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/';

    public function testGetCurrentClientTheme(): void
    {
        $result = Request::makeRequest('admin/theme/get_current');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $data = $result->getResult();
        $this->assertIsArray($data);

        $this->assertValidThemePayload($data);
    }

    public function testGetCurrentAdminTheme(): void
    {
        $result = Request::makeRequest('admin/theme/get_current', ['client' => false]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $data = $result->getResult();
        $this->assertIsArray($data);

        $this->assertValidThemePayload($data);
    }

    public function testGetCurrentThemeWithInvalidClientParameterReturnsError(): void
    {
        $result = Request::makeRequest('admin/theme/get_current', ['client' => 'invalid']);

        $this->assertFalse($result->wasSuccessful(), 'Request should fail for an invalid "client" parameter type.');
        $this->assertGreaterThanOrEqual(400, $result->getStatusCode(), 'Invalid request should return an HTTP error status.');

        $errorMessage = $result->getErrorMessage();
        $this->assertIsString($errorMessage, 'Error message should be a string.');
        $this->assertNotSame('', trim($errorMessage), 'Error message should not be empty.');
    }

    public function testAdminThemeRequestWithClientFalseFailureReturnsError(): void
    {
        $result = Request::makeRequest('admin/theme/non_existing_action', ['client' => false]);
        $this->assertSame(404, $result->getStatusCode(), 'Invalid admin theme action with client=false should return HTTP 404.');
        $this->assertFalse($result->wasSuccessful(), 'Invalid admin theme action with client=false should not be successful.');
        // API not-found actions/endpoints are mapped to error code 740 (see src/library/Api/Handler.php).
        $expectedNotFoundErrorCode = 740;
        $this->assertSame($expectedNotFoundErrorCode, $result->getErrorCode(), 'Invalid admin theme action with client=false should return the API not-found error code.');

        $errorMessage = $result->getErrorMessage();
        $this->assertIsString($errorMessage, 'Error message should be a string.');
        $this->assertNotSame('', trim($errorMessage), 'Error message should not be empty.');
    }

    public function testInvalidThemeActionReturnsError(): void
    {
        $result = Request::makeRequest('admin/theme/non_existing_action');
        $this->assertSame(404, $result->getStatusCode(), 'Invalid theme action should return HTTP 404.');
        $this->assertFalse($result->wasSuccessful(), 'Invalid theme action should not be successful.');
        // API not-found actions/endpoints are mapped to error code 740 (see src/library/Api/Handler.php).
        $expectedNotFoundErrorCode = 740;
        $this->assertSame($expectedNotFoundErrorCode, $result->getErrorCode(), 'Invalid theme action should return the API not-found error code.');

        $errorMessage = $result->getErrorMessage();
        $this->assertIsString($errorMessage, 'Error message should be a string.');
        $this->assertNotSame('', trim($errorMessage), 'Error message should not be empty.');
    }

    private function assertValidThemePayload(array $data): void
    {
        $expectedKeys = ['author', 'name', 'version'];
        $actualKeys = array_keys($data);
        sort($expectedKeys);
        sort($actualKeys);
        $this->assertSame($expectedKeys, $actualKeys, 'Theme payload should contain only expected keys.');

        $this->assertIsString($data['name']);
        $this->assertNotSame('', trim($data['name']), 'Theme name should not be empty.');
        $this->assertIsString($data['version']);
        $this->assertNotSame('', trim($data['version']), 'Theme version should not be empty.');
        $this->assertMatchesRegularExpression(
            self::SEMVER_PATTERN,
            $data['version'],
            'Theme version should follow semantic versioning (e.g., 1.2.3).'
        );
        $this->assertIsString($data['author']);
        $this->assertNotSame('', trim($data['author']), 'Theme author should not be empty.');
    }
}
