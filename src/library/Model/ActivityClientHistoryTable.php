<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


class Model_ActivityClientHistoryTable implements \Box\InjectionAwareInterface
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

    /**
     * @param array $data
     */
    public function logEvent($data)
    {
        if(!isset($data['client_id']) || !isset($data['ip'])) {
            return ;
        }

        $extensionService = $this->di['mod_service']('extension');
        if ($extensionService->isExtensionActive('mod', 'demo')) {
        $ip = null;
        } else {
        $ip = $data['ip'];
        }

        $entry = $this->di['db']->dispense('ActivityClientHistory');
        $entry->client_id       = $data['client_id'];
        $entry->ip              = $data['ip'];
        $entry->created_at      = date('Y-m-d H:i:s');
        $entry->updated_at      = date('Y-m-d H:i:s');
        $this->di['db']->store($entry);
    }

    public function rmByClient(Model_Client $client)
    {
        $models = $this->di['db']->find('ActivityClientHistory', 'client_id = ?', array($client->id));
        foreach($models as $model){
            $this->di['db']->trash($model);
        }
    }

}