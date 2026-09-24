<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

// Skip E2E tests if environment is not configured
if (!getenv('APP_URL') || !getenv('TEST_API_KEY')) {
    return;
}

use function Tests\Helpers\assertApiResultIsArray;
use function Tests\Helpers\assertApiResultIsInt;
use function Tests\Helpers\assertApiSuccess;

test('group-targeted promos follow any membership of a multi-group client', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    $charityGroup = null;
    $resellerGroup = null;
    $product = null;
    $autoPromo = null;
    $manualPromo = null;
    $manualCode = 'E2EMG' . strtoupper(uniqid());
    $clientA = null;
    $clientB = null;
    $clientC = null;

    try {
        $charityGroup = multiGroupCreateGroup('E2E Charity ' . uniqid());
        $resellerGroup = multiGroupCreateGroup('E2E Reseller ' . uniqid());
        $product = multiGroupCreateProduct(100.0);

        // 10% standing discount for the Charity group, applied automatically.
        $autoPromo = multiGroupCreatePromo('E2EMGA' . strtoupper(uniqid()), 'percentage', 10, [
            'active' => 1,
            'recurring' => 1,
            'auto_apply' => 1,
            'products' => [$product],
            'client_groups' => [$charityGroup],
        ]);
        // Manual code for the Reseller group.
        $manualPromo = multiGroupCreatePromo($manualCode, 'percentage', 10, [
            'active' => 1,
            'recurring' => 1,
            'products' => [$product],
            'client_groups' => [$resellerGroup],
        ]);

        // A is in both groups, B in Charity only, C in neither.
        $clientA = multiGroupCreateClient([$charityGroup, $resellerGroup]);
        $clientB = multiGroupCreateClient([$charityGroup]);
        $clientC = multiGroupCreateClient([]);

        // The automatic Charity promo resolves for both members, not for C.
        expect(multiGroupCartDiscount($clientA, $product))->toEqual(10.0);
        expect(multiGroupCartDiscount($clientB, $product))->toEqual(10.0);
        expect(multiGroupCartDiscount($clientC, $product))->toEqual(0.0);

        // The manual Reseller code applies for A, and is rejected for B and C.
        expect(multiGroupApplyCode($clientA, $product, $manualCode))->toBeTrue();
        expect(multiGroupApplyCode($clientB, $product, $manualCode))->toContain('cannot be applied');
        expect(multiGroupApplyCode($clientC, $product, $manualCode))->toContain('cannot be applied');
    } finally {
        multiGroupCleanupClient($clientA);
        multiGroupCleanupClient($clientB);
        multiGroupCleanupClient($clientC);
        multiGroupDeactivatePromo($autoPromo);
        multiGroupDeactivatePromo($manualPromo);
        multiGroupDeleteProduct($product);
        multiGroupDeleteGroup($charityGroup);
        multiGroupDeleteGroup($resellerGroup);
    }
});

function multiGroupCreateGroup(string $title): int
{
    $result = Tests\Helpers\ApiClient::request('admin/client/group_create', ['title' => $title]);
    assertApiSuccess($result);
    assertApiResultIsInt($result);

    return (int) $result->getResult();
}

function multiGroupDeleteGroup(?int $groupId): void
{
    if ($groupId === null) {
        return;
    }

    $result = Tests\Helpers\ApiClient::request('admin/client/group_delete', ['id' => $groupId]);
    assertApiSuccess($result);
}

function multiGroupCreateProduct(float $price): int
{
    $result = Tests\Helpers\ApiClient::request('admin/product/prepare', [
        'title' => 'E2E Multi-Group Product ' . uniqid(),
        'type' => 'custom',
        'product_category_id' => 1,
    ]);
    assertApiSuccess($result);
    assertApiResultIsInt($result);
    $productId = (int) $result->getResult();

    $update = Tests\Helpers\ApiClient::request('admin/product/update', [
        'id' => $productId,
        'status' => 'enabled',
        'pricing' => ['type' => 'once', 'once' => ['price' => $price, 'setup' => 0]],
    ]);
    assertApiSuccess($update);

    return $productId;
}

function multiGroupDeleteProduct(?int $productId): void
{
    if ($productId === null) {
        return;
    }

    $result = Tests\Helpers\ApiClient::request('admin/product/delete', ['id' => $productId]);
    assertApiSuccess($result);
}

function multiGroupCreatePromo(string $code, string $type, float $value, array $flags): int
{
    $result = Tests\Helpers\ApiClient::request('admin/product/promo_create', [
        'code' => $code,
        'type' => $type,
        'value' => $value,
        ...$flags,
    ]);
    assertApiSuccess($result);
    assertApiResultIsInt($result);

    return (int) $result->getResult();
}

function multiGroupDeactivatePromo(?int $promoId): void
{
    if ($promoId === null) {
        return;
    }

    $result = Tests\Helpers\ApiClient::request('admin/product/promo_update', [
        'id' => $promoId,
        'active' => 0,
        'auto_apply' => 0,
    ]);
    assertApiSuccess($result);
}

function multiGroupCreateClient(array $groupIds): array
{
    $email = 'multigroup_' . uniqid() . '@example.com';
    $password = 'A1a' . bin2hex(random_bytes(6));
    $created = Tests\Helpers\ApiClient::request('admin/client/create', [
        'email' => $email,
        'first_name' => 'Test',
        'password' => $password,
        'send_welcome_email' => 0,
        'group_ids' => $groupIds,
    ]);
    assertApiSuccess($created);
    $clientId = (int) $created->getResult();

    return ['id' => $clientId, 'password' => $password, 'email' => $email];
}

function multiGroupLogin(array $client): void
{
    Tests\Helpers\ApiClient::resetCookies();
    $login = Tests\Helpers\ApiClient::request('guest/client/login', [
        'email' => $client['email'],
        'password' => $client['password'],
    ]);
    assertApiSuccess($login);
}

function multiGroupCartDiscount(array $client, int $product): float
{
    multiGroupLogin($client);
    Tests\Helpers\ApiClient::request('guest/cart/add_item', ['id' => $product, 'multiple' => 1]);

    $cart = Tests\Helpers\ApiClient::request('guest/cart/get');
    assertApiSuccess($cart);
    assertApiResultIsArray($cart);

    return (float) $cart->getResult()['discount'];
}

/**
 * @return bool|string true when the code applies, otherwise the error message
 */
function multiGroupApplyCode(array $client, int $product, string $code): bool|string
{
    multiGroupLogin($client);
    Tests\Helpers\ApiClient::request('guest/cart/add_item', ['id' => $product, 'multiple' => 1]);

    $applied = Tests\Helpers\ApiClient::request('guest/cart/apply_promo', ['promocode' => $code]);

    return $applied->wasSuccessful() ? true : $applied->getErrorMessage();
}

function multiGroupCleanupClient(?array $client): void
{
    if ($client === null) {
        return;
    }

    $deleted = Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $client['id']]);
    assertApiSuccess($deleted);
}
