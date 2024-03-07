<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Massmailer;

use FOSSBilling\Environment;

class Service implements \FOSSBilling\InjectionAwareInterface
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

    public function install()
    {
        $extensionService = $this->di['mod_service']('extension');

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

        $sql = 'SELECT DISTINCT c.id
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

    public function sendMessage($model, $client_id, bool $sendNow = false)
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
            throw new \FOSSBilling\InformationException('Disabled for security reasons (Demo mode enabled)');
        }

        if (!Environment::isProduction()) {
            if (DEBUG) {
                error_log('Skip email sending. Application ENV: ' . Environment::getCurrentEnvironment());
            }

            return true;
        }

        $emailService = $this->di['mod_service']('email');
        $emailService->sendMail($data['to'], $data['from'], $data['subject'], $data['content'], $data['to_name'], $data['from_name'], $data['client_id'], null, $sendNow);

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

    public function sendMail($params)
    {
        $model = $this->di['db']->load('mod_massmailer', $params['msg_id']);
        if (!$model) {
            throw new \Exception('Mass mail message not found');
        }
        $this->sendMessage($model, $params['client_id']);
    }
}
