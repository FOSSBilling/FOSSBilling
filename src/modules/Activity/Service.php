<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Activity;

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

    public function getModulePermissions(): array
    {
        return [
            'delete_activity' => [
                'type' => 'bool',
                'display_name' => __trans('Delete activity'),
                'description' => __trans('Allows the staff member to delete recorded activity.'),
            ],
        ];
    }

    public function logEvent($data)
    {
        $extensionService = $this->di['mod_service']('extension');
        if ($extensionService->isExtensionActive('mod', 'demo')) {
            $ip = null;
        } else {
            $ip = $this->di['request']->getClientAddress();
        }

        $entry = $this->di['db']->dispense('ActivitySystem');
        $entry->client_id = $data['client_id'] ?? null;
        $entry->admin_id = $data['admin_id'] ?? null;
        $entry->priority = $data['priority'] ?? null;
        $entry->message = $data['message'];
        $entry->created_at = date('Y-m-d H:i:s');
        $entry->updated_at = date('Y-m-d H:i:s');
        $entry->ip = $ip;
        $this->di['db']->store($entry);
    }

    /** EVENTS  **/
    public static function onAfterClientLogin(\Box_Event $event)
    {
        $params = $event->getParameters();
        $di = $event->getDi();

        $extensionService = $di['mod_service']('extension');
        if ($extensionService->isExtensionActive('mod', 'demo')) {
            $ip = null;
        } else {
            $ip = $params['ip'];
        }

        $log = $di['db']->dispense('ActivityClientHistory');
        $log->client_id = $params['id'];
        $log->ip = $ip;
        $log->created_at = date('Y-m-d H:i:s');
        $log->updated_at = date('Y-m-d H:i:s');

        $di['db']->store($log);
    }

    public static function onAfterAdminLogin(\Box_Event $event)
    {
        $params = $event->getParameters();
        $di = $event->getDi();

        $extensionService = $di['mod_service']('extension');
        if ($extensionService->isExtensionActive('mod', 'demo')) {
            $ip = null;
        } else {
            $ip = $params['ip'];
        }

        $log = $di['db']->dispense('ActivityAdminHistory');
        $log->admin_id = $params['id'];
        $log->ip = $ip;
        $log->created_at = date('Y-m-d H:i:s');
        $log->updated_at = date('Y-m-d H:i:s');

        $di['db']->store($log);
    }

    public static function onBeforeAdminCronRun(\Box_Event $event): void
    {
        $di = $event->getDi();
        $config = $di['mod_service']('extension')->getConfig('mod_activity');

        $retention = intval($config['max_age'] ?? 90);
        $emailRetention = intval($config['email_max_age'] ?? 0);

        if ($retention === 0 && $emailRetention === 0) {
            return;
        }

        $ageInSeconds = intval($retention) * 86_400;
        $emailAgeInSeconds = intval($emailRetention) * 86_400;

        try {
            if ($retention !== 0) {
                $di['db']->exec('DELETE FROM activity_admin_history WHERE created_at <= :created_at', [':created_at' => date('Y-m-d H:i:s', time() - $ageInSeconds)]);
                $di['db']->exec('DELETE FROM activity_client_history WHERE created_at <= :created_at', [':created_at' => date('Y-m-d H:i:s', time() - $ageInSeconds)]);
                $di['db']->exec('DELETE FROM activity_system WHERE created_at <= :created_at', [':created_at' => date('Y-m-d H:i:s', time() - $ageInSeconds)]);
            }

            if ($emailRetention !== 0) {
                $di['db']->exec('DELETE FROM activity_client_email WHERE created_at <= :created_at', [':created_at' => date('Y-m-d H:i:s', time() - $emailAgeInSeconds)]);
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
    }

    public function getSearchQuery($data)
    {
        $sql = 'SELECT m.*, a.id as staff_id, a.email as staff_email, a.name as staff_name, c.id as client_id, CONCAT(c.first_name, " ", c.last_name) as client_name, c.email as client_email
                FROM activity_system as m
                left join admin as a on a.id = m.admin_id
                left join client as c on c.id = m.client_id';

        $params = [];
        $search = $data['search'] ?? null;
        $priority = $data['priority'] ?? null;
        $only_staff = $data['only_staff'] ?? null;
        $admin_id = $data['admin_id'] ?? null;
        $only_clients = $data['only_clients'] ?? null;
        $no_info = $data['no_info'] ?? null;
        $no_debug = $data['no_debug'] ?? null;
        $where = [];
        if ($priority) {
            $where[] = 'm.priority = :priority';
            $params[':priority'] = $priority;
        }

        if ($no_info) {
            $where[] = 'm.priority < :priority';
            $params[':priority'] = \Box_Log::INFO;
        }

        if ($no_debug) {
            $where[] = 'm.priority < :priority';
            $params[':priority'] = \Box_Log::DEBUG;
        }

        if ($only_staff) {
            $where[] = 'm.admin_id IS NOT NULL';
        }

        if ($admin_id) {
            $where[] = 'm.admin_id = :admin_id';
            $params[':admin_id'] = $admin_id;
        }

        if ($only_clients) {
            $where[] = 'm.client_id IS NOT NULL';
        }

        if ($search) {
            $where[] = 'm.message LIKE :search OR m.ip LIKE :search2';
            $params[':search'] = $search;
            $params[':search2'] = $search;
        }

        if (!empty($where)) {
            $whereStatement = implode(' and ', $where);
            $sql .= ' WHERE ' . $whereStatement;
        }

        $sql .= ' ORDER by m.id desc';

        return [$sql, $params];
    }

    public function logEmail($subject, $clientId = null, $sender = null, $recipients = null, $content_html = null, $content_text = null)
    {
        $entry = $this->di['db']->dispense('ActivityClientEmail');

        $entry->client_id = $clientId;
        $entry->sender = $sender;
        $entry->recipients = $recipients;
        $entry->subject = $subject;
        $entry->content_html = $content_html;
        $entry->content_text = $content_text;
        $entry->created_at = date('Y-m-d H:i:s');
        $entry->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($entry);

        return true;
    }

    public function toApiArray(\Model_ActivityClientHistory $model)
    {
        $client = $this->di['db']->getExistingModelById('Client', $model->client_id, 'Client not found');

        return [
            'id' => $model->id,
            'ip' => $model->ip,
            'created_at' => $model->created_at,
            'client' => [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'email' => $client->email,
            ],
        ];
    }

    public function rmByClient(\Model_Client $client)
    {
        $models = $this->di['db']->find('ActivitySystem', 'client_id = ?', [$client->id]);
        foreach ($models as $model) {
            $this->di['db']->trash($model);
        }
    }
}
