<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Email;

use FOSSBilling\Config;
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

    public function getModulePermissions(): array
    {
        return [
            'can_always_access' => true,
            'manage_settings' => [],
        ];
    }

    public function getSearchQuery($data)
    {
        $query = 'SELECT * FROM activity_client_email';

        $search = $data['search'] ?? null;
        $client_id = $data['client_id'] ?? null;

        $where = [];
        $bindings = [];

        if ($search) {
            $search = "%$search%";
            $where[] = '(sender LIKE :sender OR recipients LIKE :recipient OR subject LIKE :subject OR content_text LIKE :content_text OR content_html LIKE :content_html)';
            $bindings[':sender'] = $search;
            $bindings[':recipient'] = $search;
            $bindings[':subject'] = $search;
            $bindings[':content_text'] = $search;
            $bindings[':content_html'] = $search;
        }

        if ($client_id !== null) {
            $where[] = 'client_id = :client_id';
            $bindings[':client_id'] = $client_id;
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }
        $query .= ' ORDER BY id DESC';

        return [$query, $bindings];
    }

    public function findOneForClientById(\Model_Client $client, $id)
    {
        $bindings = [
            ':id' => $id,
            ':client_id' => $client->id,
        ];

        $db = $this->di['db'];

        return $db->findOne('ActivityClientEmail', 'id = :id AND client_id = :client_id ORDER BY id DESC', $bindings);
    }

    public function rmByClient(\Model_Client $client)
    {
        $models = $this->di['db']->find('ActivityClientEmail', 'client_id = ?', [$client->id]);
        foreach ($models as $model) {
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
        return [
            'id' => $model->id,
            'client_id' => $model->client_id,
            'sender' => $model->sender,
            'recipients' => $model->recipients,
            'subject' => $model->subject,
            'content_html' => $model->content_html,
            'content_text' => $model->content_text,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
        ];
    }

    public function setVars($t, $vars)
    {
        $t->vars = $this->di['crypt']->encrypt(json_encode($vars), Config::getProperty('info.salt'));
        $this->di['db']->store($t);

        return true;
    }

    /**
     * @param \Model_EmailTemplate $t
     */
    public function getVars($t): array
    {
        $json = $this->di['crypt']->decrypt($t->vars, Config::getProperty('info.salt'));
        if (is_string($json) && json_validate($json)) {
            return json_decode($json, true);
        }

        return [];
    }

    public function sendTemplate($data)
    {
        $required = [
            'code' => 'Template code not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if (!isset($data['to']) && !isset($data['to_staff']) && !isset($data['to_client']) && !isset($data['to_admin'])) {
            throw new \FOSSBilling\InformationException('Receiver is not defined. Define to or to_client or to_staff or to_admin parameter');
        }
        $vars = $data;
        unset($vars['to'], $vars['to_client'], $vars['to_staff'], $vars['to_name'], $vars['from'], $vars['from_name'], $vars['to_admin']);
        unset($vars['default_description'], $vars['default_subject'], $vars['default_template'], $vars['code'], $vars['send_now'], $vars['throw_exceptions']);

        $send_now = $data['send_now'] ?? false;
        $throw_exceptions = $data['throw_exceptions'] ?? false;

        // add additional variables to template
        if (isset($data['to_staff']) && $data['to_staff']) {
            $staffService = $this->di['mod_service']('staff');
            $staff = $staffService->getList(['status' => 'active', 'no_cron' => true]);
            $vars['staff'] = $staff['list'][0];
        }

        // add additional variables to template
        if (isset($data['to_client']) && $data['to_client'] > 0) {
            $clientService = $this->di['mod_service']('client');
            $customer = $clientService->get(['id' => $data['to_client']]);
            $customer = $clientService->toApiArray($customer);
            $vars['c'] = $customer;
        }

        // send email to admins
        if (isset($data['to_admin']) && $data['to_admin'] > 0) {
            $oneStaff = $this->di['db']->findOne('Admin', 'id=?', [$data['to_admin']]);
            $vars['c'] = $oneStaff;
        }

        $db = $this->di['db'];

        $t = $db->findOne('EmailTemplate', 'action_code = :action', [':action' => $data['code']]);
        if (!$t instanceof \Model_EmailTemplate) {
            [$s, $c, $desc, $enabled, $mod] = $this->_getDefaults($data);
            $t = $db->dispense('EmailTemplate');
            $t->enabled = $enabled;
            $t->action_code = $data['code'];
            $t->category = $mod;
            $t->subject = $s;
            $t->content = $c;
            $t->description = $desc;
            $db->store($t);
        }

        $this->setVars($t, $vars);

        // do not send inactive template
        if (!$t->enabled) {
            return false;
        }
        $systemService = $this->di['mod_service']('system');

        [$subject, $content] = $this->_parse($t, $vars);
        $from = $data['from'] ?? $systemService->getParamValue('company_email');
        $from_name = $data['from_name'] ?? $systemService->getParamValue('company_name');
        $sent = false;

        if (!$from) {
            throw new \FOSSBilling\InformationException('The "from" email address cannot be empty');
        }

        if (isset($staff)) {
            foreach ($staff['list'] as $staff) {
                $to = $staff['email'];
                $to_name = $staff['name'];
                $sent = $this->sendMail($to, $from, $subject, $content, $to_name, $from_name, null, $staff['id'], $send_now, $throw_exceptions);
            }
        } elseif (isset($oneStaff)) {
            $to = $oneStaff->email;
            $to_name = $oneStaff->name;
            $sent = $this->sendMail($to, $from, $subject, $content, $to_name, $from_name, $oneStaff->id, null, $send_now, $throw_exceptions);
        } elseif (isset($customer)) {
            $to = $customer['email'];
            $to_name = $customer['first_name'] . ' ' . $customer['last_name'];
            $sent = $this->sendMail($to, $from, $subject, $content, $to_name, $from_name, $customer['id'], null, $send_now, $throw_exceptions);
        } else {
            $to = $data['to'];
            $to_name = $data['to_name'] ?? null;
            $sent = $this->sendMail($to, $from, $subject, $content, $to_name, $from_name, null, null, $send_now, $throw_exceptions);
        }

        return $sent;
    }

    public function sendMail($to, $from, $subject, $content, $to_name = null, $from_name = null, $client_id = null, $admin_id = null, bool $send_now = false, bool $throw_exceptions = false)
    {
        // Add the email to the queue
        $email = $this->_queue($to, $from, $subject, $content, $to_name, $from_name, $client_id, $admin_id);
        if ($send_now) {
            $this->_sendFromQueue($email, $throw_exceptions);
        }

        return true;
    }

    private function _getDefaults($data)
    {
        $code = $data['code'];

        $enabled = 0;
        $subject = $data['default_subject'] ?? ucwords(str_replace('_', ' ', $code));
        $content = $data['default_template'] ?? $this->_getVarsString();
        $description = $data['default_description'] ?? null;

        $matches = [];
        preg_match('/mod_([a-zA-Z0-9]+)_([a-zA-Z0-9]+)/i', $code, $matches);
        $mod = $matches[1];
        $path = PATH_MODS . DIRECTORY_SEPARATOR . ucfirst($mod) . DIRECTORY_SEPARATOR . 'html_email' . DIRECTORY_SEPARATOR . $code . '.html.twig';

        if (file_exists($path)) {
            $tpl = file_get_contents($path);

            $ms = [];
            preg_match('#{%.?block subject.?%}((.*?)+){%.?endblock.?%}#', $tpl, $ms);
            if (isset($ms[1])) {
                $subject = $ms[1];
            }

            $mc = [];
            preg_match('/{%.?block content.?%}((.*?\n)+){%.?endblock.?%}/m', $tpl, $mc);
            if (isset($mc[1])) {
                $content = $mc[1];
                $enabled = 1;
            }
        }

        return [$subject, $content, $description, $enabled, $mod];
    }

    private function _queue($to, $from, $subject, $content, $to_name = null, $from_name = null, $client_id = null, $admin_id = null)
    {
        $db = $this->di['db'];

        $queue = $db->dispense('ModEmailQueue');
        $queue->recipient = $to;
        $queue->sender = $from;
        $queue->subject = $subject;
        $queue->content = $content;
        $queue->to_name = $to_name;
        $queue->from_name = $from_name;
        $queue->client_id = $client_id;
        $queue->admin_id = $admin_id;
        $queue->status = 'unsent';
        $queue->created_at = date('Y-m-d H:i:s');
        $queue->updated_at = date('Y-m-d H:i:s');
        $queue->priority = 1;
        $queue->tries = 0;

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
        $str .= 'This email template was automatically generated by FOSSBilling extension.   ' . PHP_EOL;
        $str .= 'Template is ready to be modified.   ' . PHP_EOL;
        $str .= 'Email template is just like FOSSBilling theme file.   ' . PHP_EOL;
        $str .= 'Use **admin** and **guest** API calls to get additional information using variables passed to template.' . PHP_EOL . PHP_EOL;
        $str .= 'Example API usage in email template:' . PHP_EOL . PHP_EOL;
        $str .= '{{ FOSSBillingVersion }}' . PHP_EOL . PHP_EOL;
        $str .= "{{ now|date('Y-m-d') }}" . PHP_EOL . PHP_EOL;

        return $str . '{% endapply %}';
    }

    private function _parse(\Model_EmailTemplate $t, $vars)
    {
        $systemService = $this->di['mod_service']('System');
        $pc = $systemService->renderString($t->content, false, $vars);
        $ps = $systemService->renderString($t->subject, false, $vars);

        return [$ps, $pc];
    }

    public function resend(\Model_ActivityClientEmail $email)
    {
        $di = $this->getDi();
        $extensionService = $di['mod_service']('extension');
        if ($extensionService->isExtensionActive('mod', 'demo')) {
            return false;
        }

        if (Environment::isTesting()) {
            if (DEBUG) {
                $this->di['logger']->setChannel('email')->info('Skipping email sending in test environment');
            }

            return true;
        }

        $clientService = $this->di['mod_service']('client');
        $customer = $clientService->get(['id' => $email->client_id]);
        $customer = $clientService->toApiArray($customer);

        $systemService = $this->di['mod_service']('system');
        $from_name = $systemService->getParamValue('company_name');

        $this->sendMail($email->recipients, $email->sender, $email->subject, $email->content_html, $customer['first_name'] . ' ' . $customer['last_name'], $from_name, $email->client_id);

        $this->di['logger']->info('Resent email #%s', $email->id);

        return true;
    }

    public function templateGetSearchQuery($data)
    {
        $query = 'SELECT * FROM email_template';

        $code = $data['code'] ?? null;
        $search = $data['search'] ?? null;

        $where = [];
        $bindings = [];

        if ($code) {
            $where[] = 'action_code LIKE :code';

            $bindings[':code'] = "%$code%";
        }

        if ($search) {
            $search = "%$search%";

            $where[] = '(action_code LIKE :action_code OR subject LIKE :subject OR content LIKE :content)';

            $bindings[':action_code'] = $search;
            $bindings[':subject'] = $search;
            $bindings[':content'] = $search;
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $query .= ' ORDER BY category ASC';

        return [$query, $bindings];
    }

    public function queueGetSearchQuery($data)
    {
        $query = 'SELECT * FROM mod_email_queue';

        $search = $data['search'] ?? null;

        $where = [];
        $bindings = [];

        if ($search) {
            $search = "%$search%";

            $where[] = '(recipient LIKE :recipient OR subject LIKE :subject OR content LIKE :content OR to_name LIKE :to_name)';

            $bindings[':recipient'] = $search;
            $bindings[':subject'] = $search;
            $bindings[':content'] = $search;
            $bindings[':to_name'] = $search;
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $query .= ' ORDER BY updated_at DESC';

        return [$query, $bindings];
    }

    public function templateToApiArray(\Model_EmailTemplate $model, $deep = false): array
    {
        $data = [
            'id' => $model->id,
            'action_code' => $model->action_code,
            'category' => $model->category,
            'enabled' => $model->enabled,
            'subject' => $model->subject,
            'description' => $model->description,
        ];
        if ($deep) {
            $data['content'] = $model->content;
            $data['vars'] = $this->getVars($model);
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
        $t = $this->di['db']->findOne('EmailTemplate', 'action_code = :action', [':action' => $code]);

        if (!$t instanceof \Model_EmailTemplate) {
            throw new \FOSSBilling\Exception('Email template :code was not found', [':code' => $code]);
        }

        $d = ['code' => $code];
        [$s, $c] = $this->_getDefaults($d);
        $this->updateTemplate($t, null, null, $s, $c);
        $this->di['logger']->info('Reset email template: %s', $t->action_code);

        return true;
    }

    public function getEmailById($id)
    {
        $model = $this->di['db']->findOne('ActivityClientEmail', 'id = ?', [$id]);
        if (!$model instanceof \Model_ActivityClientEmail) {
            throw new \FOSSBilling\Exception('Email not found');
        }

        return $model;
    }

    public function templateCreate($actionCode, $subject, $content, $enabled = 0, $category = null)
    {
        $model = $this->di['db']->dispense('EmailTemplate');
        $model->action_code = $actionCode;
        $model->subject = $subject;
        $model->enabled = $enabled;
        $model->category = $category;
        $model->content = $content;

        $modelId = $this->di['db']->store($model);

        $this->di['logger']->info('Added new  email template #%s', $modelId);

        return $model;
    }

    public function templateBatchGenerate()
    {
        $pattern = PATH_MODS . '/*/html_email/*.html.twig';
        $list = glob($pattern);
        foreach ($list as $path) {
            $code = str_replace('.html', '', pathinfo($path, PATHINFO_FILENAME));
            $dir = pathinfo($path, PATHINFO_DIRNAME);
            $dir = pathinfo($dir, PATHINFO_DIRNAME);
            $dir = pathinfo($dir, PATHINFO_FILENAME);
            $mod = strtolower($dir);

            // skip if disabled
            $extensionService = $this->di['mod_service']('extension');

            if (!$extensionService->isExtensionActive('mod', $mod)) {
                continue;
            }

            // skip if already exists
            if ($this->di['db']->findOne('EmailTemplate', 'action_code = :code', [':code' => $code])) {
                continue;
            }

            [$subject, $content, $desc, $enabled, $mod] = $this->_getDefaults(['code' => $code]);
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
        $sql = 'UPDATE email_template SET enabled = 0 WHERE 1';
        $this->di['db']->exec($sql);
        $this->di['logger']->info('Disabled all email templates');

        return true;
    }

    public function templateBatchEnable()
    {
        $sql = 'UPDATE email_template SET enabled = 1 WHERE 1';
        $this->di['db']->exec($sql);
        $this->di['logger']->info('Enabled all email templates');

        return true;
    }

    /**
     * Sends emails from queue, respecting the configured time limit and emails per cron limit.
     * If an email fails to be sent, it will be skipped and retried on the next cron run until the retry limit is reached.
     *
     * @return void
     */
    public function batchSend()
    {
        $mod = $this->di['mod']('email');
        $settings = $mod->getConfig();

        $time_limit = ($settings['time_limit'] ?? 5) * 60;
        $sendPerCron = $settings['queue_once'] ?? 0;

        $start = time();

        $query = 'ORDER BY created_at ASC';
        if ($sendPerCron) {
            $query .= ' LIMIT ' . intval($sendPerCron);
            $mailQueue = $this->di['db']->findAll('mod_email_queue', $query);
        } else {
            $mailQueue = $this->di['db']->findAll('mod_email_queue', $query);
        }

        foreach ($mailQueue as $email) {
            $mailModel = new \Model_ModEmailQueue();
            $mailModel->loadBean($email);
            $this->_sendFromQueue($mailModel);
            if ($time_limit && time() - $start > $time_limit) {
                break;
            }
        }
    }

    private function _sendFromQueue(\Model_ModEmailQueue $queue, bool $throw_exceptions = false)
    {
        $extensionService = $this->di['mod_service']('extension');
        if ($extensionService->isExtensionActive('mod', 'demo')) {
            return false;
        }
        $queue->status = 'sending';
        $this->di['db']->store($queue);

        $queue->content .= PHP_EOL;

        $mod = $this->di['mod']('email');
        $settings = $mod->getConfig();
        $log = isset($settings['log_enabled']) && $settings['log_enabled'];

        $transport = $settings['mailer'] ?? 'sendmail';
        $sender = [
            'email' => $queue->sender,
            'name' => $queue->from_name,
        ];
        $recipient = [
            'email' => $queue->recipient,
            'name' => $queue->to_name,
        ];

        try {
            $mail = new \FOSSBilling\Mail($sender, $recipient, $queue->subject, $queue->content, $transport, $settings['custom_dsn'] ?? null);

            if (!Environment::isProduction()) {
                $this->di['logger']->setChannel('email')->info('Skip email sending. Application ENV: ' . Environment::getCurrentEnvironment());

                return true;
            }

            $mail->send($settings);

            // It sent without causing an exception (error), so we are safe to log it now
            if ($log) {
                $activityService = $this->di['mod_service']('activity');
                $activityService->logEmail($queue->subject, $queue->client_id, $queue->sender, $queue->recipient, $queue->content);
            }

            try {
                $this->di['db']->trash($queue);
            } catch (\Exception $e) {
                $this->di['logger']->setChannel('email')->err($e->getMessage());
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->di['logger']->setChannel('email')->err($e->getMessage());

            if ($queue->priority) {
                --$queue->priority;
            }

            $queue->status = 'unsent';
            ++$queue->tries;
            $queue->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($queue);

            $maxTries = $settings['cancel_after'] ?? 5;
            if ($queue->tries > $maxTries) {
                // The email failed to send after the max number of tries. This might be because of a server error, so let's be sure to log it which gives the client the ability to resend it.
                if ($log) {
                    $activityService = $this->di['mod_service']('activity');
                    $activityService->logEmail($queue->subject, $queue->client_id, $queue->sender, $queue->recipient, $queue->content);
                }
                $this->di['db']->trash($queue);
            }

            if ($throw_exceptions) {
                // If the error message is long, truncate it and inform the user the rest is in the error log.
                $truncated = (strlen($message) > 350) ? __trans('Error message truncated due to length, please check the error log for the complete message: ') . substr($message, 0, 350) . '...' : $message;

                throw new \FOSSBilling\Exception($truncated);
            }
        }

        return true;
    }
}
