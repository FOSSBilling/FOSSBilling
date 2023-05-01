<?php

/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Api;

class Service implements \Box\InjectionAwareInterface
{
    protected $di;

    /**
     * @param \Pimple\Container $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Pimple\Container
     */
    public function getDi()
    {
        return $this->di;
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

        if (null != $ip) {
            $sql .= ' AND ip = :ip';
            $values['ip'] = $ip;
        }

        return (int) $this->di['db']->getCell($sql, $values);
    }
}
