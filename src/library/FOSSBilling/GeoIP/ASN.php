<?php

declare(strict_types=1);
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\GeoIP;

class ASN implements \JsonSerializable
{
    private readonly int|float $asnNumber;
    private readonly string $asnOrg;

    public function __construct(array $asnRecord)
    {
        $this->asnNumber = $asnRecord['autonomous_system_number'] ?? 'Unknown';
        $this->asnOrg = $asnRecord['autonomous_system_organization'] ?? 'Unknown';
    }

    public function jsonSerialize(): array
    {
        return [
            'asnNumber' => $this->asnNumber,
            'asnOrg' => $this->asnOrg,
        ];
    }
}
