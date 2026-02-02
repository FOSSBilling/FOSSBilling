<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\License\plugins;

class Simple
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function generate(\Model_ExtProductLicense $service, \Model_ClientOrder $order, array $config): string
    {
        $length = $config['length'] ?? 25;
        $prefix = $config['prefix'] ?? null;

        $character_array = [...range('A', 'Z'), ...range(1, 9)];
        $size = count($character_array) - 1;
        $string = '';
        for ($i = 1; $i < $length; ++$i) {
            $string .= ($i % 5 == 0) ? '-' : $character_array[random_int(0, $size)];
        }

        return $prefix . $string;
    }
}
