<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_ActivityClientHistoryTable implements FOSSBilling\InjectionAwareInterface
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

    /**
     * @param array $data
     */
    public function logEvent($data)
    {
        if (!isset($data['client_id']) || !isset($data['ip'])) {
            return;
        }

        $extensionService = $this->di['mod_service']('extension');
        if ($extensionService->isExtensionActive('mod', 'demo')) {
            $ip = null;
        } else {
            $ip = $data['ip'];
        }

        $entry = $this->di['db']->dispense('ActivityClientHistory');
        $entry->client_id = $data['client_id'];
        $entry->ip = $ip;
        $entry->created_at = date('Y-m-d H:i:s');
        $entry->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($entry);
    }

    public function rmByClient(Model_Client $client)
    {
        $models = $this->di['db']->find('ActivityClientHistory', 'client_id = ?', [$client->id]);
        foreach ($models as $model) {
            $this->di['db']->trash($model);
        }
    }
}
