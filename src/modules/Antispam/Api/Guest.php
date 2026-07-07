<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Antispam\Api;

class Guest extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Returns recaptcha configuration info.
     */
    public function recaptcha($data): array
    {
        $config = $this->getDi()['mod_config']('Antispam');

        return [
            'publickey' => $config['captcha_recaptcha_publickey'] ?? null,
            'enabled' => $config['captcha_enabled'] ?? false,
            'version' => $config['captcha_version'] ?? null,
            'captcha_provider' => $config['captcha_provider'] ?? 'recaptcha_v2',
            'recaptcha_v3_threshold' => $config['captcha_recaptcha_v3_threshold'] ?? 0.5,
            'turnstile_site_key' => $config['turnstile_site_key'] ?? null,
            'hcaptcha_site_key' => $config['hcaptcha_site_key'] ?? null,
        ];
    }
}
