<?php

declare(strict_types=1);

dataset('public_urls', [
    'homepage' => ['', 200],
    'order page' => ['order', 200],
    'news page' => ['news', 200],
    'privacy policy' => ['privacy-policy', 200],
    'sitemap' => ['sitemap.xml', 200],
    'contact us' => ['contact-us', 200],
    'login page' => ['login', 200],
    'signup page' => ['signup', 200],
    'password reset' => ['password-reset', 200],
]);

test('public URL returns expected status code', function (string $path, int $expectedCode) {
    $baseUrl = getenv('APP_URL');

    $ch = curl_init($baseUrl . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    expect($responseCode)->toBe($expectedCode);
})->with('public_urls');

test('API is responding correctly', function () {
    expect(api('guest/system/company'))
        ->toHaveResult()
        ->toBeArray();
});

test('fresh install has no pending patches', function () {
    expect(api('admin/system/is_behind_on_patches'))
        ->toHaveResult()
        ->toBeFalse();
});
