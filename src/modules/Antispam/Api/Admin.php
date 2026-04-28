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

namespace Box\Mod\Antispam\Api;

class Admin extends \Api_Abstract
{
    public function get_config($data): array
    {
        $config = $this->di['mod_config']('Antispam');

        return [
            'block_ips' => $config['block_ips'] ?? false,
            'blocked_ips' => $config['blocked_ips'] ?? '',
            'captcha_enabled' => $config['captcha_enabled'] ?? false,
            'captcha_provider' => $config['captcha_provider'] ?? 'recaptcha_v2',
            'captcha_recaptcha_publickey' => $config['captcha_recaptcha_publickey'] ?? null,
            'captcha_recaptcha_privatekey' => $config['captcha_recaptcha_privatekey'] ?? null,
            'turnstile_site_key' => $config['turnstile_site_key'] ?? null,
            'turnstile_secret_key' => $config['turnstile_secret_key'] ?? null,
            'hcaptcha_site_key' => $config['hcaptcha_site_key'] ?? null,
            'hcaptcha_secret_key' => $config['hcaptcha_secret_key'] ?? null,
            'sfs' => $config['sfs'] ?? false,
            'check_temp_emails' => $config['check_temp_emails'] ?? true,
            'honeypot_enabled' => $config['honeypot_enabled'] ?? true,
            'honeypot_field' => $config['honeypot_field'] ?? 'honeypot_field',
        ];
    }

    private function normalizeIp(mixed $ip): string
    {
        if (!is_string($ip)) {
            throw new \FOSSBilling\InformationException('IP address is required');
        }

        $ip = trim($ip);
        if ($ip === '') {
            throw new \FOSSBilling\InformationException('IP address is required');
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new \FOSSBilling\InformationException('Invalid IP address');
        }

        return $ip;
    }

    public function block_ip($data): array
    {
        $ip = $this->normalizeIp($data['ip'] ?? null);

        $config = $this->di['mod_config']('Antispam');
        $blocked_ips = isset($config['blocked_ips']) && !empty($config['blocked_ips'])
            ? explode(PHP_EOL, $config['blocked_ips'])
            : [];
        $blocked_ips = array_map(trim(...), $blocked_ips);

        if (in_array($ip, $blocked_ips, true)) {
            throw new \FOSSBilling\InformationException(':ip is already blocked.', [':ip' => $ip]);
        }

        $blocked_ips[] = $ip;
        $blocked_ips_string = implode(PHP_EOL, $blocked_ips);

        $config['block_ips'] = true;
        $config['blocked_ips'] = $blocked_ips_string;
        $this->di['mod_service']('extension')->setConfig($config);

        return ['result' => true, 'ip' => $ip];
    }

    public function unblock_ip($data): array
    {
        $ip = $data['ip'] ?? null;
        if (empty($ip)) {
            throw new \FOSSBilling\InformationException('IP address is required');
        }

        $config = $this->di['mod_config']('Antispam');
        $blocked_ips = isset($config['blocked_ips']) && !empty($config['blocked_ips'])
            ? explode(PHP_EOL, $config['blocked_ips'])
            : [];
        $blocked_ips = array_map(trim(...), $blocked_ips);

        $key = array_search($ip, $blocked_ips);
        if ($key === false) {
            throw new \FOSSBilling\InformationException(':ip is not currently blocked.', [':ip' => $ip]);
        }

        unset($blocked_ips[$key]);
        $blocked_ips_string = implode(PHP_EOL, $blocked_ips);

        $config['block_ips'] = !empty($blocked_ips);
        $config['blocked_ips'] = $blocked_ips_string;
        $this->di['mod_service']('extension')->setConfig($config);

        return ['result' => true, 'ip' => $ip];
    }

    public function get_blocked_ips($data): array
    {
        $config = $this->di['mod_config']('Antispam');
        $blocked_ips = isset($config['blocked_ips']) && !empty($config['blocked_ips'])
            ? explode(PHP_EOL, $config['blocked_ips'])
            : [];

        return [
            'enabled' => $config['block_ips'] ?? false,
            'ips' => array_map(trim(...), $blocked_ips),
        ];
    }
}
