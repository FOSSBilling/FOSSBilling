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

class Admin extends \FOSSBilling\Api\AbstractApi
{
    public function get_config($data): array
    {
        $this->checkPermissions('antispam', 'view');

        $config = $this->getDi()['mod_config']('Antispam');

        return [
            'block_ips' => $config['block_ips'] ?? false,
            'blocked_ips' => $config['blocked_ips'] ?? '',
            'captcha_enabled' => $config['captcha_enabled'] ?? false,
            'captcha_provider' => $config['captcha_provider'] ?? 'recaptcha_v2',
            'captcha_recaptcha_publickey' => $config['captcha_recaptcha_publickey'] ?? null,
            'captcha_recaptcha_privatekey' => $config['captcha_recaptcha_privatekey'] ?? null,
            'captcha_recaptcha_v3_threshold' => $config['captcha_recaptcha_v3_threshold'] ?? 0.5,
            'turnstile_site_key' => $config['turnstile_site_key'] ?? null,
            'turnstile_secret_key' => $config['turnstile_secret_key'] ?? null,
            'hcaptcha_site_key' => $config['hcaptcha_site_key'] ?? null,
            'hcaptcha_secret_key' => $config['hcaptcha_secret_key'] ?? null,
            'sfs' => $config['sfs'] ?? false,
            'check_temp_emails' => $config['check_temp_emails'] ?? true,
            'honeypot_enabled' => $config['honeypot_enabled'] ?? true,
            'honeypot_field' => $config['honeypot_field'] ?? 'bio',
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

    private function getBlockedIpsFromConfig(array $config): array
    {
        $blocked_ips = isset($config['blocked_ips']) && !empty($config['blocked_ips'])
            ? explode(PHP_EOL, (string) $config['blocked_ips'])
            : [];
        $trimmed_ips = array_map(trim(...), $blocked_ips);
        $filtered_ips = array_filter($trimmed_ips, static fn (string $value): bool => $value !== '');

        return array_values($filtered_ips);
    }

    private function saveBlockedIpsConfig(array $blocked_ips, bool $enabled): void
    {
        $config = $this->getDi()['mod_config']('Antispam');
        $config['block_ips'] = $enabled;
        $config['blocked_ips'] = implode(PHP_EOL, $blocked_ips);
        $config['ext'] = 'mod_antispam';
        $this->getDi()['mod_service']('extension')->setConfig($config);
    }

    public function block_ip($data): array
    {
        $this->checkPermissions('antispam', 'manage');

        $ip = $this->normalizeIp($data['ip'] ?? null);

        $config = $this->getDi()['mod_config']('Antispam');
        $blocked_ips = $this->getBlockedIpsFromConfig($config);

        if (in_array($ip, $blocked_ips, true)) {
            throw new \FOSSBilling\InformationException(':ip is already blocked.', [':ip' => $ip]);
        }

        if ((string) $this->getDi()['request']->getClientIp() === $ip) {
            throw new \FOSSBilling\InformationException('You cannot block :ip as it is the IP you are making requests from.', [':ip' => $ip]);
        }

        $blocked_ips[] = $ip;
        $this->saveBlockedIpsConfig($blocked_ips, true);

        return ['result' => true, 'ip' => $ip];
    }

    public function unblock_ip($data): array
    {
        $this->checkPermissions('antispam', 'manage');

        $ip = $this->normalizeIp($data['ip'] ?? null);

        $config = $this->getDi()['mod_config']('Antispam');
        $blocked_ips = $this->getBlockedIpsFromConfig($config);

        $key = array_search($ip, $blocked_ips, true);
        if ($key === false) {
            throw new \FOSSBilling\InformationException(':ip is not currently blocked.', [':ip' => $ip]);
        }

        unset($blocked_ips[$key]);
        $this->saveBlockedIpsConfig($blocked_ips, !empty($blocked_ips));

        return ['result' => true, 'ip' => $ip];
    }

    public function get_blocked_ips($data): array
    {
        $this->checkPermissions('antispam', 'view');

        $config = $this->getDi()['mod_config']('Antispam');
        $blocked_ips = $this->getBlockedIpsFromConfig($config);

        return [
            'enabled' => $config['block_ips'] ?? false,
            'ips' => $blocked_ips,
        ];
    }
}
