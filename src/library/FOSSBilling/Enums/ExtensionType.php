<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Enums;

/**
 * Canonical extension types.
 *
 * The underlying string values must match the `extension.type` column values
 * and the extension directory API so the enum stays interchangeable with the
 * string-based code paths. This is the single source of truth; the `TYPE_*`
 * constants on {@see \FOSSBilling\ExtensionManager} and
 * {@see \Box\Mod\Extension\Entity\Extension} are deprecated aliases.
 *
 * Note: the legacy `Extension::TYPE_PG` constant held the value `'pg'`, which
 * nothing in the codebase ever wrote or read (payment gateways always used
 * `'payment-gateway'`). It is reconciled to the live value here.
 */
enum ExtensionType: string
{
    case Module = 'mod';
    case Theme = 'theme';
    case PaymentGateway = 'payment-gateway';
    case ServerManager = 'server-manager';
    case DomainRegistrar = 'domain-registrar';
    case Translation = 'translation';
}
