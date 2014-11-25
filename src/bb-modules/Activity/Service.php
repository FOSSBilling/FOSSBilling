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


namespace Box\Mod\Activity;
use Box\InjectionAwareInterface;
class Service implements InjectionAwareInterface
{
    protected $di = null;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function logEvent($data)
    {
        $entry = $this->di['db']->dispense('ActivitySystem');
        $entry->client_id       = isset($data['client_id']) ? $data['client_id'] : NULL;
        $entry->admin_id        = isset($data['admin_id']) ? $data['admin_id'] : NULL;
        $entry->priority        = isset($data['priority']) ? $data['priority'] : NULL;
        $entry->message         = $data['message'];
        $entry->created_at      = date('c');
        $entry->updated_at      = date('c');
        $entry->ip              = $this->di['request']->getClientAddress();
        $this->di['db']->store($entry);
    }

    /** EVENTS  **/
    
    /*
    public static function onEventAdminLoginFailed(Box_Event $event)
    {
        $api = $event->getApiAdmin();
        $params = $event->getParameters();
        $ip = $params['ip'];
        throw new Exception('Wait 2 minutes');
    }
    */
    
    public static function onAfterClientLogin(\Box_Event $event)
    {
        $params = $event->getParameters();
        $di = $event->getDi();

        $log = $di['db']->dispense('ActivityClientHistory');
        $log->client_id       = $params['id'];
        $log->ip              = $params['ip'];
        $log->created_at      = date('c');
        $log->updated_at      = date('c');

        $di['db']->store($log);
    }
    
    public static function onAfterAdminLogin(\Box_Event $event)
    {
        $params = $event->getParameters();
        $di = $event->getDi();
        
        $log= $di['db']->dispense('ActivityAdminHistory');
        $log->admin_id        = $params['id'];
        $log->ip              = $params['ip'];
        $log->created_at      = date('c');
        $log->updated_at      = date('c');

        $di['db']->store($log);
    }

    public function getSearchQuery($data)
    {

        $sql = 'SELECT m.*, a.id as staff_id, a.email as staff_email, a.name as staff_name, c.id as client_id, CONCAT(c.first_name, " ", c.last_name) as client_name, c.email as client_email
                FROM activity_system as m
                left join admin as a on a.id = m.admin_id
                left join client as c on c.id = m.client_id';

        $params = array();
        $search = isset($data['search']) ? $data['search'] : NULL;
        $priority = isset($data['priority']) ? $data['priority'] : NULL;
        $only_staff = isset($data['only_staff']) ? $data['only_staff'] : NULL;
        $only_clients = isset($data['only_clients']) ? $data['only_clients'] : NULL;
        $no_info = isset($data['no_info']) ? $data['no_info'] : NULL;
        $no_debug = isset($data['no_debug']) ? $data['no_debug'] : NULL;
        $where = array ();
        if($priority) {
            $where[] = 'm.priority = :priority';
            $params[':priority'] = $priority;
        }

        if($no_info) {
            $where[] = 'm.priority < :priority';
            $params[':priority'] = \Box_Log::INFO;
        }

        if($no_debug) {
            $where[] = 'm.priority < :priority';
            $params[':priority'] = \Box_Log::DEBUG;
        }

        if($only_staff) {
            $where[] = 'm.admin_id IS NOT NULL';
        }

        if($only_clients) {
            $where[] = 'm.client_id IS NOT NULL';
        }

        if($search) {
            $where[] = 'm.message LIKE :search OR m.ip LIKE :search2';
            $params[':search'] = $search;
            $params[':search2'] = $search;
        }

        if (count ($where) > 0){
            $whereStatment = implode(' and ', $where);
            $sql .= ' WHERE '.$whereStatment;
        }

        $sql .= ' ORDER by m.id desc';
        return array($sql, $params);
    }

    public function logEmail($subject, $clientId = null, $sender = null, $recipients = null, $content_html = null, $content_text = null)
    {
        $entry = $this->di['db']->dispense('ActivityClientEmail');

        $entry->client_id    = $clientId;
        $entry->sender       = $sender;
        $entry->recipients   = $recipients;
        $entry->subject      = $subject;
        $entry->content_html = $content_html;
        $entry->content_text = $content_text;
        $entry->created_at   = date('c');
        $entry->updated_at   = date('c');

        $this->di['db']->store($entry);

        return true;
    }

    public function toApiArray(\Model_ActivityClientHistory $model)
    {
        $client = $this->di['db']->getExistingModelById('Client', $model->client_id, 'Client not found');
        return array(
            'id'            =>  $model->id,
            'ip'            =>  $model->ip,
            'created_at'    =>  $model->created_at,
            'client'        =>  array(
                'id'            =>  $client->id,
                'first_name'    => $client->first_name,
                'last_name'     =>  $client->last_name,
                'email'         =>  $client->email,
            )
        );
    }

    public function rmByClient(\Model_Client $client)
    {
        $models = $this->di['db']->find('ActivitySystem', 'client_id = ?', array($client->id));
        foreach($models as $model){
            $this->di['db']->trash($model);
        }
    }
}