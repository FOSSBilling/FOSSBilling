<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Activity;

use Doctrine\DBAL\Connection;
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

    private function getDbal(): Connection
    {
        return $this->di['dbal'];
    }

    private static function getDbalFromDi(\Pimple\Container $di): Connection
    {
        return $di['dbal'];
    }

    public function logEvent($data): void
    {
        $extensionService = $this->di['mod_service']('extension');
        $ip = $extensionService->isExtensionActive('mod', 'demo')
            ? null
            : $this->di['request']->getClientIp();

        $this->getDbal()->insert('activity_system', [
            'client_id' => $data['client_id'] ?? null,
            'admin_id' => $data['admin_id'] ?? null,
            'priority' => $data['priority'] ?? null,
            'message' => $data['message'],
            'created_at' => date('Y-m-d H:i:s'),
            'ip' => $ip,
        ]);
    }

    /** EVENTS  **/
    public static function onAfterClientLogin(\Box_Event $event): void
    {
        $params = $event->getParameters();
        $di = $event->getDi();

        $extensionService = $di['mod_service']('extension');
        $ip = $extensionService->isExtensionActive('mod', 'demo') ? null : $params['ip'];

        self::getDbalFromDi($di)->insert('activity_client_history', [
            'client_id' => $params['id'],
            'ip' => $ip,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function onAfterAdminLogin(\Box_Event $event): void
    {
        $params = $event->getParameters();
        $di = $event->getDi();

        $extensionService = $di['mod_service']('extension');
        $ip = $extensionService->isExtensionActive('mod', 'demo') ? null : $params['ip'];

        self::getDbalFromDi($di)->insert('activity_admin_history', [
            'admin_id' => $params['id'],
            'ip' => $ip,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
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

        $ageInSeconds = $retention * 86_400;
        $emailAgeInSeconds = $emailRetention * 86_400;
        $dbal = self::getDbalFromDi($di);

        try {
            if ($retention !== 0) {
                $createdAt = date('Y-m-d H:i:s', time() - $ageInSeconds);
                $dbal->executeStatement('DELETE FROM activity_admin_history WHERE created_at <= ?', [$createdAt]);
                $dbal->executeStatement('DELETE FROM activity_client_history WHERE created_at <= ?', [$createdAt]);
                $dbal->executeStatement('DELETE FROM activity_system WHERE created_at <= ?', [$createdAt]);
            }

            if ($emailRetention !== 0) {
                $dbal->executeStatement('DELETE FROM activity_client_email WHERE created_at <= ?', [
                    date('Y-m-d H:i:s', time() - $emailAgeInSeconds),
                ]);
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
    }

    public function getSearchQuery($data): array
    {
        $sql = 'SELECT m.*, a.id as staff_id, a.email as staff_email, a.name as staff_name, CONCAT_WS(" ", c.first_name, c.last_name) as client_name, c.email as client_email
                FROM activity_system as m
                left join admin as a on a.id = m.admin_id
                left join client as c on c.id = m.client_id';

        $params = [];
        $search = $data['search'] ?? null;
        $ip = $data['ip'] ?? null;
        $priority = $data['priority'] ?? null;
        $min_priority = $data['min_priority'] ?? null;
        $user_filter = $data['user_filter'] ?? null;
        $admin_id = $data['admin_id'] ?? null;
        $client_id = $data['client_id'] ?? null;
        $date_from = $data['date_from'] ?? null;
        $date_to = $data['date_to'] ?? null;
        $where = [];

        if ($priority !== null && $priority !== '') {
            $where[] = 'm.priority = :priority';
            $params[':priority'] = $priority;
        } elseif ($min_priority !== null && $min_priority !== '') {
            $where[] = 'm.priority <= :min_priority';
            $params[':min_priority'] = $min_priority;
        }

        if ($user_filter === 'only_staff') {
            $where[] = 'm.admin_id IS NOT NULL';
        } elseif ($user_filter === 'only_clients') {
            $where[] = 'm.client_id IS NOT NULL';
        }

        if ($admin_id) {
            $where[] = 'm.admin_id = :admin_id';
            $params[':admin_id'] = $admin_id;
        }

        if ($client_id) {
            $where[] = 'm.client_id = :client_id';
            $params[':client_id'] = $client_id;
        }

        if ($date_from) {
            $where[] = 'm.created_at >= :date_from';
            $params[':date_from'] = date('Y-m-d 00:00:00', strtotime((string) $date_from));
        }

        if ($date_to) {
            $where[] = 'm.created_at <= :date_to';
            $params[':date_to'] = date('Y-m-d 23:59:59', strtotime((string) $date_to));
        }

        if ($ip) {
            $where[] = 'm.ip = :ip';
            $params[':ip'] = $ip;
        }

        if ($search) {
            $where[] = 'm.message LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' and ', $where);
        }

        $sql .= ' ORDER by m.id desc';

        return [$sql, $params];
    }

    public function logEmail($subject, $clientId = null, $sender = null, $recipients = null, $content_html = null, $content_text = null): bool
    {
        $this->getDbal()->insert('activity_client_email', [
            'client_id' => $clientId,
            'sender' => $sender,
            'recipients' => $recipients,
            'subject' => $subject,
            'content_html' => $content_html,
            'content_text' => $content_text,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function toApiArray(\Model_ActivityClientHistory $model): array
    {
        $client = $this->getDbal()->executeQuery(
            'SELECT id, first_name, last_name, email FROM client WHERE id = ?',
            [$model->client_id]
        )->fetchAssociative();

        if ($client === false) {
            throw new \FOSSBilling\Exception('Client not found');
        }

        return [
            'id' => $model->id,
            'ip' => $model->ip,
            'created_at' => $model->created_at,
            'client' => [
                'id' => $client['id'],
                'first_name' => $client['first_name'],
                'last_name' => $client['last_name'],
                'email' => $client['email'],
            ],
        ];
    }

    public function rmByClient(\Model_Client $client): void
    {
        $this->getDbal()->executeStatement('DELETE FROM activity_system WHERE client_id = ?', [$client->id]);
    }
}
