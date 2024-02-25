<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Api;

class Service implements \FOSSBilling\InjectionAwareInterface
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

    public function getModulePermissions(): array
    {
        return [
            'hide_permissions' => true,
        ];
    }

    /**
     * @return int - 1
     */
    public function logRequest()
    {
        $request = $this->di['request'];
        $sql = '
            INSERT INTO api_request (ip, request, created_at)
            VALUES(:ip, :request, NOW())
        ';
        $values = [
            'ip' => $request->getClientAddress(),
            'request' => $_SERVER['REQUEST_URI'] ?? null,
        ];

        return $this->di['db']->exec($sql, $values);
    }

    /**
     * @param int         $since - timestamp
     * @param string|null $ip
     *
     * @return int
     */
    public function getRequestCount($since, $ip = null, $isLoginMethod = false)
    {
        if (!is_numeric($since)) {
            $since = strtotime($since);
        }
        $sinceIso = date('Y-m-d H:i:s', $since);
        $values = [
            'since' => $sinceIso,
        ];
        if ($isLoginMethod) {
            $sql = '
        SELECT COUNT(id) as cclogin
        FROM api_request
        WHERE created_at > :since
        ';
        } else {
            $sql = '
        SELECT COUNT(id) as cc
        FROM api_request
        WHERE created_at > :since
        ';
        }

        if ($ip != null) {
            $sql .= ' AND ip = :ip';
            $values['ip'] = $ip;
        }

        return (int) $this->di['db']->getCell($sql, $values);
    }
}
