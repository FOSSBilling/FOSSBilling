<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


namespace Box\Mod\Massmailer;

class Service implements \Box\InjectionAwareInterface
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function install()
    {
        $extensionService = $this->di['mod_service']('extension');
        $extensionService->activateExistingExtension(array('id'=>'queue', 'type'=>'mod'));
        
        $sql="
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
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ";
        $this->di['db']->exec($sql);
        
        //default config values
        $extensionService->setConfig(array('ext'=>'mod_massmailer', 'limit'=>'2','interval'=>'10', 'test_client_id'=>1));
    }
    
    public function getSearchQuery($data)
    {
        $sql="SELECT *
            FROM mod_massmailer
            WHERE 1 ";
        
        $params = array();
        
        $search = (isset($data['search']) && !empty($data['search'])) ? $data['search'] : NULL;
        $status = $this->di['array_get']($data, 'status', NULL);
        
        if(NULL !== $status) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }
        
        if(NULL !== $search) {
            $sql .= ' AND (subject LIKE :search OR content LIKE :search OR from_email LIKE :search OR from_name LIKE :search)';
            $params[':search'] = '%'.$search.'%';
        }
        
        $sql .= ' ORDER BY created_at DESC';
        
        return array($sql, $params);
    }
    
    public function getMessageReceivers($model, $data = array())
    {
        $row = $this->toApiArray($model);
        $filter = $row['filter'];
        
        $sql="SELECT c.id, c.first_name, c.last_name 
            FROM client c
            LEFT JOIN client_order co ON (co.client_id = c.id)
            WHERE 1
        ";
        
        $values = array();
        if(!empty($filter)) {
            if(isset($filter['client_status']) && !empty($filter['client_status'])) {
                $sql .= sprintf(" AND c.status IN ('%s')", implode("', '", $filter['client_status']));
            }
            
            if(isset($filter['client_groups']) && !empty($filter['client_groups'])) {
                $sql .= sprintf(" AND c.client_group_id IN ('%s')", implode("', '", $filter['client_groups']));
            }
            
            if(isset($filter['has_order']) && !empty($filter['has_order'])) {
                $sql .= sprintf(" AND co.product_id IN ('%s')", implode("', '", $filter['has_order']));
            }
            
            if(isset($filter['has_order_with_status']) && !empty($filter['has_order_with_status'])) {
                $sql .= sprintf(" AND co.status IN ('%s')", implode("', '", $filter['has_order_with_status']));
            }
        }
        
        $sql .= ' ORDER BY c.id DESC';
        
        if(isset($data['debug']) && $data['debug']) {
            throw new \Exception($sql. ' '. print_r($values, 1));
        }
        
        return $this->di['db']->getAll($sql, $values);
    }
    
    public function getParsed($model, $client_id)
    {
        $clientService = $this->di['mod_service']('client');
        $systemService = $this->di['mod_service']('system');

        $client = $clientService->get(array('id'=>$client_id));
        $clientArr = $clientService->toApiArray($client, true, null);

        $vars = array();
        $vars['c'] = $clientArr;
        $vars['_tpl'] = $model->subject;
        $ps = $systemService->renderString($vars['_tpl'], false, $vars);
        
        $vars = array();
        $vars['c'] = $clientArr;
        $vars['_tpl'] = $model->content;
        $pc = $systemService->renderString($vars['_tpl'], false, $vars);
        
        return array($ps, $pc);
    }

    public function sendMessage($model, $client_id)
    {
        list($ps, $pc) = $this->getParsed($model, $client_id);

        $clientService = $this->di['mod_service']('client');

        $client = $clientService->get(array('id' => $client_id));

        $data = array(
            'to'        => $client->email,
            'to_name'   => $client->first_name . ' ' . $client->last_name,
            'from'      => $model->from_email,
            'from_name' => $model->from_name,
            'subject'   => $ps,
            'content'   => $pc,
            'client_id' => $client_id,
        );

        $mail = $this->di['mail'];
        $mail->setSubject($data['subject']);
        $mail->setBodyHtml($data['content']);
        $mail->setFrom($data['from'], $data['from_name']);
        $mail->addTo($data['to'], $data['to_name']);

        if (APPLICATION_ENV != 'production') {
            if($this->di['config']['debug'])
                error_log('Skip email sending. Application ENV: '.APPLICATION_ENV);
            return true;
        }

		$mod      = $this->di['mod']('email');
        $settings = $mod->getConfig();

        if (isset($settings['log_enabled']) && $settings['log_enabled']) {
            $activityService =  $this->di['mod_service']('activity');
            $activityService->logEmail($data['subject'], $client_id, $data['from'], $data['to'], $data['content']);
        }
		
        $emailSettings = $this->di['mod_config']('email');
        $transport     = $this->di['array_get']($emailSettings, 'mailer', 'sendmail');

        $mail->send($transport, $emailSettings);

        return true;
    }
    
    public function toApiArray($row)
    {
        if($row instanceof \RedBeanPHP\OODBBean) {
            $row = $row->export();
        }
        
        if($row['filter']) {
            $row['filter'] = json_decode($row['filter'], 1);
        } else {
            $row['filter'] = array();
        }
        
        return $row;
    }
    
    public static function onAfterAdminCronRun(\Box_Event $event)
    {
        try {
            $di = $event->getDi();
            $di['api_admin']->queue_execute(array('queue'=>'massmailer'));
        } catch(\Exception $e) {
            error_log('Error executing massmailer queue: '.$e->getMessage());
        }
    }
    
    public function sendMail($params)
    {
        $model = $this->di['db']->load('mod_massmailer', $params['msg_id']);
        if(!$model) {
            throw new \Exception('Mass mail message not found');
        }
        $this->sendMessage($model, $params['client_id']);
    }
}