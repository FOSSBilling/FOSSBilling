<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Queue;

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

    public function uninstall()
    {
        $this->di['db']->exec('DROP TABLE queue;');
        $this->di['db']->exec('DROP TABLE queue_message;');
    }

    public function install()
    {
        $sql = '
        CREATE TABLE IF NOT EXISTS `queue` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) DEFAULT NULL,
        `module` varchar(255) DEFAULT NULL,
        `timeout` bigint(20) DEFAULT NULL,
        `iteration` int(10) DEFAULT NULL,
        `created_at` varchar(35) DEFAULT NULL,
        `updated_at` varchar(35) DEFAULT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
        ';
        $this->di['db']->exec($sql);

        $sql = '
        CREATE TABLE IF NOT EXISTS `queue_message` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `queue_id` bigint(20) DEFAULT NULL,
        `handle` char(32) DEFAULT NULL,
        `handler` varchar(255) DEFAULT NULL,
        `body` longblob,
        `hash` char(32) DEFAULT NULL,
        `timeout` double(18,2) DEFAULT NULL,
        `log` text,
        `execute_at` varchar(35) DEFAULT NULL,
        `created_at` varchar(35) DEFAULT NULL,
        `updated_at` varchar(35) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `queue_id_idx` (`queue_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
        ';

        $this->di['db']->exec($sql);
        $columns = $this->di['db']->getColumns('queue');
        if (!array_key_exists('module', $columns)) {
            $this->di['db']->exec('ALTER TABLE  `queue` ADD  `module` VARCHAR( 255 ) NULL AFTER  `name`;');
        }

        if (!array_key_exists('iteration', $columns)) {
            $this->di['db']->exec('ALTER TABLE  `queue` ADD  `iteration` VARCHAR( 255 ) NULL AFTER  `timeout`;');
        }

        $columns = $this->di['db']->getColumns('queue_message');
        if (!array_key_exists('handler', $columns)) {
            $this->di['db']->exec('ALTER TABLE  `queue_message` ADD  `handler` VARCHAR( 255 ) NULL AFTER  `handle`;');
        }

        if (!array_key_exists('execute_at', $columns)) {
            $this->di['db']->exec('ALTER TABLE  `queue_message` ADD  `execute_at` VARCHAR( 255 ) NULL AFTER  `log`;');
        }
    }

    public function getSearchQuery($data)
    {
        $sql = 'SELECT *
            FROM queue
            WHERE 1 ';

        $params = [];

        $search = $data['search'] ?? null;
        $name = $data['name'] ?? null;
        $mod = $data['mod'] ?? null;

        if ($mod) {
            $sql .= ' AND mod = :mod ';
            $params['mod'] = $mod;
        }

        if ($name) {
            $sql .= ' AND name = :name ';
            $params['name'] = $name;
        }

        if ($search) {
            $sql .= ' AND (name LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY id DESC';

        return [$sql, $params];
    }

    public function toApiArray($row)
    {
        if ($row instanceof \RedBeanPHP\OODBBean) {
            $row = $row->export();
        }

        $sql = 'SELECT COUNT(id) FROM queue_message WHERE queue_id = :id GROUP BY queue_id';
        $count = $this->di['db']->getCell($sql, ['id' => $row['id']]);
        $row['messages_count'] = ($count) ? $count : 0;

        return $row;
    }

    public static function dummy($params)
    {
        throw new \Exception('Received params: ' . var_export($params, 1));
    }
}
