<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace Tests\Helpers;

/**
 * Create a mock model with the given properties.
 * This replaces the old DummyBean pattern.
 */
function model(string $class, array $attributes = []): object
{
    $instance = new $class();

    // Handle RedBeanPHP models
    if (method_exists($instance, 'loadBean')) {
        $instance->loadBean(new DummyBean());
    }

    foreach ($attributes as $key => $value) {
        $instance->$key = $value;
    }

    return $instance;
}

/**
 * Create a client order model.
 */
function order(array $attributes = []): object
{
    $defaults = [
        'id' => random_int(1, 10000),
        'client_id' => random_int(1, 10000),
        'product_id' => random_int(1, 10000),
        'status' => \Model_ClientOrder::STATUS_ACTIVE,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    return model(\Model_ClientOrder::class, array_merge($defaults, $attributes));
}

/**
 * Create a product model.
 */
function product(array $attributes = []): object
{
    $defaults = [
        'id' => random_int(1, 10000),
        'title' => 'Test Product',
        'type' => \Model_ProductTable::CUSTOM,
        'status' => \Model_Product::STATUS_ENABLED,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    return model(\Model_Product::class, array_merge($defaults, $attributes));
}

/**
 * Create a client model.
 */
function client(array $attributes = []): object
{
    $defaults = [
        'id' => random_int(1, 10000),
        'email' => 'test' . uniqid() . '@example.com',
        'first_name' => 'Test',
        'last_name' => 'User',
        'status' => \Model_Client::ACTIVE,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    return model(\Model_Client::class, array_merge($defaults, $attributes));
}

/**
 * Create an admin model.
 */
function admin(array $attributes = []): object
{
    $defaults = [
        'id' => random_int(1, 10000),
        'email' => 'admin' . uniqid() . '@example.com',
        'name' => 'Admin User',
        'status' => \Model_Admin::STATUS_ACTIVE,
    ];

    return model(\Model_Admin::class, array_merge($defaults, $attributes));
}
