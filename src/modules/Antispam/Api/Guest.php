<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Spam checking module management.
 */

namespace Box\Mod\Antispam\Api;

class Guest extends \Api_Abstract
{
    /**
     * Returns recaptcha configuration info.
     */
    public function recaptcha($data): array
    {
        $config = $this->di['mod_config']('antispam');

        return [
            'publickey' => $config['captcha_recaptcha_publickey'] ?? null,
            'enabled' => $config['captcha_enabled'] ?? false,
            'version' => $config['captcha_version'] ?? null,
        ];
    }
}
