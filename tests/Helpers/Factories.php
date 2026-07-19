<?php

declare(strict_types=1);

namespace Tests\Helpers;

function order(array $attributes = []): object
{
    $defaults = [
        'id' => random_int(1, 10000),
        'client_id' => random_int(1, 10000),
        'product_id' => random_int(1, 10000),
        'status' => \Box\Mod\Order\Entity\Order::STATUS_ACTIVE,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    return createEntity(\Box\Mod\Order\Entity\Order::class, array_merge($defaults, $attributes));
}

function product(array $attributes = []): object
{
    $defaults = [
        'id' => random_int(1, 10000),
        'title' => 'Test Product',
        'type' => 'custom',
        'status' => 'enabled',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    return createEntity(\Box\Mod\Product\Entity\Product::class, array_merge($defaults, $attributes));
}

function client(array $attributes = []): object
{
    $defaults = [
        'id' => random_int(1, 10000),
        'email' => 'test' . uniqid() . '@example.com',
        'first_name' => 'Test',
        'last_name' => 'User',
        'status' => \Box\Mod\Client\Entity\Client::ACTIVE,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    return createEntity(\Box\Mod\Client\Entity\Client::class, array_merge($defaults, $attributes));
}

function admin(array $attributes = []): object
{
    $defaults = [
        'id' => random_int(1, 10000),
        'email' => 'admin' . uniqid() . '@example.com',
        'name' => 'Admin User',
        'status' => \Box\Mod\Staff\Entity\Admin::STATUS_ACTIVE,
    ];

    return createEntity(\Box\Mod\Staff\Entity\Admin::class, array_merge($defaults, $attributes));
}
