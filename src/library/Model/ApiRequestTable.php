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
 * with this source code in the file LICENSE.
 */

class Model_ApiRequestTable implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di;

    /**
     * @param \Pimple\Container $di
     */
    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    /**
     * @return \Pimple\Container
     */
    public function getDi(): ?\Pimple\Container
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
