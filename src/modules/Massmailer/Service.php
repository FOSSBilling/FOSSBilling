<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Massmailer;

class Service implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function install()
    {
        $extensionService = $this->di['mod_service']('extension');
        $extensionService->activateExistingExtension(['id' => 'queue', 'type' => 'mod']);

        $sql = '
        CREATE TABLE IF NOT EXISTS `mod_massmailer` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `from_email` varchar(255) DEFAULT NULL,
        `from_name` varchar(255) DEFAULT NULL,
        `subject` varchar(255) DEFAULT NULL,
        `content` text DEFAULT NULL,
        `filter` text DEFAULT NULL,
        `status` varchar(255) DEFAULT NULL,
        `sent_at` varchar(35) DEFAULT NULL,
        `created_at` varchar(35) DEFAULT NULL,
        `updated_at` varchar(35) DEFAULT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
        ';
        $this->di['db']->exec($sql);

        // default config values
        $extensionService->setConfig(['ext' => 'mod_massmailer', 'limit' => '2', 'interval' => '10', 'test_client_id' => 1]);
    }

    public function getSearchQuery($data)
    {
        $sql = 'SELECT *
            FROM mod_massmailer
            WHERE 1 ';

        $params = [];

        $search = (isset($data['search']) && !empty($data['search'])) ? $data['search'] : null;
        $status = $data['status'] ?? null;

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }

        if ($search !== null) {
            $sql .= ' AND (subject LIKE :search OR content LIKE :search OR from_email LIKE :search OR from_name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY created_at DESC';

        return [$sql, $params];
    }

    public function getMessageReceivers($model, $data = [])
    {
        $row = $this->toApiArray($model);
        $filter = $row['filter'];

        $sql = 'SELECT c.id, c.first_name, c.last_name
            FROM client c
            LEFT JOIN client_order co ON (co.client_id = c.id)
            WHERE 1
        ';

        $values = [];
        if (!empty($filter)) {
            if (isset($filter['client_status']) && !empty($filter['client_status'])) {
                $sql .= sprintf(" AND c.status IN ('%s')", implode("', '", $filter['client_status']));
            }

            if (isset($filter['client_groups']) && !empty($filter['client_groups'])) {
                $sql .= sprintf(" AND c.client_group_id IN ('%s')", implode("', '", $filter['client_groups']));
            }

            if (isset($filter['has_order']) && !empty($filter['has_order'])) {
                $sql .= sprintf(" AND co.product_id IN ('%s')", implode("', '", $filter['has_order']));
            }

            if (isset($filter['has_order_with_status']) && !empty($filter['has_order_with_status'])) {
                $sql .= sprintf(" AND co.status IN ('%s')", implode("', '", $filter['has_order_with_status']));
            }
        }

        $sql .= ' ORDER BY c.id DESC';

        if (isset($data['debug']) && $data['debug']) {
            throw new \Exception($sql . ' ' . print_r($values, 1));
        }

        return $this->di['db']->getAll($sql, $values);
    }

    public function getParsed($model, $client_id)
    {
        $clientService = $this->di['mod_service']('client');
        $systemService = $this->di['mod_service']('system');

        $client = $clientService->get(['id' => $client_id]);
        $clientArr = $clientService->toApiArray($client, true, null);

        $vars = [];
        $vars['c'] = $clientArr;
        $vars['_tpl'] = $model->subject;
        $ps = $systemService->renderString($vars['_tpl'], false, $vars);

        $vars = [];
        $vars['c'] = $clientArr;
        $vars['_tpl'] = $model->content;
        $pc = $systemService->renderString($vars['_tpl'], false, $vars);

        return [$ps, $pc];
    }

    public function sendMessage($model, $client_id)
    {
        [$ps, $pc] = $this->getParsed($model, $client_id);

        $clientService = $this->di['mod_service']('client');

        $client = $clientService->get(['id' => $client_id]);

        $data = [
            'to' => $client->email,
            'to_name' => $client->first_name . ' ' . $client->last_name,
            'from' => $model->from_email,
            'from_name' => $model->from_name,
            'subject' => $ps,
            'content' => $pc,
            'client_id' => $client_id,
        ];

        $extensionService = $this->di['mod_service']('extension');
        if ($extensionService->isExtensionActive('mod', 'demo')) {
            throw new \Box_Exception('Disabled for security reasons (Demo mode enabled)');
        }

        $mail = $this->di['mail'];
        $mail->setSubject($data['subject']);
        $mail->setBodyHtml($data['content']);
        $mail->setFrom($data['from'], $data['from_name']);
        $mail->addTo($data['to'], $data['to_name']);

        if (APPLICATION_ENV != 'production') {
            if ($this->di['config']['debug']) {
                error_log('Skip email sending. Application ENV: ' . APPLICATION_ENV);
            }

            return true;
        }

        $mod = $this->di['mod']('email');
        $settings = $mod->getConfig();

        if (isset($settings['log_enabled']) && $settings['log_enabled']) {
            $activityService = $this->di['mod_service']('activity');
            $activityService->logEmail($data['subject'], $client_id, $data['from'], $data['to'], $data['content']);
        }

        $emailSettings = $this->di['mod_config']('email');
        $transport = $data['mailer'] ?? 'sendmail';

        $mail->send($transport, $emailSettings);

        return true;
    }

    public function toApiArray($row)
    {
        if ($row instanceof \RedBeanPHP\OODBBean) {
            $row = $row->export();
        }

        if ($row['filter']) {
            $row['filter'] = json_decode($row['filter'], 1);
        } else {
            $row['filter'] = [];
        }

        return $row;
    }

    public static function onAfterAdminCronRun(\Box_Event $event)
    {
        try {
            $di = $event->getDi();
            $di['api_admin']->queue_execute(['queue' => 'massmailer']);
        } catch (\Exception $e) {
            error_log('Error executing massmailer queue: ' . $e->getMessage());
        }
    }

    public function sendMail($params)
    {
        $model = $this->di['db']->load('mod_massmailer', $params['msg_id']);
        if (!$model) {
            throw new \Exception('Mass mail message not found');
        }
        $this->sendMessage($model, $params['client_id']);
    }
}
