<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_ClientPasswordResetTable implements FOSSBilling\InjectionAwareInterface
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

    public function generate(Model_Client $client, $ip)
    {
        $r = $this->di['db']->findOne('ClientPasswordReset', 'client_id', $client->id);
        if (!$r instanceof Model_ClientPasswordReset) {
            $r = $this->di['db']->dispense('ClientPasswordReset');
            $r->created_at = date('Y-m-d H:i:s');
            $r->client_id = $client->id;
        }

        $r->ip = $ip;
        $r->hash = hash('sha256', random_int(50, random_int(10, 99)));
        $r->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($r);

        return $r;
    }

    public function rmByClient(Model_Client $client)
    {
        $models = $this->di['db']->find('ClientPasswordReset', 'client_id = ?', [$client->id]);
        foreach ($models as $model) {
            $this->di['db']->trash($model);
        }
    }
}
