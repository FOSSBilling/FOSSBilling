<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Email;

use FOSSBilling\Config;
use FOSSBilling\Environment;
use FOSSBilling\Tools;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class Service implements \FOSSBilling\InjectionAwareInterface
{
    private const int BUILTIN_TEMPLATE = 0;
    private const int CUSTOM_TEMPLATE = 1;

    protected ?\Pimple\Container $di = null;
    private readonly Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

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

    public function getSearchQuery($data): array
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

    public function rmByClient(\Model_Client $client): bool
    {
        $models = $this->di['db']->find('ActivityClientEmail', 'client_id = ?', [$client->id]);
        foreach ($models as $model) {
            $this->di['db']->trash($model);
        }

        return true;
    }

    public function rm(\Model_ActivityClientEmail $email): bool
    {
        $db = $this->di['db'];
        $db->trash($email);

        return true;
    }

    public function toApiArray(\Model_ActivityClientEmail $model, $deep = true): array
    {
        return [
            'id' => $model->id,
            'client_id' => $model->client_id,
            'sender' => $model->sender,
            'recipients' => $model->recipients,
            'subject' => $model->subject,
            'content_html' => Tools::sanitizeContent($model->content_html ?? ''),
            'content_text' => $model->content_text,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
        ];
    }

    public function setVars($t, $vars): bool
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

        return is_string($json) ? json_decode($json, true) : [];
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

        $t = $this->getOrCreateTemplateByCode($data['code'], $data);

        $this->setVars($t, $vars);

        // do not send inactive template
        if (!$t->enabled) {
            return false;
        }
        $systemService = $this->di['mod_service']('system');

        [$subject, $content] = $this->_parse($t, $vars);

        $emailMod = $this->di['mod']('email');
        $emailSettings = $emailMod->getConfig();

        $customEmail = $emailSettings['from_email'] ?? '';
        $customName = $emailSettings['from_name'] ?? '';

        $companyEmail = $systemService->getParamValue('company_email');
        $companyName = $systemService->getParamValue('company_name');

        $from = $data['from'] ?? (!empty($customEmail) ? $customEmail : $companyEmail);
        $from_name = $data['from_name'] ?? (!empty($customName) ? $customName : $companyName);

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

    public function sendMail($to, $from, $subject, $content, $to_name = null, $from_name = null, $client_id = null, $admin_id = null, bool $send_now = false, bool $throw_exceptions = false): bool
    {
        // Add the email to the queue
        $email = $this->_queue($to, $from, $subject, $content, $to_name, $from_name, $client_id, $admin_id);
        if ($send_now) {
            $this->_sendFromQueue($email, $throw_exceptions);
        }

        return true;
    }

    private function _getDefaults($data): array
    {
        $code = $data['code'];

        $enabled = 0;
        $subject = $data['default_subject'] ?? ucwords(str_replace('_', ' ', $code));
        $content = $data['default_template'] ?? $this->_getVarsString();
        $description = $data['default_description'] ?? null;

        $matches = [];
        if (preg_match('/mod_([a-zA-Z0-9]+)_([a-zA-Z0-9]+)/i', (string) $code, $matches)) {
            $mod = $matches[1];
        } else {
            $mod = 'custom';
        }

        $path = Path::join(PATH_MODS, ucfirst($mod), 'templates/email', "{$code}.html.twig");

        if ($this->filesystem->exists($path)) {
            $tpl = $this->filesystem->readFile($path);

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

    private function getDefaultTemplate(string $code, array $data = []): ?array
    {
        $path = $this->getDefaultTemplatePath($code);
        if ($path === null) {
            return null;
        }

        [$subject, $content, $description, $enabled, $category] = $this->_getDefaults(array_merge($data, ['code' => $code]));

        return [
            'action_code' => $code,
            'category' => $category,
            'subject' => $subject,
            'content' => $content,
            'description' => $description,
            'enabled' => $enabled,
            'path' => $path,
        ];
    }

    public function hasDefaultTemplate(string $code): bool
    {
        return $this->getDefaultTemplatePath($code) !== null;
    }

    private function getDefaultTemplatePath(string $code): ?string
    {
        $matches = [];
        if (!preg_match('/mod_([a-zA-Z0-9]+)_([a-zA-Z0-9]+)/i', $code, $matches)) {
            return null;
        }

        $module = ucfirst($matches[1]);
        $path = Path::join(PATH_MODS, $module, 'templates/email', "{$code}.html.twig");

        return $this->filesystem->exists($path) ? $path : null;
    }

    private function isCustomTemplate(\Model_EmailTemplate $template): bool
    {
        return (int) ($template->is_custom ?? self::BUILTIN_TEMPLATE) === self::CUSTOM_TEMPLATE;
    }

    private function createBuiltinTemplateRecord(string $code, array $default): \Model_EmailTemplate
    {
        $template = $this->di['db']->dispense('EmailTemplate');
        $template->action_code = $code;
        $template->category = $default['category'];
        $template->description = $default['description'];
        $template->enabled = $default['enabled'];
        $template->is_custom = self::BUILTIN_TEMPLATE;
        $template->is_overridden = 0;
        $template->subject = $default['subject'];
        $template->content = $default['content'];

        $this->di['db']->store($template);

        return $template;
    }

    private function createTemplateRecordFromData(string $code, array $data): \Model_EmailTemplate
    {
        $default = $this->getDefaultTemplate($code, $data);
        if ($default !== null) {
            return $this->createBuiltinTemplateRecord($code, $default);
        }

        [$subject, $content, $description, $enabled, $category] = $this->_getDefaults($data);

        $template = $this->di['db']->dispense('EmailTemplate');
        $template->action_code = $code;
        $template->category = $category;
        $template->enabled = $enabled;
        $template->description = $description;
        $template->subject = $subject;
        $template->content = $content;
        $template->is_custom = self::CUSTOM_TEMPLATE;

        $this->di['db']->store($template);

        return $template;
    }

    private function getOrCreateTemplateByCode(string $code, array $data = []): \Model_EmailTemplate
    {
        $template = $this->di['db']->findOne('EmailTemplate', 'action_code = :action', [':action' => $code]);
        if ($template instanceof \Model_EmailTemplate) {
            $default = $this->getDefaultTemplate($code, $data);
            if ($default !== null && !$this->isCustomTemplate($template)) {
                $this->syncBuiltinTemplateMetadata($template, $default);
            }

            return $template;
        }

        return $this->createTemplateRecordFromData($code, $data);
    }

    private function syncBuiltinTemplateMetadata(\Model_EmailTemplate $template, array $default): void
    {
        $updated = false;

        if (($template->category === null || $template->category === '') && $default['category'] !== null) {
            $template->category = $default['category'];
            $updated = true;
        }

        if (($template->description === null || $template->description === '') && $default['description'] !== null) {
            $template->description = $default['description'];
            $updated = true;
        }

        if ($template->enabled === null || $template->enabled === '') {
            $template->enabled = $default['enabled'];
            $updated = true;
        }

        if (!isset($template->is_custom) || $template->is_custom === '') {
            $template->is_custom = self::BUILTIN_TEMPLATE;
            $updated = true;
        }

        if (isset($template->is_overridden)) {
            $isOverridden = !empty($template->is_overridden);
        } else {
            // Determine override status by comparing against file-backed defaults
            $isOverridden = ($template->subject !== $default['subject'])
                || ($template->content !== $default['content']);
            $template->is_overridden = $isOverridden ? 1 : 0;
            $updated = true;
        }

        if (!$isOverridden) {
            if ($template->subject !== $default['subject']) {
                $template->subject = $default['subject'];
                $updated = true;
            }
            if ($template->content !== $default['content']) {
                $template->content = $default['content'];
                $updated = true;
            }
        }

        if ($updated) {
            $this->di['db']->store($template);
        }
    }

    private function getEffectiveTemplateParts(\Model_EmailTemplate $template): array
    {
        if ($this->isCustomTemplate($template)) {
            return [$template->subject ?? '', $template->content ?? ''];
        }

        if ($template->subject !== null && $template->content !== null) {
            return [$template->subject, $template->content];
        }

        $default = $this->getDefaultTemplate($template->action_code);
        if ($default !== null) {
            $subject = $template->subject ?? $default['subject'];
            $content = $template->content ?? $default['content'];

            return [$subject ?? '', $content ?? ''];
        }

        return [$template->subject ?? '', $template->content ?? ''];
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

    private function _getVarsString(): string
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

    private function _parse(\Model_EmailTemplate $t, $vars): array
    {
        $systemService = $this->di['mod_service']('System');
        [$subjectTemplate, $contentTemplate] = $this->getEffectiveTemplateParts($t);
        $pc = $systemService->renderString($contentTemplate, false, $vars);
        $ps = $systemService->renderString($subjectTemplate, false, $vars);

        return [$ps, $pc];
    }

    public function resend(\Model_ActivityClientEmail $email): bool
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

    public function templateGetSearchQuery($data): array
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

    public function queueGetSearchQuery($data): array
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
        $isCustom = $this->isCustomTemplate($model);
        [$subject, $content] = $this->getEffectiveTemplateParts($model);

        $isOverridden = false;
        if (!$isCustom) {
            if (isset($model->is_overridden)) {
                $isOverridden = !empty($model->is_overridden);
            } else {
                $isOverridden = ($model->subject !== null && $model->subject !== '')
                    || ($model->content !== null && $model->content !== '');
            }
        }

        $data = [
            'id' => $model->id,
            'action_code' => $model->action_code,
            'category' => $model->category,
            'enabled' => $model->enabled,
            'subject' => $subject,
            'description' => $model->description,
            'is_custom' => $isCustom,
            'has_default' => !$isCustom && $this->hasDefaultTemplate((string) $model->action_code),
            'is_overridden' => $isOverridden,
        ];
        if ($deep) {
            $data['content'] = $content;
            $data['vars'] = $this->getVars($model);
            $data['subject_override'] = $isCustom ? null : $model->subject;
            $data['content_override'] = $isCustom ? null : $model->content;
        }

        return $data;
    }

    public function updateTemplate(\Model_EmailTemplate $model, $enabled = null, $category = null, $subject = null, $content = null): bool
    {
        if (isset($enabled)) {
            $model->enabled = $enabled;
        }

        if (isset($category)) {
            $model->category = $category;
        }

        $systemService = $this->di['mod_service']('System');
        $vars = $this->getVars($model);

        $isCustom = $this->isCustomTemplate($model);
        $default = $isCustom ? null : $this->getDefaultTemplate((string) $model->action_code);

        if (isset($subject)) {
            $vars['_tpl'] = $subject;
            $systemService->renderString($subject, false, $vars);
            $model->subject = $subject;
        }

        if (isset($content)) {
            $vars['_tpl'] = $content;
            $systemService->renderString($content, false, $vars);
            $model->content = $content;
        }

        if (!$isCustom && $default !== null) {
            $subjectMatches = $model->subject === $default['subject'];
            $contentMatches = $model->content === $default['content'];
            $model->is_overridden = ($subjectMatches && $contentMatches) ? 0 : 1;
        }

        $this->di['db']->store($model);
        $this->di['logger']->info('Updated email template #%s', $model->id);

        return true;
    }

    public function resetTemplateByCode($code): bool
    {
        $default = $this->getDefaultTemplate((string) $code);
        if ($default === null) {
            throw new \FOSSBilling\Exception('Email template :code does not have a file-backed default', [':code' => $code]);
        }

        $t = $this->getOrCreateTemplateByCode((string) $code, ['code' => $code]);
        if ($this->isCustomTemplate($t)) {
            throw new \FOSSBilling\Exception('Custom email template :code cannot be reset to a default', [':code' => $code]);
        }

        $t->subject = $default['subject'];
        $t->content = $default['content'];
        $t->is_overridden = 0;
        $this->di['db']->store($t);
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
        $model->is_custom = self::CUSTOM_TEMPLATE;

        $modelId = $this->di['db']->store($model);

        $this->di['logger']->info('Added new email template #%s', $modelId);

        return $model;
    }

    public function templateBatchGenerate(): bool
    {
        $extensionService = $this->di['mod_service']('extension');

        $finder = new Finder();
        $finder = $finder->files()->in(PATH_MODS . '/*/templates/email/')->name('*.html.twig');

        foreach ($finder as $file) {
            $code = $file->getBasename('.html.twig');
            $module = strtolower(Path::getFilenameWithoutExtension(Path::getDirectory(Path::getDirectory($file->getPath()))));

            // Skip if module is not active.
            if (!$extensionService->isExtensionActive('mod', $module)) {
                continue;
            }

            $template = $this->di['db']->findOne('EmailTemplate', 'action_code = :code', [':code' => $code]);
            $default = $this->getDefaultTemplate($code, ['code' => $code]);
            if ($default === null) {
                continue;
            }

            if (!$template instanceof \Model_EmailTemplate) {
                $this->createBuiltinTemplateRecord($code, $default);

                continue;
            }

            if (!$this->isCustomTemplate($template)) {
                $this->syncBuiltinTemplateMetadata($template, $default);
            }
        }

        $this->di['logger']->info('Synced file-backed email templates for installed modules.');

        return true;
    }

    public function templateBatchDisable(): bool
    {
        $this->templateBatchGenerate();
        $sql = 'UPDATE email_template SET enabled = 0 WHERE 1';
        $this->di['db']->exec($sql);
        $this->di['logger']->info('Disabled all email templates');

        return true;
    }

    public function templateBatchEnable(): bool
    {
        $this->templateBatchGenerate();
        $sql = 'UPDATE email_template SET enabled = 1 WHERE 1';
        $this->di['db']->exec($sql);
        $this->di['logger']->info('Enabled all email templates');

        return true;
    }

    public function getTemplate(int $id): \Model_EmailTemplate
    {
        $model = $this->di['db']->findOne('EmailTemplate', 'id = ?', [$id]);
        if (!$model instanceof \Model_EmailTemplate) {
            throw new \FOSSBilling\Exception('Email template not found');
        }

        if (!$this->isCustomTemplate($model)) {
            $default = $this->getDefaultTemplate((string) $model->action_code);
            if ($default !== null) {
                $this->syncBuiltinTemplateMetadata($model, $default);
            }
        }

        return $model;
    }

    public function getTemplateList(array $data = []): array
    {
        $where = [];
        $bindings = [];

        if (isset($data['code']) && $data['code'] !== '') {
            $where[] = 'action_code LIKE :code';
            $bindings[':code'] = '%' . $data['code'] . '%';
        }

        if (isset($data['search']) && $data['search'] !== '') {
            $searchParam = '%' . $data['search'] . '%';
            $where[] = '(action_code LIKE :search_action_code'
                . ' OR COALESCE(subject, \'\') LIKE :search_subject'
                . ' OR COALESCE(content, \'\') LIKE :search_content'
                . ' OR COALESCE(category, \'\') LIKE :search_category'
                . ' OR COALESCE(description, \'\') LIKE :search_description)';
            $bindings[':search_action_code'] = $searchParam;
            $bindings[':search_subject'] = $searchParam;
            $bindings[':search_content'] = $searchParam;
            $bindings[':search_category'] = $searchParam;
            $bindings[':search_description'] = $searchParam;
        }

        $request = $this->di['request'] ?? null;
        $page = max(1, (int) ($data['page'] ?? $request?->query->get('page', 1) ?? 1));
        $perPage = (int) ($data['per_page'] ?? $request?->query->get('per_page', $this->di['pager']->getDefaultPerPage()) ?? $this->di['pager']->getDefaultPerPage());
        $perPage = max(1, $perPage);

        // getCell() requires raw SQL with the physical table name; model-aware methods
        // (find/findOne) accept the RedBeanPHP model name ('EmailTemplate').
        $countSql = 'SELECT COUNT(*) FROM email_template';
        if (!empty($where)) {
            $countSql .= ' WHERE ' . implode(' AND ', $where);
        }
        $total = (int) $this->di['db']->getCell($countSql, $bindings);

        $offset = ($page - 1) * $perPage;

        $condition = (!empty($where) ? implode(' AND ', $where) . ' ' : '')
            . sprintf('ORDER BY category ASC, action_code ASC LIMIT %u OFFSET %u', $perPage, $offset);

        $templates = $this->di['db']->find('EmailTemplate', $condition, $bindings);

        $list = [];
        foreach ($templates as $template) {
            if (!$template instanceof \Model_EmailTemplate) {
                continue;
            }
            $list[] = $this->templateToApiArray($template, false);
        }

        return [
            'pages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'list' => $list,
        ];
    }

    /**
     * Sends emails from queue, respecting the configured time limit and emails per cron limit.
     * If an email fails to be sent, it will be skipped and retried on the next cron run until the retry limit is reached.
     */
    public function batchSend(): void
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

    private function _sendFromQueue(\Model_ModEmailQueue $queue, bool $throw_exceptions = false): bool
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
