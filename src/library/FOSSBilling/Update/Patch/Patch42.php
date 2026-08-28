<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Update\Patch;

use FOSSBilling\Update\Patcher;

class Patch42 implements PatchInterface
{
    public function getVersion(): int
    {
        return 42;
    }

    public function apply(Patcher $patcher): void
    {
        // This patch will migrate previous currency exchange rate data provider settings to the new ones
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/2189
        $ext_service = $patcher->di['mod_service']('extension');

        $pairs = $patcher->fetchKeyValue('SELECT param, value FROM setting');

        $config = $ext_service->getConfig('mod_currency');
        $config['ext'] = 'mod_currency'; // This should automatically be set, but some appear to be having cache issues that causes it to not be

        // Migrate the old currency exchange rate sync settings
        $key = $pairs['currencylayer'] ?? '';
        if ($key) {
            $config['provider'] = 'currency_data_api';
            $config['currencydata_key'] = $key;
        }

        // Now migrate the cron setting
        $cron = $pairs['currency_cron_enabled'] ?? 0;
        if ($cron == '1') {
            $config['sync_rate'] = 'auto';
        } else {
            $config['sync_rate'] = 'never';
        }

        $ext_service->setConfig($config);
    }
}
