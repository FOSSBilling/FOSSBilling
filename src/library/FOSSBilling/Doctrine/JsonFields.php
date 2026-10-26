<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Doctrine;

/** The migration inventory; encrypted email variables intentionally remain encrypted TEXT. */
final class JsonFields
{
    /** @var array<string, array{entity: class-string, columns: array<string, string>}> */
    public const array FIELDS = [
        'mod_massmailer' => ['entity' => \Box\Mod\Massmailer\Entity\MassmailerMessage::class, 'columns' => ['filter' => 'filter']],
        'form' => ['entity' => \Box\Mod\Formbuilder\Entity\Form::class, 'columns' => ['style' => 'style']],
        'product' => ['entity' => \Box\Mod\Product\Entity\Product::class, 'columns' => ['config' => 'config', 'addons' => 'addons', 'upgrades' => 'upgrades']],
        'promo' => ['entity' => \Box\Mod\Product\Entity\Promo::class, 'columns' => ['products' => 'products', 'periods' => 'periods', 'client_groups' => 'clientGroups', 'requires_products' => 'requiresProducts']],
        'cart_product' => ['entity' => \Box\Mod\Cart\Entity\CartProduct::class, 'columns' => ['config' => 'config']],
        'client_order' => ['entity' => \Box\Mod\Order\Entity\Order::class, 'columns' => ['config' => 'config']],
        'service_custom' => ['entity' => \Box\Mod\Servicecustom\Entity\ServiceCustom::class, 'columns' => ['config' => 'config']],
        'service_apikey' => ['entity' => \Box\Mod\Serviceapikey\Entity\ServiceApiKey::class, 'columns' => ['config' => 'config']],
        'service_hosting_hp' => ['entity' => \Box\Mod\Servicehosting\Entity\ServiceHostingHp::class, 'columns' => ['config' => 'config']],
        'service_hosting_server' => ['entity' => \Box\Mod\Servicehosting\Entity\ServiceHostingServer::class, 'columns' => ['config' => 'config']],
        'service_license' => ['entity' => \Box\Mod\Servicelicense\Entity\ServiceLicense::class, 'columns' => ['ips' => 'ips', 'hosts' => 'hosts', 'paths' => 'paths', 'versions' => 'versions']],
        'pay_gateway' => ['entity' => \Box\Mod\Invoice\Entity\PayGateway::class, 'columns' => ['config' => 'config', 'accepted_currencies' => 'acceptedCurrencies']],
        'tld_registrar' => ['entity' => \Box\Mod\Servicedomain\Entity\TldRegistrar::class, 'columns' => ['config' => 'config']],
        'transaction' => ['entity' => \Box\Mod\Invoice\Entity\Transaction::class, 'columns' => ['ipn' => 'ipn']],
        'session' => ['entity' => \Box\Mod\System\Entity\Session::class, 'columns' => ['fingerprint' => 'fingerprint']],
    ];
}
