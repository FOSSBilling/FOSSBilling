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


class Model_ApiRequestTable implements \Box\InjectionAwareInterface
{
    /**
     * @var \Box_Di
     */
    protected $di;

    /**
     * @param Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    public function logRequest($request, $ip)
    {
        $r = $this->di['db']->dispense('ApiRequest');
        $r->ip = $ip;
        $r->request = $request;
        $r->created_at = date('Y-m-d H:i:s');
        $this->di['db']->store($r);
    }

    public function getRequestCount($since, $ip = null)
    {
        $sinceIso = date('Y-m-d H:i:s', $since);

        $sql = 'SELECT count(id) as cc
                WHERE created_at > :since';

        $params = array(':since' => $sinceIso);

        if(NULL !== $ip) {
            $sql .= ' AND ip = :ip';
            $params[':ip'] = $ip;
        }

        $stmt = $this->di['pdo']->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn($stmt);
    }
}