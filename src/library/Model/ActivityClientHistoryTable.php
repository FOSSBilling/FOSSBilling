<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
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
    public function logEvent($data): void
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

        $this->di['em']->getConnection()->executeStatement(
            'INSERT INTO activity_client_history (client_id, ip, created_at, updated_at) VALUES (:client_id, :ip, :created_at, :updated_at)',
            [
                'client_id' => $data['client_id'],
                'ip' => $ip,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    public function rmByClient(Model_Client $client): void
    {
        $this->di['em']->getConnection()->executeStatement(
            'DELETE FROM activity_client_history WHERE client_id = :client_id',
            ['client_id' => $client->id]
        );
    }
}
