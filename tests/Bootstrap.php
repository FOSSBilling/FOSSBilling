<?php

use APIHelper\Request;
use APIHelper\Response;
use function Pest\Faker\fake;

// Load Composer autoloader first
require_once __DIR__ . '/../src/vendor/autoload.php';

// Load test classes
require_once __DIR__ . DIRECTORY_SEPARATOR . 'TestCase.php';

// Then load APIHelper
require_once __DIR__ . DIRECTORY_SEPARATOR . 'APIHelper.php';

// Load Pest.php to register custom expectations
// Note: This is loaded here because we have a custom bootstrap
if (class_exists(\Pest\TestSuite::class)) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'Pest.php';
}

/**
 * Make an API request and return the response.
 */
function api(string $endpoint, array $payload = [], ?string $role = null): Response
{
    return Request::makeRequest($endpoint, $payload, $role);
}

/**
 * Create a test client and return the ID.
 */
function createTestClient(?string $email = null, ?string $password = null): int
{
    $email = $email ?? fake()->email();
    $password = $password ?? 'A1a' . fake()->password(8);

    $response = api('guest/client/create', [
        'email' => $email,
        'first_name' => fake()->firstName(),
        'password' => $password,
        'password_confirm' => $password,
    ]);

    if (!$response->wasSuccessful()) {
        throw new RuntimeException($response->generatePHPUnitMessage());
    }

    return intval($response->getResult());
}

/**
 * Delete a test client.
 */
function deleteTestClient(int $id): void
{
    $response = api('admin/client/delete', ['id' => $id]);
    if (!$response->wasSuccessful()) {
        throw new RuntimeException($response->generatePHPUnitMessage());
    }
}

/**
 * Create a test product and return the ID.
 */
function createTestProduct(string $title = 'Test Product'): int
{
    $response = api('admin/product/prepare', [
        'title' => $title,
        'type' => 'custom',
        'product_category_id' => 1,
    ]);

    if (!$response->wasSuccessful()) {
        throw new RuntimeException($response->generatePHPUnitMessage());
    }

    $productId = intval($response->getResult());

    // Configure it as enabled and free
    api('admin/product/update', [
        'id' => $productId,
        'status' => 'enabled',
        'pricing' => ['type' => 'free'],
    ]);

    return $productId;
}

/**
 * Reset cookies between tests.
 */
function resetCookies(): void
{
    Request::resetCookies();
}

/**
 * Check if IP lookup services are working.
 */
function isIpLookupWorking(): bool
{
    $services = ['https://api64.ipify.org', 'https://ifconfig.io/ip', 'https://ip.hestiacp.com/'];
    foreach ($services as $service) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_URL, $service);
        $ip = curl_exec($ch);
        curl_close($ch);

        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }
    }

    return false;
}
