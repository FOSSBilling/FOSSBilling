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

class Model_ClientPasswordResetTable implements \FOSSBilling\InjectionAwareInterface
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

    public function generate(Model_Client $client, $ip)
    {
        $r = $this->di['db']->findOne('ClientPasswordReset', 'client_id', $client->id);
        if(!$r instanceof Model_ClientPasswordReset) {
            $r = $this->di['db']->dispense('ClientPasswordReset');
            $r->created_at  = date('Y-m-d H:i:s');
            $r->client_id   = $client->id;
        }

        $r->ip          = $ip;
        $r->hash        = hash('sha256', random_int(50, random_int(10, 99)));
        $r->updated_at  = date('Y-m-d H:i:s');
        $this->di['db']->store($r);

        return $r;
    }

    public function rmByClient(Model_Client $client)
    {
        $models = $this->di['db']->find('ClientPasswordReset', 'client_id = ?', array($client->id));
        foreach($models as $model){
            $this->di['db']->trash($model);
        }
    }

}
