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

namespace Box\Mod\Security\Controller;

class Admin implements \FOSSBilling\InjectionAwareInterface
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

    public function fetchNavigation(): array
    {
        return [
            'group' => [
                'index' => 650,
                'location' => 'security',
                'label' => __trans('Security'),
                'class' => 'lock',
            ],
            'subpages' => [
                [
                    'location' => 'security',
                    'label' => __trans('Security Dashboard'),
                    'index' => 100,
                    'uri' => $this->di['url']->adminLink('security'),
                    'class' => '',
                ],
                [
                    'location' => 'security',
                    'label' => __trans('IP Lookup'),
                    'index' => 200,
                    'uri' => $this->di['url']->adminLink('security/iplookup'),
                    'class' => '',
                ],
                [
                    'location' => 'security',
                    'label' => __trans('Rate Limits'),
                    'index' => 300,
                    'uri' => $this->di['url']->adminLink('security/rate-limits'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app): void
    {
        $app->get('/security', 'get_index', [], static::class);
        $app->get('/security/', 'get_index', [], static::class);
        $app->get('/security/iplookup', 'ip_lookup', [], static::class);
        $app->get('/security/rate-limits', 'rate_limits', [], static::class);
    }

    public function get_index(\Box_App $app): string
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_security_index');
    }

    public function ip_lookup(\Box_App $app): string
    {
        $this->di['is_admin_logged'];
        $record = [];

        if (isset($_GET['ip']) && filter_var($_GET['ip'], FILTER_VALIDATE_IP)) {
            try {
                $record = $this->di['api']('admin')->Security_IP_Lookup(['ip' => $_GET['ip']]);
            } catch (\Exception) {
            }
        }

        return $app->render('mod_security_iplookup', ['record' => $record]);
    }

    public function rate_limits(\Box_App $app): string
    {
        $this->di['is_admin_logged'];
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('security', 'view');

        $ip = $_GET['ip'] ?? null;
        $invalidIp = false;
        if (is_string($ip) && $ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP)) {
            $invalidIp = true;
            $ip = null;
        }

        $counters = $invalidIp ? [] : $this->di['mod_service']('Security')->getRateLimitList(is_string($ip) && $ip !== '' ? $ip : null);
        $search = $_GET['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $search = strtolower($search);
            $counters = array_values(array_filter($counters, static fn (array $counter): bool => str_contains(strtolower((string) $counter['ip']), $search) || str_contains(strtolower((string) $counter['policy']), $search)));
        }

        $status = $_GET['status'] ?? null;
        if (in_array($status, ['active', 'limited'], true)) {
            $counters = array_values(array_filter($counters, static fn (array $counter): bool => $status === 'limited' ? (bool) $counter['limited'] : !(bool) $counter['limited']));
        }

        return $app->render('mod_security_rate_limits', [
            'rate_limit_status' => $this->di['mod_service']('Security')->getRateLimitStatus(),
            'rate_limit_counters' => $counters,
            'rate_limit_invalid_ip' => $invalidIp,
        ]);
    }
}
