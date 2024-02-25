<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_ApiRequestTable implements FOSSBilling\InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
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

        $params = [':since' => $sinceIso];

        if ($ip !== null) {
            $sql .= ' AND ip = :ip';
            $params[':ip'] = $ip;
        }

        $stmt = $this->di['pdo']->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn($stmt);
    }
}
