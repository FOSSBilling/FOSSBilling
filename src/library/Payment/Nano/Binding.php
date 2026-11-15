<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Binds a Nano send block to one invoice with option C of the xno-binding v1
 * standard (docs/BINDING-STANDARD.md): the order commitment goes in the send
 * block's representative field.
 *
 *   commitment = SHA256("xno-binding/v1/rep|" + orderId + "|" + payTo + "|" + priceRaw)
 *
 * encoded as a `nano_` account. A Nano send has no memo, so a bare block hash
 * proves only that an amount reached an account; the commitment makes the block
 * belong to exactly one issued invoice at exactly one price, signed by the
 * payer. Pure: no dependency and no network, so it unit-tests on its own.
 */
final class Payment_Nano_Binding
{
    private const DOMAIN = 'xno-binding/v1/rep|';

    public static function commitmentHex(string $orderId, string $payTo, string $priceRaw): string
    {
        $priceRaw = Payment_Nano_Amount::normalizeRaw($priceRaw);

        return hash('sha256', self::DOMAIN . $orderId . '|' . $payTo . '|' . $priceRaw);
    }

    /**
     * The representative account a payer must set on the send block for this
     * invoice. The adapter compares it against the block's representative.
     */
    public static function representativeAccount(string $orderId, string $payTo, string $priceRaw): string
    {
        return Payment_Nano_Address::fromPublicKey(self::commitmentHex($orderId, $payTo, $priceRaw));
    }
}
