<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\GeoIP;

class ASN implements \JsonSerializable
{
    public readonly int|float $asnNumber;
    public readonly string $asnOrg;

    public function __construct(array $asnRecord)
    {
        if (!array_key_exists('autonomous_system_number', $asnRecord) || !array_key_exists('autonomous_system_organization', $asnRecord)) {
            throw new IncompleteRecord('The is no ASN information for the provided IP address');
        }

        $this->asnNumber = $asnRecord['autonomous_system_number'];
        $this->asnOrg = $asnRecord['autonomous_system_organization'];
    }

    public function jsonSerialize(): array
    {
        return [
            'asnNumber' => $this->asnNumber,
            'asnOrg' => $this->asnOrg,
        ];
    }
}
