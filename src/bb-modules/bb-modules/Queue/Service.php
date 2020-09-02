<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


namespace Box\Mod\Queue;

class Service implements \Box\InjectionAwareInterface
{
    protected $di;

    /**
     * @param mixed $di
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

    public function uninstall()
    {
        $this->di['db']->exec('DROP TABLE queue;');
        $this->di['db']->exec('DROP TABLE queue_message;');
    }
    
    public function install()
    {
        $sql="
        CREATE TABLE IF NOT EXISTS `queue` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) DEFAULT NULL,
        `module` varchar(255) DEFAULT NULL,
        `timeout` bigint(20) DEFAULT NULL,
        `iteration` int(10) DEFAULT NULL,
        `created_at` varchar(35) DEFAULT NULL,
        `updated_at` varchar(35) DEFAULT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ";
        $this->di['db']->exec($sql);
        
        $sql="
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
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ";
        
        $this->di['db']->exec($sql);
        $columns = $this->di['db']->getColumns('queue');
        if(!array_key_exists('module', $columns)) {
            $this->di['db']->exec('ALTER TABLE  `queue` ADD  `module` VARCHAR( 255 ) NULL AFTER  `name`;');
        }
        
        if(!array_key_exists('iteration', $columns)) {
            $this->di['db']->exec('ALTER TABLE  `queue` ADD  `iteration` VARCHAR( 255 ) NULL AFTER  `timeout`;');
        }
        
        $columns = $this->di['db']->getColumns('queue_message');
        if(!array_key_exists('handler', $columns)) {
            $this->di['db']->exec('ALTER TABLE  `queue_message` ADD  `handler` VARCHAR( 255 ) NULL AFTER  `handle`;');
        }
        
        if(!array_key_exists('execute_at', $columns)) {
            $this->di['db']->exec('ALTER TABLE  `queue_message` ADD  `execute_at` VARCHAR( 255 ) NULL AFTER  `log`;');
        }
    }
    
    public function getSearchQuery($data)
    {
        $sql="SELECT *
            FROM queue
            WHERE 1 ";
        
        $params = array();
        
        $search = $this->di['array_get']($data, 'search', NULL);
        $name = $this->di['array_get']($data, 'name', NULL);
        $mod = $this->di['array_get']($data, 'mod', NULL);
        
        if($mod) {
            $sql .= " AND mod = :mod ";
            $params['mod'] = $mod;
        }
        
        if($name) {
            $sql .= " AND name = :name ";
            $params['name'] = $name;
        }
        
        if($search) {
            $sql .= " AND (name LIKE :search)";
            $params['search'] = '%'.$search.'%';
        }
        
        $sql .= ' ORDER BY id DESC';
        return array($sql, $params);
    }

    public function toApiArray($row)
    {
        if($row instanceof \RedBeanPHP\OODBBean) {
            $row = $row->export();
        }
        
        $sql="SELECT COUNT(id) FROM queue_message WHERE queue_id = :id GROUP BY queue_id";
        $count = $this->di['db']->getCell($sql, array('id'=>$row['id']));
        $row['messages_count'] = ($count) ? $count : 0;
        
        return $row;
    }
    
    public static function dummy($params)
    {
        throw new \Exception('Received params: '.var_export($params, 1));
    }
}