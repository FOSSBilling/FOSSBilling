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

/**
 * This file is a delegate for module. Class does not extend any other class.
 *
 * All methods provided in this example are optional, but function names are
 * still reserved.
 */

namespace Box\Mod\Paidsupport;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    /**
     * @var \Box_Di
     */
    protected $di;

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
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
        $errorMessage = $this->di['array_get']($config, 'error_msg', '');

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
            throw new \Box_Exception($this->getErrorMessage());
        }

        return true;
    }

    /**
     * @param $id
     *
     * @return bool
     */
    public function hasHelpdeskPaidSupport($id)
    {
        $helpdeskConfig = $this->getPaidHelpdeskConfig();

        if (isset($helpdeskConfig[$id]) && 1 == $helpdeskConfig[$id]) {
            return true;
        }

        return false;
    }

    public function uninstall()
    {
        $model = $this->di['db']->findOne('ExtensionMeta', 'extension = :ext AND meta_key = :key',
            [':ext' => 'mod_paidsupport', ':key' => 'config']);
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
