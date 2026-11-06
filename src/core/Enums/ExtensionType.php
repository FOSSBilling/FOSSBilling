<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Enums;

/**
 * Canonical extension types.
 *
 * The underlying string values must match the `extension.type` column values
 * and the extension directory API so the enum stays interchangeable with the
 * string-based code paths. This is the single source of truth, replacing the
 * former `TYPE_*` constant sets on {@see \FOSSBilling\Core\Remote\ExtensionManager} and
 * {@see \Box\Mod\Extension\Entity\Extension}.
 *
 * Note: the old `Extension::TYPE_PG` constant held the value `'pg'`, which
 * nothing in the codebase ever wrote or read (payment gateways always used
 * `'payment-gateway'`). The discrepancy died with the constants.
 */
enum ExtensionType: string
{
    case Module = 'module';
    case Theme = 'theme';
    case PaymentGateway = 'payment-gateway';
    case ServerManager = 'server-manager';
    case DomainRegistrar = 'domain-registrar';
    case Translation = 'translation';

    /**
     * Map a legacy type discriminator to its canonical value.
     *
     * Pre-migration callers, stored rows and third-party integrations may
     * still use 'mod'; every other value passes through unchanged.
     */
    public static function normalize(string $type): string
    {
        return $type === 'mod' ? self::Module->value : $type;
    }
}
