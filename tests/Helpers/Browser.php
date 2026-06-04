<?php

declare(strict_types=1);

namespace Tests\Helpers;

function browserBaseUrl(): string
{
    return rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
}

function adminEmail(): string
{
    return getenv('ADMIN_EMAIL') ?: 'email@example.com';
}

function adminPassword(): string
{
    return getenv('ADMIN_PASSWORD') ?: '4WGemqiihh8iM3';
}

function createTestClient(array $overrides = []): array
{
    $suffix = uniqid('', true);
    $client = [
        'first_name' => $overrides['first_name'] ?? 'Browser',
        'last_name' => $overrides['last_name'] ?? 'Test',
        'email' => $overrides['email'] ?? "browser-test-{$suffix}@example.com",
        'password' => $overrides['password'] ?? 'BrowserClient1!',
    ];

    $ch = curl_init();
    $baseUrl = browserBaseUrl();

    curl_setopt_array($ch, [
        CURLOPT_URL => "{$baseUrl}/api/guest/client/create",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            ...$client,
            'password_confirm' => $client['password'],
        ]),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode !== 200 || $response === false) {
        throw new \RuntimeException("Failed to create test client (HTTP {$httpCode})");
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || ($data['error'] ?? null) !== null) {
        $msg = $data['error']['message'] ?? json_last_error_msg();

        throw new \RuntimeException("Failed to create test client: {$msg}");
    }

    return [
        ...$client,
        'id' => $data['result'],
    ];
}

function apiRequest(string $method, string $url, array $body = [], ?string $csrfToken = null): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url . ($csrfToken && $method === 'GET' ? '?CSRFToken=' . urlencode($csrfToken) : ''),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_FAILONERROR => false,
    ]);

    if ($method === 'POST' && !empty($body)) {
        $fields = $body;
        if ($csrfToken) {
            $fields['CSRFToken'] = $csrfToken;
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $decoded = json_decode($response ?: '', true) ?? [];

    return [
        'status' => $httpCode,
        'body' => $decoded,
    ];
}
