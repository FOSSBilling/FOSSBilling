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

use FOSSBilling\System\Config;
use FOSSBilling\Update\Patcher;

class Patch68 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        $row = $patcher->fetchOne(
            "SELECT meta_value FROM extension_meta WHERE extension = 'mod_cron' AND meta_key = 'config'",
        );

        if (!is_string($row) || $row === '') {
            return;
        }

        $configJson = $patcher->di['crypt']->decrypt($row, Config::getProperty('info.salt'));
        if (!is_string($configJson)) {
            return;
        }

        $config = json_decode($configJson, true);
        if (!is_array($config) || empty($config['guest_cron']) || !empty($config['cron_hash'])) {
            return;
        }

        $config['cron_hash'] = bin2hex(random_bytes(32));
        $encrypted = $patcher->di['crypt']->encrypt(json_encode($config, JSON_THROW_ON_ERROR), Config::getProperty('info.salt'));

        $patcher->executeSql(
            "UPDATE extension_meta SET meta_value = :config WHERE extension = 'mod_cron' AND meta_key = 'config'",
            ['config' => $encrypted],
        );
    }
}
