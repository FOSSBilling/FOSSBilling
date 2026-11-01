<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;

class Patch58 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        $gateways = $patcher->fetchAll(
            "SELECT id, config FROM pay_gateway WHERE gateway = 'Custom'"
        );

        foreach ($gateways as $gateway) {
            $config = json_decode($gateway['config'] ?? '', true);
            if (!is_array($config)) {
                continue;
            }

            $fields = ['single', 'recurrent'];
            $needsSave = false;
            foreach ($fields as $field) {
                if (isset($config[$field]) && is_string($config[$field]) && preg_match('/\b(function|include|import|extends|range|max|min|dump|system|guest\.|admin\.|client\.)\b/i', $config[$field])) {
                    $patcher->logUpdate('warning', 'Custom payment adapter template for gateway ID {gateway_id} contained incompatible Twig syntax and has been cleared. Please re-create it with compatible syntax.', ['gateway_id' => $gateway['id']]);
                    unset($config[$field]);
                    $needsSave = true;
                }
            }

            if ($needsSave) {
                $patcher->updateTable('pay_gateway', [
                    'config' => json_encode($config, JSON_UNESCAPED_SLASHES),
                ], ['id' => $gateway['id']]);
            }
        }
    }
}
