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


class Box_EventManager implements \Box\InjectionAwareInterface
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

    public function fire($data)
    {
        if(!isset($data['event']) || empty($data['event'])) {
            error_log('Invoked event call without providing event name');
            return false;
        }

        $event = $data['event'];
        $subject = isset($data['subject']) ? $data['subject'] : null;
        $params = isset($data['params']) ? $data['params'] : null;

        if(BB_DEBUG) {
            $this->di['logger']->debug($event. ': '. var_export($params, 1));
        }

        $e = new Box_Event($subject, $event, $params);
        $e->setDi($this->di);
        $disp = new Box_EventDispatcher();
        $this->_connectDatabaseHooks($disp, $e->getName());
        $disp->notify($e);

        return $e->getReturnValue();
    }


    /**
     * @param Box_EventDispatcher $disp
     * @param string $event
     */
    private function _connectDatabaseHooks(&$disp, $event)
    {
        $sql="SELECT id, rel_id, meta_value
            FROM extension_meta
            WHERE extension = 'mod_hook'
            AND rel_type = 'mod'
            AND meta_key = 'listener'
            AND meta_value = :event
        ";
        $list = $this->di['db']->getAll($sql, array('event'=>$event));

        // no need to connect listeners
        if(empty($list)) {
            return ;
        }

        foreach($list as $listener) {
            $mod = $listener['rel_id'];
            $event = $listener['meta_value'];
            try {
                $s = $this->di['mod_service']($mod);
                if(method_exists($s, $event)) {
                    $disp->connect($event, array(get_class($s), $event));
                }
            } catch(Exception $e) {
                error_log($e->getMessage());
            }
        }
    }
}