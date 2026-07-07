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
 * Cron management.
 */

namespace Box\Mod\Cron\Api;

class Admin extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Returns cron job information. When it was last executed, where cron job
     * file is located.
     *
     * @return array
     */
    public function info($data)
    {
        $this->checkPermissions('cron', 'view');

        return $this->getService()->getCronInfo();
    }

    /**
     * Run cron.
     *
     * @return bool
     */
    public function run($data)
    {
        $this->checkPermissions('cron', 'manage');

        return $this->getService()->runCrons();
    }

    /**
     * Save cron settings, managing the cron_hash lifecycle.
     *
     * When guest_cron is enabled and no hash exists, one is generated automatically.
     * When guest_cron is disabled, the hash is cleared.
     *
     * @return bool
     */
    public function save_settings($data)
    {
        $this->checkPermissions('cron', 'manage');

        $guestCron = !empty($data['guest_cron']);

        // This endpoint is specific to the cron module; don't allow writing config for arbitrary extensions.
        $data = [
            'ext' => 'mod_cron',
            'guest_cron' => $guestCron,
        ];

        if ($guestCron) {
            $existing = $this->getMod()->getConfig();
            $existingHash = (string) ($existing['cron_hash'] ?? '');
            $data['cron_hash'] = $existingHash !== '' ? $existingHash : bin2hex(random_bytes(32));
        } else {
            $data['cron_hash'] = '';
        }

        return $this->getDi()['mod_service']('extension')->setConfig($data);
    }

    /**
     * Generate a new cron hash, replacing any existing one.
     */
    public function regenerate_cron_hash($data): array
    {
        $this->checkPermissions('cron', 'manage');

        $config = $this->getMod()->getConfig();
        $config['cron_hash'] = bin2hex(random_bytes(32));
        $config['ext'] = 'mod_cron';

        $this->getDi()['mod_service']('extension')->setConfig($config);

        return ['cron_hash' => $config['cron_hash']];
    }
}
