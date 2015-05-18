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

/**
 * Queue is a powerfull tool to execute long running tasks in the background.
 */

namespace Box\Mod\Queue\Api;

class Admin extends \Api_Abstract
{
    /**
     * Returns paginated list of queues
     * 
     * @optional string $mod - filter results by mod
     * @optional string $name - filter results by name
     * 
     * @return array
     */
    public function get_list($data)
    {
        list($sql, $params) = $this->getService()->getSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $pager['list'][$key] = $this->getService()->toApiArray($item);
        }

        return $pager;
    }
    
    /**
     * Get queue details
     * 
     * @param string $queue     - queue name, ie: massemails
     * 
     * @return array
     */
    public function get($data)
    {
        $q = $this->_getQueue($data);
        $service = $this->getService();
        return $service->toApiArray($q);
    }
    
    /**
     * Remove message from queue
     * 
     * @param type $int - message id
     * 
     * @return bool
     */
    public function message_delete($data)
    {
        $required = array(
            'id' => 'Queue message ID is required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        
        $msg = $this->di['db']->getExistingModelById('queue_message', $data['id'], 'Queue message not found');
        
        $this->di['db']->trash($msg);
        return true;
    }
    
    /**
     * Add message to queue to be executed later
     * 
     * @param string $queue     - unique queue name, ie: massemails
     * @param string $mod       - module name, ie: massmailer
     * 
     * @optional string $execute_at - Message execution time. Schedule message to be executed later, ie: 2022-12-29 14:53:51
     * @optional mixed $params      - queue message params. Any serializable param
     * @optional string $handler    - function handler. Static function name in extensions service class - default $queue name
     * @optional int $interval      - Interval to execute messages in the queue.  Default 30
     * @optional int $max           - Maximum amount of messages to be executed per interval. Default 25
     * 
     * @return int - message id
     */
    public function message_add($data)
    {
        $required = array(
            'queue' => 'Queue name not provided',
            'mod'   => 'Module name not provided',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        
        $mod = $this->di['mod']($data['mod']);
        $service = $mod->getService();
        $handler = isset($data['handler']) ? $data['handler'] : $data['queue'];
        if(!method_exists($service, $handler)) {
            throw new \Box_Exception('Message handler function :method does not exists', array(':ext'=>$data['mod'], ':method'=>  get_class($service).':'.$handler));
        }
        
        $interval = isset($data['interval']) ? (int)$data['interval'] : 30;
        $max = isset($data['max']) ? (int)$data['max'] : 25;
        $q = $this->di['db']->findOne('queue', 'name = :name', array('name'=>$data['queue']));
        if(!$q) {
            $q = $this->di['db']->dispense('queue');
            $q->name        = $data['queue'];
            $q->module      = $data['mod'];
            $q->created_at  = date('Y-m-d H:i:s');
        }
        $q->timeout = $interval;
        $q->iteration = $max;
        $q->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($q);
        
        $params = $this->di['array_get']($data, 'params', null);
        $body   = base64_encode(json_encode($params));
        
        $msg = $this->di['db']->dispense('queue_message');
        $msg->queue_id = $q->id;
        $msg->handler = $handler;
        $msg->body = $body;
        $msg->hash = md5($body);
        $msg->created_at = date('Y-m-d H:i:s');
        $msg->updated_at = date('Y-m-d H:i:s');
        
        if(isset($data['execute_at'])) {
            $msg->execute_at = date('Y-m-d H:i:s', strtotime($data['execute_at']));
        }
        
        $this->di['db']->store($msg);

        return (int)$msg->id;
    }
    
    /**
     * Execute queue.
     * For example: Send 25 emails every 30 seconds until complete
     * Executing queue is locked until finished.
     * 
     * @param string $queue - queue name to be executed
     * 
     * @optional int $max - Maximum amount of messages to be executed per interval. Default is queue max amount
     * @optional int $interval - interval in seconds for message to be executed. Default is queue timeout
     * @optional bool $until_complete - Execute until all messages in queue are executed. Default true
     * 
     * @return bool - returns true when queue finihed executing
     */
    public function execute($data)
    {
        $required = array(
            'queue' => 'Queue name not provided',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        
        $q = $this->di['db']->findOne('queue', 'name = :name', array('name'=>$data['queue']));
        if(!$q) {
            throw new \Exception('Queue not found');
        }

        $lock_file = BB_PATH_LOG.'/queue_'.$q->id.'.lock';
        touch($lock_file);
        $file_handle = fopen($lock_file, 'r+');
        if(!flock($file_handle, LOCK_EX | LOCK_NB)) {
            $this->di['logger']->info(sprintf('Queue %s is being executed by other process.', $q->id));
            throw new \Exception('This queue is being executed by other process.');
        }
        $this->di['logger']->info('Locked queue: '.$q->id);
        
        $max = isset($data['max']) ? (int)$data['max'] : $q->iteration;
        $interval = isset($data['interval']) ? (int)$data['interval'] : $q->timeout;
        $until_complete = isset($data['until_complete']) ? (bool)$data['until_complete'] : true;
        
        $this->di['logger']->info(sprintf('Started executing %s queue by selecting %s messages every %s seconds', $q->name, $max, $interval));
        
        $iterate = true;
        while($iterate) {
            $start = (float) array_sum(explode(' ',microtime())); 
            $r = $this->_execute($q, $max, $interval);
            $end = (float) array_sum(explode(' ',microtime())); 
            
            $wait_for = $interval - ($end-$start);
            
            if($wait_for > 0.000001) {
                $this->di['logger']->info('Waiting for '.$wait_for.' seconds to continue iteration');
                if(APPLICATION_ENV != 'testing') {
                    sleep($wait_for);
                }
            }
            
            if(!$until_complete) {
                $iterate = false;
            } else {
                $iterate = !empty($r);
            }
        }
        fclose($file_handle);
        $this->di['logger']->info('Unlocked queue: '.$q->id);
        unlink($lock_file);
        
        $this->di['logger']->info(sprintf('Finished executing queue %s', $q->name));
        return true;
    }
    
    private function _execute($q, $max, $interval)
    {
        $lsql = "UPDATE queue_message SET log = :log, updated_at = :u WHERE id = :id;";
        $dsql = "DELETE FROM queue_message WHERE id = :id;";
        $mod = $this->di['mod']($q->module);
        $service = $mod->getService();
        
        $msgs = $this->receiveQueueMessages($q->id, $max, $interval);
        $result = array();
        foreach($msgs as $msg) {
            try {
                $this->di['logger']->info(sprintf('Executing %s queue message #%s with handler %s(%s)', $q->name, $msg['id'], get_class($service).':'.$msg['handler'], $msg['json']));
                call_user_func(array($service, $msg['handler']), $msg['params']);
                $this->di['db']->exec($dsql, array('id'=>$msg['id']));
                $result[$msg['id']] = array('status'=> 'executed', 'error'=>null);
            } catch(\Exception $e) {
                $this->di['db']->exec($lsql, array('log'=>$e->getMessage(). ' '. $e->getCode(), 'id'=>$msg['id'], 'u'=>date('Y-m-d H:i:s')));
                $this->di['logger']->info(sprintf('Error executing queue %s message #%s %s', $q->name, $msg['id'], $e->getMessage()));
                $result[$msg['id']] = array('status'=> 'fail', 'error' => $e->getMessage());
            }
        }
        
        return $result;
    }
    
    private function _getQueue($data)
    {
        $required = array(
            'queue' => 'Queue name not provided',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        
        $q = $this->di['db']->findOne('queue', 'name = :name', array('name'=>$data['queue']));
        if(!$q) {
            throw new \Exception('Queue not found');
        }
        return $q;
    }
            
    /**
     * Select unselected messages from queue
     * 
     * @param Queue $queue
     * @param int $max
     * @param int $timeout
     * @return array
     */
    private function receiveQueueMessages($qid, $max, $timeout)
    {
        // start transaction handling
        if ( $max < 0 ) {
            return array();
        }
        
        $qid       = (int)$qid;
        $max       = (int)$max;
        $msgs      = array();
        $microtime = microtime(true); // cache microtime
        $db        = $this->di['pdo'];

        $sql = "SELECT id, handler, body
                FROM queue_message
                WHERE queue_id = $qid
                AND (handle IS NULL OR timeout+" . (int)$timeout . " < " . (int)$microtime .")
                AND (execute_at IS NULL OR UNIX_TIMESTAMP(execute_at) > UNIX_TIMESTAMP() )
                LIMIT $max";
        
        $sql2= "UPDATE queue_message
                SET
                    handle = :handle,
                    timeout = :timeout
                WHERE
                    id = :id
                    AND
                    (handle IS NULL OR timeout+" . (int)$timeout . " < " . (int)$microtime.')';
        
        $stmt1 = $db->prepare($sql);
        $stmt1->execute();
        
        foreach ($stmt1->fetchAll() as $data) {
            $stmt = $db->prepare($sql2);
            $stmt->bindValue('handle', md5(uniqid($microtime, true)), \PDO::PARAM_STR);
            $stmt->bindValue('id', $data['id'], \PDO::PARAM_INT);
            $stmt->bindValue('timeout', $microtime);
            if ($stmt->execute()) {
                $msgs[] = array(
                    'id'        =>  $data['id'],
                    'handler'   =>  $data['handler'],
                    'json'    =>  base64_decode($data['body']),
                    'params'    =>  json_decode(base64_decode($data['body']), 1),
                );
            }
        }

        return $msgs;
    }
}