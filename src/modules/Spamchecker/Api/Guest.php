<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Spam checking module management.
 */

namespace Box\Mod\Spamchecker\Api;

class Guest extends \Api_Abstract
{
    /**
     * Returns recaptcha configuration info.
     *
     * @return array
     */
    public function recaptcha($data)
    {
        $config = $this->di['mod_config']('Spamchecker');

        return [
            'publickey' => $config['captcha_recaptcha_publickey'] ?? null,
            'enabled' => $config['captcha_enabled'] ?? false,
            'version' => $config['captcha_version'] ?? null,
        ];
    }
}
