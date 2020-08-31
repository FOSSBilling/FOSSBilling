<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Api;

class Service implements \Box\InjectionAwareInterface
{
    protected $di;

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Box_Di
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
        $sql="
            INSERT INTO api_request (ip, request, created_at)
            VALUES(:ip, :request, NOW())
        ";
        $values = array(
            'ip'        =>  $request->getClientAddress(),
            'request'   =>  $request->getURI(),
        );
        return $this->di['db']->exec($sql, $values);
    }

    /**
     * @param int $since - timestamp
     * @param string|null $ip
     * @return int
     */
    public function getRequestCount($since, $ip = null)
    {
        if (!is_numeric($since)){
            $since = strtotime($since);
        }
        $sinceIso = date('Y-m-d H:i:s', $since);
        $values = array(
            'since' =>  $sinceIso,
        );
        $sql="
        SELECT COUNT(id) as cc
        FROM api_request
        WHERE created_at > :since
        ";

        if(null != $ip) {
            $sql .= " AND ip = :ip";
            $values['ip'] = $ip;
        }

        return (int)$this->di['db']->getCell($sql, $values);
    }

    /**
     * @deprecated use DI
     */
    public function getApiGuest()
    {
        return $this->di['api_guest'];
    }


    /**
     * @deprecated use DI
     */
    public function getApiClient($id)
    {
        return $this->di['api_client'];
    }

    /**
     * @deprecated use DI
     */
    public function getApiAdmin($id = null)
    {
        return $this->di['api_admin'];
    }
}