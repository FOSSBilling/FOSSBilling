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

namespace Box\Mod\Email;

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

    public function getSearchQuery($data)
    {
        $query = 'SELECT * FROM activity_client_email';

        $search = $this->di['array_get']($data, 'search', NULL);
        $client_id = $this->di['array_get']($data, 'client_id', NULL);

        $where = array();
        $bindings = array();

        if($search) {
            $search = "%$search%";
            $where[] = '(sender LIKE :sender OR recipients LIKE :recipient OR subject LIKE :subject OR content_text LIKE :content_text OR content_html LIKE :content_html)';
            $bindings[':sender'] = $search;
            $bindings[':recipient'] = $search;
            $bindings[':subject'] = $search;
            $bindings[':content_text'] = $search;
            $bindings[':content_html'] = $search;
        }

        if(NULL !== $client_id) {
            $where[] = "client_id = :client_id";
            $bindings[':client_id'] = $client_id;
        }

        if (!empty($where)){
            $query = $query . ' WHERE '.implode(' AND ', $where);
        }
        $query .= ' ORDER BY id DESC';

        return array($query, $bindings);
    }

    public function findOneForClientById(\Model_Client $client, $id)
    {
        $bindings = array(
            ':id' => $id,
            ':client_id' => $client->id
        );

        $db = $this->di['db'];
        return $db->findOne('ActivityClientEmail', 'id = :id AND client_id = :client_id ORDER BY id DESC', $bindings);
    }

    public function rmByClient(\Model_Client $client)
    {
        $models = $this->di['db']->find('ActivityClientEmail', 'client_id = ?', array($client->id));
        foreach($models as $model)
        {
            $this->di['db']->trash($model);
        }
        return true;
    }

    public function rm(\Model_ActivityClientEmail $email)
    {
        $db = $this->di['db'];
        $db->trash($email);
        return true;
    }

    public function toApiArray(\Model_ActivityClientEmail $model, $deep = true)
    {
        $data = array(
            'id'           => $model->id,
            'client_id'    => $model->client_id,
            'sender'       => $model->sender,
            'recipients'   => $model->recipients,
            'subject'      => $model->subject,
            'content_html' => $model->content_html,
            'content_text' => $model->content_text,
            'created_at'   => $model->created_at,
            'updated_at'   => $model->updated_at,
        );
        return $data;
    }

    public function setVars($t, $vars)
    {
        $t->vars = $this->di['crypt']->encrypt(json_encode($vars), 'v8JoWZph12DYSY4aq8zpvWdzC');
        $this->di['db']->store($t);

        return true;
    }

    /**
     * @param \Model_EmailTemplate $t
     */
    public function getVars($t)
    {
        $json = $this->di['crypt']->decrypt($t->vars, 'v8JoWZph12DYSY4aq8zpvWdzC');

        return $this->di['tools']->decodeJ($json);
    }

    public function sendTemplate($data)
    {
        $required = array(
            'code' => 'Template code not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if (!isset($data['to']) && !isset($data['to_staff']) && !isset($data['to_client'])) {
            throw new \Box_Exception('Receiver is not defined. Define to or to_client or to_staff parameter');
        }
        $vars = $data;
        unset($vars['to'], $vars['to_client'], $vars['to_staff'], $vars['to_name'], $vars['from'], $vars['from_name']);
        unset($vars['default_description'], $vars['default_subject'], $vars['default_template'], $vars['code']);

        //add aditional variables to template
        if (isset($data['to_staff']) && $data['to_staff']) {
            $staffService =  $this->di['mod_service']('staff');
            $staff         = $staffService->getList(array('status' => 'active', 'no_cron' => true));
            $vars['staff'] = $staff['list'][0];
        }

        //add aditional variables to template
        if (isset($data['to_client']) && $data['to_client'] > 0) {
            $clientService =  $this->di['mod_service']('client');
            $customer  = $clientService->get(array('id' => $data['to_client']));
            $customer  = $clientService->toApiArray($customer);
            $vars['c'] = $customer;
        }

        $db = $this->di['db'];

        $t  = $db->findOne('EmailTemplate', 'action_code = :action', array(':action' => $data['code']));
        if (!$t instanceof \Model_EmailTemplate) {

            list($s, $c, $desc, $enabled, $mod) = $this->_getDefaults($data);
            $t              = $db->dispense('EmailTemplate');
            $t->enabled     = $enabled;
            $t->action_code = $data['code'];
            $t->category    = $mod;
            $t->subject     = $s;
            $t->content     = $c;
            $t->description = $desc;
            $db->store($t);
        }

        //update template vars with latest variables
        $this->setVars($t, $vars);

        // do not send inactive template
        if (!$t->enabled) {
            return false;
        }
        $systemService =  $this->di['mod_service']('system');

        list($subject, $content) = $this->_parse($t, $vars);
        $from      = $this->di['array_get']($data, 'from', $systemService->getParamValue('company_email'));
        $from_name = $this->di['array_get']($data, 'from_name', $systemService->getParamValue('company_name'));
        $sent      = false;

        if (!$from){
            throw new \Box_Exception('From email address can not be empty');
        }

        if (isset($staff)) {
            foreach ($staff['list'] as $staff) {
                $to      = $staff['email'];
                $to_name = $staff['name'];
                $sent    = $this->sendMail($to, $from, $subject, $content, $to_name, $from_name, null, $staff['id']);
            }
        } else if (isset($customer)) {
            $to      = $customer['email'];
            $to_name = $customer['first_name'] . ' ' . $customer['last_name'];
            $sent    = $this->sendMail($to, $from, $subject, $content, $to_name, $from_name, $customer['id']);
        } else {
            $to      = $data['to'];
            $to_name = $this->di['array_get']($data, 'to_name', null);
            $sent    = $this->sendMail($to, $from, $subject, $content, $to_name, $from_name);
        }

        return $sent;
    }

    public function sendMail($to, $from, $subject, $content, $to_name = null, $from_name = null, $client_id = null, $admin_id = null)
    {
        $email = $this->_queue($to, $from, $subject, $content, $to_name, $from_name, $client_id, $admin_id);
        $this->_sendFromQueue($email);
        return true;
    }

    private function _getDefaults($data)
    {
        $code = $data['code'];

        $enabled     = 0;
        $subject     = $this->di['array_get']($data, 'default_subject', ucwords(str_replace('_', ' ', $code)));
        $content     = $this->di['array_get']($data, 'default_template', $this->_getVarsString());
        $description = $this->di['array_get']($data, 'default_description', null);

        $matches = array();
        preg_match("/mod_([a-zA-Z0-9]+)_([a-zA-Z0-9]+)/i", $code, $matches);
        $mod  = $matches[1];
        $path = BB_PATH_MODS . '/' . ucfirst($mod) . '/html_email/' . $code . '.phtml';

        if (file_exists($path)) {
            $tpl = file_get_contents($path);

            $ms = array();
            preg_match('#{%.?block subject.?%}((.*?)+){%.?endblock.?%}#', $tpl, $ms);
            if (isset($ms[1])) {
                $subject = $ms[1];
            }

            $mc = array();
            preg_match('/{%.?block content.?%}((.*?\n)+){%.?endblock.?%}/m', $tpl, $mc);
            if (isset($mc[1])) {
                $content = $mc[1];
                $enabled = 1;
            }
        }

        return array($subject, $content, $description, $enabled, $mod);
    }

    private function _queue($to, $from, $subject, $content, $to_name = null, $from_name = null, $client_id = null, $admin_id = null)
    {
        $db                = $this->di['db'];

        $queue             = $db->dispense('ModEmailQueue');
        $queue->recipient  = $to;
        $queue->sender     = $from;
        $queue->subject    = $subject;
        $queue->content    = $content;
        $queue->to_name    = $to_name;
        $queue->from_name  = $from_name;
        $queue->client_id  = $client_id;
        $queue->admin_id   = $admin_id;
        $queue->status     = 'unsent';
        $queue->created_at = date('Y-m-d H:i:s');
        $queue->updated_at = date('Y-m-d H:i:s');
        $queue->priority   = 1;
        $queue->tries      = 0;
        try {
            $db->store($queue);
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        return $queue;
    }

    private function _getVarsString()
    {
        $str = '{% apply markdown %}' . PHP_EOL . PHP_EOL;
        $str .= 'This email template was automatically generated by BoxBilling extension.   ' . PHP_EOL;
        $str .= 'Template is ready to be modified.   ' . PHP_EOL;
        $str .= 'Email template is just like BoxBilling theme file.   ' . PHP_EOL;
        $str .= 'Use **admin** and **guest** API calls to get additional information using variables passed to template.' . PHP_EOL . PHP_EOL;
        $str .= 'Example API usage in email template:' . PHP_EOL . PHP_EOL;
        $str .= '{{ guest.system_version }}' . PHP_EOL . PHP_EOL;
        $str .= "{{ now|date('Y-m-d') }}" . PHP_EOL . PHP_EOL;
        $str .= '{% endapply %}';
        return $str;
    }

    private function _parse(\Model_EmailTemplate $t, $vars)
    {
        $systemService = $this->di['mod_service']('System');
        $pc = $systemService->renderString($t->content, false, $vars);
        $ps = $systemService->renderString($t->subject, false, $vars);
        return array($ps, $pc);
    }


    public function resend(\Model_ActivityClientEmail $email)
    {
        $di = $this->getDi();

        $mail = $di['mail'];
        $mail->setBodyHtml($email->content_html);
        $mail->setFrom($email->sender);
        $mail->setSubject($email->subject);
        $mail->addTo($email->recipients);

        $settings      = $this->di['mod_config']('email');
        $transport       = $this->di['array_get']($settings, 'mailer', 'sendmail');

        if (APPLICATION_ENV == 'testing') {
            if (BB_DEBUG) error_log('Skipping email sending in test environment');
            return true;
        }

        try {

		    $mod      = $this->di['mod']('email');
            $settings = $mod->getConfig();

            if (isset($settings['log_enabled']) && $settings['log_enabled']) {
                $activityService =  $this->di['mod_service']('activity');
                $activityService->logEmail($email->subject, $email->client_id, $email->sender, $email->recipients, $email->content_html, $email->content_text);
            }
            
            $mail->send($transport, $settings);
            

        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        $this->di['logger']->info('Resent email #%s', $email->id);

        return true;
    }

    public function templateGetSearchQuery($data)
    {
        $query = "SELECT * FROM email_template";

        $code   = $this->di['array_get']($data, 'code', NULL);
        $search = $this->di['array_get']($data, 'search', NULL);

        $where    = array();
        $bindings = array();

        if ($code) {
            $where[] = "action_code LIKE :code";

            $bindings[':code'] = "%$code%";
        }

        if ($search) {
            $search  = "%$search%";

            $where[] = "(action_code LIKE :action_code OR subject LIKE :subject OR content LIKE :content)";

            $bindings[':action_code'] = $search;
            $bindings[':subject']     = $search;
            $bindings[':content']     = $search;
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $query .= " ORDER BY category ASC";

        return array($query, $bindings);
    }

    public function templateToApiArray(\Model_EmailTemplate $model, $deep = false)
    {
        $data = array(
            'id'          => $model->id,
            'action_code' => $model->action_code,
            'category'    => $model->category,
            'enabled'     => $model->enabled,
            'subject'     => $model->subject,
            'description' => $model->description,
        );
        if ($deep) {
            $data['content'] = $model->content;
            $data['vars']    = $this->getVars($model);
        }

        return $data;
    }

    public function updateTemplate(\Model_EmailTemplate $model, $enabled = null, $category = null, $subject = null, $content = null)
    {
        if (isset($enabled)) {
            $model->enabled = $enabled;
        }

        if (isset($category)) {
            $model->category = $category;
        }

        $systemService = $this->di['mod_service']('System');
        $vars = $this->getVars($model);

        if (isset($subject)) {
            // check subject syntax before saving
            // should throw exception if render fails
            $vars['_tpl'] = $subject;
            $systemService->renderString($subject, false, $vars);
            $model->subject = $subject;
        }

        if (isset($content)) {
            // check content syntax before saving
            // should throw exception if render fails
            $vars['_tpl'] = $content;
            $systemService->renderString($content, false, $vars);

            $model->content = $content;
        }

        $this->di['db']->store($model);
        $this->di['logger']->info('Updated email template #%s', $model->id);

        return true;
    }

    public function resetTemplateByCode($code)
    {
        $t = $this->di['db']->findOne('EmailTemplate', 'action_code = :action', array(':action' => $code));

        if (!$t instanceof \Model_EmailTemplate) {
            throw new \Box_Exception('Email template :code was not found', array(':code' => $code));
        }

        $d = array('code' => $code);
        list($s, $c) = $this->_getDefaults($d);
        $this->updateTemplate($t, null, null, $s, $c);
        $this->di['logger']->info('Reset email template: %s', $t->action_code);

        return true;
    }


    public function getEmailById($id)
    {
        $model = $this->di['db']->findOne('ActivityClientEmail', 'id = ?', array($id));
        if (!$model instanceof \Model_ActivityClientEmail) {
            throw new \Box_Exception('Email not found');
        }

        return $model;
    }

    public function templateCreate($actionCode, $subject, $content, $enabled = 0, $category = null)
    {
        $model              = $this->di['db']->dispense('EmailTemplate');
        $model->action_code = $actionCode;
        $model->subject     = $subject;
        $model->enabled     = $enabled;
        $model->category    = $category;
        $model->content     = $content;

        $modelId = $this->di['db']->store($model);

        $this->di['logger']->info('Added new  email template #%s', $modelId);

        return $model;
    }

    public function templateBatchGenerate()
    {
        $pattern = BB_PATH_MODS . '/*/html_email/*.phtml';
        $list    = glob($pattern);
        foreach ($list as $path) {
            $code = pathinfo($path, PATHINFO_FILENAME);
            $dir  = pathinfo($path, PATHINFO_DIRNAME);
            $dir  = pathinfo($dir, PATHINFO_DIRNAME);
            $dir  = pathinfo($dir, PATHINFO_FILENAME);
            $mod = strtolower($dir);

            //skip if disableda
            $extensionService = $this->di['mod_service']('extension');


            if (!$extensionService->isExtensionActive('mod', $mod)) {
                continue;
            }

            // skip if already exists
            if ($this->di['db']->findOne('EmailTemplate', 'action_code = :code', array(':code' => $code))) {
                continue;
            }

            list($subject, $content, $desc, $enabled, $mod) = $this->_getDefaults(array('code' => $code));
            $t = $this->templateCreate($code, $subject, $content, $enabled, $mod);
            if ($desc) {
                $t->description = $desc;
                $this->di['db']->store($t);
            }
        }

        $this->di['logger']->info('Generated email templates for installed extensions');

        return true;
    }

    public function templateBatchDisable()
    {
        $sql = "UPDATE email_template SET enabled = 0 WHERE 1";
        $this->di['db']->exec($sql);
        $this->di['logger']->info('Disabled all email templates');

        return true;
    }

    public function templateBatchEnable()
    {
        $sql = "UPDATE email_template SET enabled = 1 WHERE 1";
        $this->di['db']->exec($sql);
        $this->di['logger']->info('Enabled all email templates');

        return true;
    }

    public function batchSend(){
        $mod        = $this->di['mod']('email');
        $settings   = $mod->getConfig();
        $tries      = 0;
        $time_limit = ($this->di['array_get']($settings, 'time_limit', 5)) * 60;
        $start      = time();
        while ($email = $this->di['db']->findOne('ModEmailQueue', ' status = \'unsent\' ORDER BY updated_at ')) {
            $this->_sendFromQueue($email);
            $tries++;
            if (isset($settings['queue_once']) && $settings['queue_once'] && $settings['queue_once'] <= $tries) break;
            if ($time_limit && time() - $start > $time_limit) break;
        }
    }

    private function _sendFromQueue(\Model_ModEmailQueue $queue)
    {
        $queue->status = 'sending';
        $this->di['db']->store($queue);

        $queue->content .= PHP_EOL;

        $mod      = $this->di['mod']('email');
        $settings = $mod->getConfig();

        if (isset($settings['log_enabled']) && $settings['log_enabled']) {
            $activityService =  $this->di['mod_service']('activity');
            $activityService->logEmail($queue->subject, $queue->client_id, $queue->sender, $queue->recipient, $queue->content);
        }

        $transport = $this->di['array_get']($settings, 'mailer', 'sendmail');

        try {
            $mail = $this->di['mail'];
            $mail->setSubject($queue->subject);
            $mail->setBodyHtml($queue->content);
            $mail->setFrom($queue->sender, $queue->from_name);
            $mail->addTo($queue->recipient, $queue->to_name);

            if (APPLICATION_ENV != 'production') {
                error_log('Skip email sending. Application ENV: '.APPLICATION_ENV);
                return true;
            }

            $mail->send($transport, $settings);
            try {
                $this->di['db']->trash($queue);
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
            if ($queue->priority) $queue->priority--;
            $queue->status = 'unsent';
            $queue->tries++;
            $queue->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($queue);
            if ($settings['cancel_after'] && $queue->tries > $settings['cancel_after']) $this->di['db']->trash($queue);
        }
        return true;
    }



}
