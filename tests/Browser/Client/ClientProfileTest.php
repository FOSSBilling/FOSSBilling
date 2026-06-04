<?php

declare(strict_types=1);

use function Tests\Helpers\apiRequest;
use function Tests\Helpers\browserBaseUrl;
use function Tests\Helpers\createTestClient;

it('updates profile details', function (): void {
    $client = createTestClient();

    $page = visit(browserBaseUrl() . '/login');
    $page->type('input[name="email"]', $client['email']);
    $page->type('input[name="password"]', $client['password']);
    $page->click('button[type="submit"]');
    $page->assertPathIs('/');

    $page->navigate('/client/profile');
    $page->assertSee('Update Details');

    $updated = [
        'first_name' => 'Updated',
        'last_name' => 'Profile',
        'company' => 'Updated Browser Company',
        'phone_cc' => '44',
        'phone' => '7700900123',
        'address_1' => '42 Updated Road',
        'city' => 'Updated City',
        'state' => 'Updated State',
        'postcode' => 'UP123',
    ];

    $page->clear('input[name="first_name"]');
    $page->type('input[name="first_name"]', $updated['first_name']);
    $page->clear('input[name="last_name"]');
    $page->type('input[name="last_name"]', $updated['last_name']);
    $page->clear('input[name="company"]');
    $page->type('input[name="company"]', $updated['company']);
    $page->clear('input[name="phone_cc"]');
    $page->type('input[name="phone_cc"]', $updated['phone_cc']);
    $page->clear('input[name="phone"]');
    $page->type('input[name="phone"]', $updated['phone']);
    $page->clear('input[name="address_1"]');
    $page->type('input[name="address_1"]', $updated['address_1']);
    $page->clear('input[name="city"]');
    $page->type('input[name="city"]', $updated['city']);
    $page->clear('input[name="state"]');
    $page->type('input[name="state"]', $updated['state']);
    $page->clear('input[name="postcode"]');
    $page->type('input[name="postcode"]', $updated['postcode']);

    $page->click('form#profile-update button[type="submit"]');
    $page->wait(2);

    $page->navigate('/client/profile');

    $page->assertValue('input[name="first_name"]', $updated['first_name']);
    $page->assertValue('input[name="last_name"]', $updated['last_name']);
    $page->assertValue('input[name="company"]', $updated['company']);
    $page->assertValue('input[name="phone_cc"]', $updated['phone_cc']);
    $page->assertValue('input[name="phone"]', $updated['phone']);
    $page->assertValue('input[name="address_1"]', $updated['address_1']);
    $page->assertValue('input[name="city"]', $updated['city']);
    $page->assertValue('input[name="state"]', $updated['state']);
    $page->assertValue('input[name="postcode"]', $updated['postcode']);
});

it('changes the client password', function (): void {
    $client = createTestClient();
    $oldPassword = $client['password'];
    $newPassword = 'BrowserClient2!';

    $page = visit(browserBaseUrl() . '/login');
    $page->type('input[name="email"]', $client['email']);
    $page->type('input[name="password"]', $oldPassword);
    $page->click('button[type="submit"]');
    $page->assertPathIs('/');

    $page->navigate('/client/profile');
    $page->click('#pass-tab');

    $page->type('#pass-tab-pane input[name="current_password"]', $oldPassword);
    $page->type('#pass-tab-pane input[name="new_password"]', $newPassword);
    $page->type('#pass-tab-pane input[name="confirm_password"]', $newPassword);
    $page->click('#pass-tab-pane form button[type="submit"]');
    $page->wait(2);

    $loginResult = apiRequest('POST', browserBaseUrl() . '/api/guest/client/login', [
        'email' => $client['email'],
        'password' => $oldPassword,
    ]);
    expect($loginResult['body']['result'])->toBeNull();
    expect($loginResult['body']['error']['message'])->toBe('Please check your login details.');

    $page2 = visit(browserBaseUrl() . '/login');
    $page2->type('input[name="email"]', $client['email']);
    $page2->type('input[name="password"]', $newPassword);
    $page2->click('button[type="submit"]');
    $page2->assertPathIs('/');
});
