<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * This file is a delegate for module. Class does not extend any other class.
 *
 * All methods provided in this example are optional, but function names are
 * still reserved.
 */

namespace Box\Mod\Paidsupport;

use FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public static function onBeforeClientOpenTicket(\Box_Event $event)
    {
        $di = $event->getDi();
        $params = $event->getParameters();

        $client = $di['db']->load('Client', $params['client_id']);

        $paidSupportService = $di['mod_service']('Paidsupport');
        $paidSupportService->setDi($di);
        if ($paidSupportService->hasHelpdeskPaidSupport($params['support_helpdesk_id'])) {
            $paidSupportService->enoughInBalanceToOpenTicket($client);
        }

        return true;
    }

    /**
     * @return bool
     */
    public static function onAfterClientOpenTicket(\Box_Event $event)
    {
        $di = $event->getDi();
        $params = $event->getParameters();

        $supportTicket = $di['db']->load('SupportTicket', $params['id']);
        $client = $di['db']->load('Client', $supportTicket->client_id);

        $paidSupportService = $di['mod_service']('Paidsupport');
        $paidSupportService->setDi($di);
        if (!$paidSupportService->hasHelpdeskPaidSupport($supportTicket->support_helpdesk_id)) {
            return true;
        }

        $paidSupportService->enoughInBalanceToOpenTicket($client);

        $clientBalanceService = $di['mod_service']('Client', 'Balance');
        $clientBalanceService->setDi($di);

        $message = sprintf('Paid support ticket#%d "%s" opened', $supportTicket->id, $supportTicket->subject);
        $extra = [
            'rel_id' => $supportTicket->id,
            'type' => 'supportticket',
        ];
        $clientBalanceService->deductFunds($client, $paidSupportService->getTicketPrice(), $message, $extra);

        return true;
    }

    /**
     * @return float
     */
    public function getTicketPrice()
    {
        $config = $this->di['mod_config']('Paidsupport');
        if (!isset($config['ticket_price'])) {
            error_log('Paid Support ticket price is not set');

            return (float) 0;
        }

        return (float) $config['ticket_price'];
    }

    /**
     * @return string
     */
    public function getErrorMessage()
    {
        $config = $this->di['mod_config']('Paidsupport');
        $errorMessage = $config['error_msg'] ?? '';

        return strlen(trim($errorMessage)) > 0 ? $errorMessage : 'Configure paid support module!';
    }

    public function getPaidHelpdeskConfig()
    {
        $config = $this->di['mod_config']('Paidsupport');

        return $config['helpdesk'] ?? [];
    }

    public function enoughInBalanceToOpenTicket(\Model_Client $client)
    {
        $clientBalanceService = $this->di['mod_service']('Client', 'Balance');
        $clientBalance = $clientBalanceService->getClientBalance($client);

        if ($this->getTicketPrice() > $clientBalance) {
            throw new \FOSSBilling\Exception($this->getErrorMessage());
        }

        return true;
    }

    /**
     * @return bool
     */
    public function hasHelpdeskPaidSupport($id)
    {
        $helpdeskConfig = $this->getPaidHelpdeskConfig();

        if (isset($helpdeskConfig[$id]) && $helpdeskConfig[$id] == 1) {
            return true;
        }

        return false;
    }

    public function uninstall()
    {
        $model = $this->di['db']->findOne(
            'ExtensionMeta',
            'extension = :ext AND meta_key = :key',
            [':ext' => 'mod_paidsupport', ':key' => 'config']
        );
        if ($model instanceof \Model_ExtensionMeta) {
            $this->di['db']->trash($model);
        }

        return true;
    }

    public function install()
    {
        $extensionService = $this->di['mod_service']('Extension');
        $defaultConfig = [
            'ext' => 'mod_paidsupport',
            'error_msg' => 'Insufficient funds',
        ];
        $extensionService->setConfig($defaultConfig);

        return true;
    }
}
