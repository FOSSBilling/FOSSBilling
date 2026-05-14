<?php

declare(strict_types=1);

namespace ThemeTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase
{
    private const string SEMANTIC_VERSION_PATTERN = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/';

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
        $requiredKeys = ['author', 'name', 'version', 'code', 'paths', 'hasSettings', 'url'];
        foreach ($requiredKeys as $requiredKey) {
            $this->assertArrayHasKey($requiredKey, $data, sprintf('Theme payload should contain the "%s" key.', $requiredKey));
        }

        $this->assertIsString($data['name']);
        $this->assertNotSame('', trim($data['name']), 'Theme name should not be empty.');
        $this->assertIsString($data['version']);
        $this->assertNotSame('', trim($data['version']), 'Theme version should not be empty.');
        $this->assertMatchesRegularExpression(
            self::SEMANTIC_VERSION_PATTERN,
            $data['version'],
            'Theme version should follow semantic versioning (e.g., 1.2.3).'
        );
        $this->assertIsString($data['author']);
        $this->assertNotSame('', trim($data['author']), 'Theme author should not be empty.');
        $this->assertIsString($data['code']);
        $this->assertNotSame('', trim($data['code']), 'Theme code should not be empty.');
        $this->assertIsString($data['url']);
        $this->assertNotSame('', trim($data['url']), 'Theme URL should not be empty.');
        $this->assertIsBool($data['hasSettings']);
        $this->assertIsArray($data['paths']);
        $this->assertNotEmpty($data['paths'], 'Theme paths should not be empty.');
        foreach ($data['paths'] as $path) {
            $this->assertIsString($path);
            $this->assertNotSame('', trim($path), 'Theme paths should contain non-empty strings.');
        }

        if (array_key_exists('author_url', $data)) {
            $this->assertIsString($data['author_url']);
            $this->assertNotSame('', trim($data['author_url']), 'Theme author URL should not be empty when present.');
        }

        if (array_key_exists('description', $data)) {
            $this->assertIsString($data['description']);
        }

        if (array_key_exists('icon', $data)) {
            $this->assertIsString($data['icon']);
        }

        if (array_key_exists('markdown_attributes', $data)) {
            $this->assertIsArray($data['markdown_attributes']);
        }
    }
}
