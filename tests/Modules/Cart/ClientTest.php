<?php

declare(strict_types=1);

use function Pest\Faker\fake;

it('persists cart items when guest logs in', function () {
    // Create a test product
    $productId = createTestProduct('Cart Test Product');

    // Add product to guest cart
    expect(api('guest/cart/add_item', ['id' => $productId]))
        ->toBeSuccessfulResponse();

    // Save cart state
    $guestCart = api('guest/cart/get')->getResult();
    expect($guestCart)->toBeArray();

    // Create and login as new client
    $email = fake()->email();
    $password = 'A1a' . fake()->password(8);

    $clientId = createTestClient($email,$password);

    expect(api('guest/client/login', ['email' => $email, 'password' => $password]))
        ->toBeSuccessfulResponse();

    // Verify cart was transferred
    $clientCart = api('guest/cart/get')->getResult();
    expect($clientCart)->toBe($guestCart);

    // Cleanup
    deleteTestClient($clientId);
    resetCookies();
});
