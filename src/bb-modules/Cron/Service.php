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

namespace Box\Mod\Cron;

class Service
{
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function getCronInfo()
    {
        $service = $this->di['mod_service']('system');

        $result = array(
            'cron_url'          =>  BB_URL . 'bb-cron.php',
            'cron_path'         =>  BB_PATH_ROOT . DIRECTORY_SEPARATOR . 'bb-cron.php',
            'last_cron_exec'    =>  $service->getParamValue('last_cron_exec'),
        );
        return $result;
    }

    /**
     * @param null $interval - parameter from CLI, pass to filter crons to run
     * @return bool
     * @todo finish fixing, time to sleep
     */
    public function runCrons($interval = null)
    {
        $api = $this->di['api_system'];
        $this->di['logger']->info('- Started executing cron');

        //@core tasks
        $this->_exec($api, 'hook_batch_connect');
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminCronRun'));

        $this->_exec($api, 'invoice_batch_pay_with_credits');
        $this->_exec($api, 'invoice_batch_activate_paid');
        $this->_exec($api, 'invoice_batch_send_reminders');
        $this->_exec($api, 'invoice_batch_generate');
        $this->_exec($api, 'invoice_batch_invoke_due_event');
        $this->_exec($api, 'order_batch_suspend_expired');
        $this->_exec($api, 'order_batch_cancel_suspended');
        $this->_exec($api, 'support_batch_ticket_auto_close');
        $this->_exec($api, 'support_batch_public_ticket_auto_close');
        $this->_exec($api, 'client_batch_expire_password_reminders');
        $this->_exec($api, 'cart_batch_expire');
        $this->_exec($api, 'email_batch_sendmail');

        $create = (APPLICATION_ENV == 'production');
        $ss = $this->di['mod_service']('system');
        $ss->setParamValue('last_cron_exec', date('Y-m-d H:i:s'), $create);

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminCronRun'));

        $this->di['logger']->info('- Finished executing cron');
        return true;
    }

    /**
     * @param string $method
     */
    protected function _exec($api, $method, $params = null)
    {
        try {
            $api->{$method}($params);
        } catch(Exception $e) {
            throw new Exception($e);
        } finally {
            if (php_sapi_name() == 'cli') {
                print ("\e[32mSuccessfully ran " . $method . "(" . $params . ")" . ".\e[0m\n");
            }
        }
    }

    /**
     * @return string|null
     */
    public function getLastExecutionTime()
    {
        $service = $this->di['mod_service']('system');
        $last_exec = $service->getParamValue('last_cron_exec');
        return $last_exec;
    }

    public function isLate()
    {
        $t1 = new \DateTime($this->getLastExecutionTime());
        $t2 = new \DateTime('-6min');
        return ($t1 < $t2);
    }
}
