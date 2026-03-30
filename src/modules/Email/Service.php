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

use Box\Mod\Email\Entity\EmailLog;
use Box\Mod\Email\Entity\EmailQueue;
use Box\Mod\Email\Entity\EmailTemplate;
use Box\Mod\Email\Repository\EmailLogRepository;
use Box\Mod\Email\Repository\EmailQueueRepository;
use Box\Mod\Email\Repository\EmailTemplateRepository;
use FOSSBilling\Config;
use FOSSBilling\Environment;
use FOSSBilling\Tools;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class Service implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    protected ?EmailLogRepository $emailLogRepository = null;
    protected ?EmailQueueRepository $emailQueueRepository = null;
    protected ?EmailTemplateRepository $templateRepository = null;
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

    public function getTemplateRepository(): EmailTemplateRepository
    {
        if ($this->templateRepository === null) {
            if ($this->di === null) {
                throw new \FOSSBilling\Exception('The dependency injection container has not been set.');
            }
            $this->templateRepository = $this->di['em']->getRepository(EmailTemplate::class);
        }

        return $this->templateRepository;
    }

    public function getEmailLogRepository(): EmailLogRepository
    {
        if ($this->emailLogRepository === null) {
            if ($this->di === null) {
                throw new \FOSSBilling\Exception('The dependency injection container has not been set.');
            }
            $this->emailLogRepository = $this->di['em']->getRepository(EmailLog::class);
        }

        return $this->emailLogRepository;
    }

    public function getEmailQueueRepository(): EmailQueueRepository
    {
        if ($this->emailQueueRepository === null) {
            if ($this->di === null) {
                throw new \FOSSBilling\Exception('The dependency injection container has not been set.');
            }
            $this->emailQueueRepository = $this->di['em']->getRepository(EmailQueue::class);
        }

        return $this->emailQueueRepository;
    }

    public function getModulePermissions(): array
    {
        return [
            'can_always_access' => true,
            'manage_settings' => [],
        ];
    }

    public static function onBeforeAdminCronRun(\Box_Event $event): void
    {
        $di = $event->getDi();
        $config = $di['mod_service']('extension')->getConfig('mod_email');
        $retentionDays = (int) ($config['log_retention_days'] ?? 0);

        if ($retentionDays === 0) {
            return;
        }

        try {
            /** @var self $service */
            $service = $di['mod_service']('email');
            $service->cleanupOldLogs($retentionDays);
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
    }

    public function getEmailLogList(array $data = []): array
    {
        $qb = $this->getEmailLogRepository()->getSearchQueryBuilder($data);
        $page = isset($data['page']) ? (int) $data['page'] : null;
        $perPage = isset($data['per_page']) ? (int) $data['per_page'] : null;
        $result = $this->di['pager']->paginateDoctrineQuery($qb, $perPage, $page);

        $list = [];
        foreach ($result['list'] as $emailLogRow) {
            if ($emailLogRow instanceof EmailLog) {
                $emailLogRow = $emailLogRow->toApiArray();
            }

            if (!is_array($emailLogRow)) {
                continue;
            }

            $list[] = $this->normalizeEmailLogApiArray($emailLogRow);
        }
        $result['list'] = $list;

        return $result;
    }

    public function findOneForClientById(\Model_Client $client, $id): ?EmailLog
    {
        return $this->getEmailLogRepository()->findOneForClientById((int) $client->id, (int) $id);
    }

    public function rmByClient(\Model_Client $client): bool
    {
        $this->getEmailLogRepository()->deleteByClientId((int) $client->id);

        return true;
    }

    public function rm(EmailLog $email): bool
    {
        $this->di['em']->remove($email);
        $this->di['em']->flush();

        return true;
    }

    public function toApiArray(EmailLog $model, $deep = true): array
    {
        return $this->normalizeEmailLogApiArray($model->toApiArray());
    }

    public function setVars(EmailTemplate $template, array $vars): bool
    {
        $template->setVars($this->di['crypt']->encrypt(json_encode($vars), Config::getProperty('info.salt')));
        $this->di['em']->flush();

        return true;
    }

    public function getVars(EmailTemplate $template): array
    {
        $json = $this->di['crypt']->decrypt($template->getVars(), Config::getProperty('info.salt'));

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

        $template = $this->getOrCreateTemplateByCode($data['code'], $data);

        $this->setVars($template, $vars);

        // do not send inactive template
        if (!$template->isEnabled()) {
            return false;
        }
        $systemService = $this->di['mod_service']('system');

        [$subject, $content] = $this->_parse($template, $vars);

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

    private function isCustomTemplate(EmailTemplate $template): bool
    {
        return $template->isCustom();
    }

    private function createBuiltinTemplateRecord(string $code, array $default): EmailTemplate
    {
        $template = new EmailTemplate($code);
        $template->setCategory($default['category'])
            ->setDescription($default['description'])
            ->setEnabled((bool) $default['enabled'])
            ->setIsCustom(false)
            ->setIsOverridden(false)
            ->setSubject($default['subject'])
            ->setContent($default['content']);

        $em = $this->di['em'];
        $em->persist($template);
        $em->flush();

        return $template;
    }

    private function createTemplateRecordFromData(string $code, array $data): EmailTemplate
    {
        $default = $this->getDefaultTemplate($code, $data);
        if ($default !== null) {
            return $this->createBuiltinTemplateRecord($code, $default);
        }

        [$subject, $content, $description, $enabled, $category] = $this->_getDefaults($data);

        $template = new EmailTemplate($code);
        $template->setCategory($category)
            ->setEnabled((bool) $enabled)
            ->setDescription($description)
            ->setSubject($subject)
            ->setContent($content)
            ->setIsCustom(true);

        $em = $this->di['em'];
        $em->persist($template);
        $em->flush();

        return $template;
    }

    private function getOrCreateTemplateByCode(string $code, array $data = []): EmailTemplate
    {
        $template = $this->getTemplateRepository()->findOneByActionCode($code);
        if ($template instanceof EmailTemplate) {
            $default = $this->getDefaultTemplate($code, $data);
            if ($default !== null && !$this->isCustomTemplate($template)) {
                $this->syncBuiltinTemplateMetadata($template, $default);
            }

            return $template;
        }

        return $this->createTemplateRecordFromData($code, $data);
    }

    private function syncBuiltinTemplateMetadata(EmailTemplate $template, array $default): void
    {
        $updated = false;

        if (($template->getCategory() === null || $template->getCategory() === '') && $default['category'] !== null) {
            $template->setCategory($default['category']);
            $updated = true;
        }

        if (($template->getDescription() === null || $template->getDescription() === '') && $default['description'] !== null) {
            $template->setDescription($default['description']);
            $updated = true;
        }

        $isOverridden = $template->isOverridden();
        if (!$isOverridden) {
            if ($template->getSubject() !== $default['subject']) {
                $template->setSubject($default['subject']);
                $updated = true;
            }
            if ($template->getContent() !== $default['content']) {
                $template->setContent($default['content']);
                $updated = true;
            }
        }

        if ($updated) {
            $this->di['em']->flush();
        }
    }

    private function getEffectiveTemplateParts(EmailTemplate $template): array
    {
        if ($this->isCustomTemplate($template)) {
            return [$template->getSubject() ?? '', $template->getContent() ?? ''];
        }

        if ($template->getSubject() !== null && $template->getContent() !== null) {
            return [$template->getSubject(), $template->getContent()];
        }

        $default = $this->getDefaultTemplate($template->getActionCode());
        if ($default !== null) {
            $subject = $template->getSubject() ?? $default['subject'];
            $content = $template->getContent() ?? $default['content'];

            return [$subject ?? '', $content ?? ''];
        }

        return [$template->getSubject() ?? '', $template->getContent() ?? ''];
    }

    private function _queue($to, $from, $subject, $content, $to_name = null, $from_name = null, $client_id = null, $admin_id = null): EmailQueue
    {
        $queue = new EmailQueue($to, $from, $subject, $content);
        $queue->setToName($to_name);
        $queue->setFromName($from_name);
        $queue->setClientId($client_id !== null ? (int) $client_id : null);
        $queue->setAdminId($admin_id !== null ? (int) $admin_id : null);

        try {
            $em = $this->di['em'];
            $em->persist($queue);
            $em->flush();
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

    private function _parse(EmailTemplate $template, array $vars): array
    {
        $systemService = $this->di['mod_service']('System');
        [$subjectTemplate, $contentTemplate] = $this->getEffectiveTemplateParts($template);
        $pc = $systemService->renderString($contentTemplate, false, $vars);
        $ps = $systemService->renderString($subjectTemplate, false, $vars);

        return [$ps, $pc];
    }

    public function logEmail($subject, $client_id = null, $sender = null, $recipients = null, $content_html = null, $content_text = null): bool
    {
        $emailLog = (new EmailLog())
            ->setClientId($client_id !== null ? (int) $client_id : null)
            ->setSender($sender)
            ->setRecipients($recipients)
            ->setSubject($subject)
            ->setContentHtml($content_html)
            ->setContentText($content_text);

        $this->di['em']->persist($emailLog);
        $this->di['em']->flush();

        return true;
    }

    public function cleanupOldLogs(int $retentionDays): int
    {
        if ($retentionDays <= 0) {
            return 0;
        }

        $cutoff = new \DateTime(sprintf('-%d days', $retentionDays));

        return $this->getEmailLogRepository()->deleteOlderThan($cutoff);
    }

    public function resend(EmailLog $email): bool
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
        $customer = $clientService->get(['id' => $email->getClientId()]);
        $customer = $clientService->toApiArray($customer);

        $systemService = $this->di['mod_service']('system');
        $from_name = $systemService->getParamValue('company_name');

        $this->sendMail(
            $email->getRecipients(),
            $email->getSender(),
            $email->getSubject(),
            $email->getContentHtml(),
            $customer['first_name'] . ' ' . $customer['last_name'],
            $from_name,
            $email->getClientId()
        );

        $this->di['logger']->info('Resent email #%s', $email->getId());

        return true;
    }

    public function getQueueList(array $data = []): array
    {
        $qb = $this->getEmailQueueRepository()->getSearchQueryBuilder($data);
        $page = isset($data['page']) ? (int) $data['page'] : null;
        $perPage = isset($data['per_page']) ? (int) $data['per_page'] : null;
        $result = $this->di['pager']->paginateDoctrineQuery($qb, $perPage, $page);

        $list = [];
        foreach ($result['list'] as $emailQueueRow) {
            if ($emailQueueRow instanceof EmailQueue) {
                $emailQueueRow = $emailQueueRow->toApiArray();
            }

            if (!is_array($emailQueueRow)) {
                continue;
            }

            $list[] = [
                'id' => $emailQueueRow['id'] ?? '',
                'recipient' => $emailQueueRow['recipient'] ?? '',
                'subject' => $emailQueueRow['subject'] ?? '',
                'content' => $emailQueueRow['content'] ?? '',
                'to_name' => $emailQueueRow['to_name'] ?? '',
                'status' => $emailQueueRow['status'] ?? '',
                'tries' => $emailQueueRow['tries'] ?? '',
                'created_at' => $emailQueueRow['created_at'] ?? '',
                'updated_at' => $emailQueueRow['updated_at'] ?? '',
            ];
        }
        $result['list'] = $list;

        return $result;
    }

    public function templateToApiArray(EmailTemplate $template, bool $deep = false): array
    {
        $isCustom = $this->isCustomTemplate($template);
        [$subject, $content] = $this->getEffectiveTemplateParts($template);

        $isOverridden = false;
        if (!$isCustom) {
            $isOverridden = $template->isOverridden();
        }

        $data = [
            'id' => $template->getId(),
            'action_code' => $template->getActionCode(),
            'category' => $template->getCategory(),
            'enabled' => $template->isEnabled(),
            'subject' => $subject,
            'description' => $template->getDescription(),
            'is_custom' => $isCustom,
            'has_default' => !$isCustom && $this->hasDefaultTemplate($template->getActionCode()),
            'is_overridden' => $isOverridden,
        ];
        if ($deep) {
            $data['content'] = $content;
            $data['vars'] = $this->getVars($template);
            $data['subject_override'] = $isCustom ? null : $template->getSubject();
            $data['content_override'] = $isCustom ? null : $template->getContent();
        }

        return $data;
    }

    private function templateRowToApiArray(array $template): array
    {
        $actionCode = (string) ($template['action_code'] ?? '');
        $isCustom = (bool) ($template['is_custom'] ?? false);
        $isOverridden = !$isCustom && (bool) ($template['is_overridden'] ?? false);
        $default = (!$isCustom && $actionCode !== '') ? $this->getDefaultTemplate($actionCode) : null;

        $subject = $template['subject'] ?? null;
        $description = $template['description'] ?? null;
        $category = $template['category'] ?? null;

        if ($default !== null) {
            $category ??= $default['category'];
            $description ??= $default['description'];

            if (!$isOverridden || $subject === null || $subject === '') {
                $subject = $default['subject'];
            }
        }

        return [
            'id' => $template['id'] ?? null,
            'action_code' => $actionCode,
            'category' => $category,
            'enabled' => (bool) ($template['enabled'] ?? false),
            'subject' => $subject ?? '',
            'description' => $description,
            'is_custom' => $isCustom,
            'has_default' => !$isCustom && $default !== null,
            'is_overridden' => $isOverridden,
        ];
    }

    public function updateTemplate(EmailTemplate $template, $enabled = null, $category = null, $subject = null, $content = null): bool
    {
        if (isset($enabled)) {
            $template->setEnabled((bool) $enabled);
        }

        if (isset($category)) {
            $template->setCategory($category);
        }

        $systemService = $this->di['mod_service']('System');
        $vars = $this->getVars($template);

        $isCustom = $this->isCustomTemplate($template);
        $default = $isCustom ? null : $this->getDefaultTemplate($template->getActionCode());

        if (isset($subject)) {
            $vars['_tpl'] = $subject;
            $systemService->renderString($subject, false, $vars);
            $template->setSubject($subject);
        }

        if (isset($content)) {
            $vars['_tpl'] = $content;
            $systemService->renderString($content, false, $vars);
            $template->setContent($content);
        }

        if (!$isCustom && $default !== null) {
            $subjectMatches = $template->getSubject() === $default['subject'];
            $contentMatches = $template->getContent() === $default['content'];
            $template->setIsOverridden(!($subjectMatches && $contentMatches));
        }

        $this->di['em']->flush();
        $this->di['logger']->info('Updated email template #%s', $template->getId());

        return true;
    }

    public function resetTemplateByCode($code): bool
    {
        $default = $this->getDefaultTemplate((string) $code);
        if ($default === null) {
            throw new \FOSSBilling\Exception('Email template :code does not have a file-backed default', [':code' => $code]);
        }

        $template = $this->getOrCreateTemplateByCode((string) $code, ['code' => $code]);
        if ($this->isCustomTemplate($template)) {
            throw new \FOSSBilling\Exception('Custom email template :code cannot be reset to a default', [':code' => $code]);
        }

        $template->setSubject($default['subject'])
            ->setContent($default['content'])
            ->setIsOverridden(false);
        $this->di['em']->flush();
        $this->di['logger']->info('Reset email template: %s', $template->getActionCode());

        return true;
    }

    public function getEmailById($id)
    {
        $model = $this->getEmailLogRepository()->find((int) $id);
        if (!$model instanceof EmailLog) {
            throw new \FOSSBilling\Exception('Email not found');
        }

        return $model;
    }

    public function templateCreate($actionCode, $subject, $content, $enabled = 0, $category = null): EmailTemplate
    {
        $template = new EmailTemplate($actionCode);
        $template->setSubject($subject)
            ->setEnabled((bool) $enabled)
            ->setCategory($category)
            ->setContent($content)
            ->setIsCustom(true);

        $em = $this->di['em'];
        $em->persist($template);
        $em->flush();

        $this->di['logger']->info('Added new email template #%s', $template->getId());

        return $template;
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

            $template = $this->getTemplateRepository()->findOneByActionCode($code);
            $default = $this->getDefaultTemplate($code, ['code' => $code]);
            if ($default === null) {
                continue;
            }

            if (!$template instanceof EmailTemplate) {
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
        $this->getTemplateRepository()->setAllEnabled(false);
        $this->di['logger']->info('Disabled all email templates');

        return true;
    }

    public function templateBatchEnable(): bool
    {
        $this->templateBatchGenerate();
        $this->getTemplateRepository()->setAllEnabled(true);
        $this->di['logger']->info('Enabled all email templates');

        return true;
    }

    public function getTemplate(int $id): EmailTemplate
    {
        $template = $this->getTemplateRepository()->find($id);
        if (!$template instanceof EmailTemplate) {
            throw new \FOSSBilling\Exception('Email template not found');
        }

        if (!$this->isCustomTemplate($template)) {
            $default = $this->getDefaultTemplate($template->getActionCode());
            if ($default !== null) {
                $this->syncBuiltinTemplateMetadata($template, $default);
            }
        }

        return $template;
    }

    public function getTemplateList(array $data = []): array
    {
        $qb = $this->getTemplateRepository()->getSearchQueryBuilder($data);
        $page = isset($data['page']) ? (int) $data['page'] : null;
        $perPage = isset($data['per_page']) ? (int) $data['per_page'] : null;
        $result = $this->di['pager']->paginateDoctrineQuery($qb, $perPage, $page);

        $list = [];
        foreach ($result['list'] as $templateRow) {
            if ($templateRow instanceof EmailTemplate) {
                $templateRow = $templateRow->toApiArray();
            }

            if (!is_array($templateRow)) {
                continue;
            }

            $list[] = $this->templateRowToApiArray($templateRow);
        }
        $result['list'] = $list;

        return $result;
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
        $sendPerCron = ($settings['queue_once'] ?? 0) > 0 ? (int) $settings['queue_once'] : null;

        $start = time();

        $mailQueue = $this->getEmailQueueRepository()->findUnsent($sendPerCron);

        foreach ($mailQueue as $email) {
            $this->_sendFromQueue($email);
            if ($time_limit && time() - $start > $time_limit) {
                break;
            }
        }
    }

    private function _sendFromQueue(EmailQueue $queue, bool $throw_exceptions = false): bool
    {
        $extensionService = $this->di['mod_service']('extension');
        if ($extensionService->isExtensionActive('mod', 'demo')) {
            return false;
        }
        $queue->setStatus(EmailQueue::STATUS_SENDING);
        $this->di['em']->flush();

        $content = $queue->getContent() . PHP_EOL;

        $mod = $this->di['mod']('email');
        $settings = $mod->getConfig();
        $log = isset($settings['log_enabled']) && $settings['log_enabled'];

        $transport = $settings['mailer'] ?? 'sendmail';
        $sender = [
            'email' => $queue->getSender(),
            'name' => $queue->getFromName(),
        ];
        $recipient = [
            'email' => $queue->getRecipient(),
            'name' => $queue->getToName(),
        ];

        try {
            $mail = new \FOSSBilling\Mail($sender, $recipient, $queue->getSubject(), $content, $transport, $settings['custom_dsn'] ?? null);

            if (!Environment::isProduction()) {
                $this->di['logger']->setChannel('email')->info('Skip email sending. Application ENV: ' . Environment::getCurrentEnvironment());

                return true;
            }

            $mail->send($settings);

            // It sent without causing an exception (error), so we are safe to log it now
            if ($log) {
                $this->logEmail($queue->getSubject(), $queue->getClientId(), $queue->getSender(), $queue->getRecipient(), $queue->getContent());
            }

            try {
                $em = $this->di['em'];
                $em->remove($queue);
                $em->flush();
            } catch (\Exception $e) {
                $this->di['logger']->setChannel('email')->err($e->getMessage());
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->di['logger']->setChannel('email')->err($e->getMessage());

            $queue->decrementPriority();
            $queue->setStatus(EmailQueue::STATUS_UNSENT);
            $queue->incrementTries();
            $queue->setUpdatedAt(new \DateTime());
            $this->di['em']->flush();

            $maxTries = $settings['cancel_after'] ?? 5;
            if ($queue->getTries() > $maxTries) {
                // The email failed to send after the max number of tries. This might be because of a server error, so let's be sure to log it which gives the client the ability to resend it.
                if ($log) {
                    $this->logEmail($queue->getSubject(), $queue->getClientId(), $queue->getSender(), $queue->getRecipient(), $queue->getContent());
                }
                $em = $this->di['em'];
                $em->remove($queue);
                $em->flush();
            }

            if ($throw_exceptions) {
                // If the error message is long, truncate it and inform the user the rest is in the error log.
                $truncated = (strlen($message) > 350) ? __trans('Error message truncated due to length, please check the error log for the complete message: ') . substr($message, 0, 350) . '...' : $message;

                throw new \FOSSBilling\Exception($truncated);
            }
        }

        return true;
    }

    private function normalizeEmailLogApiArray(array $emailLog): array
    {
        return [
            'id' => $emailLog['id'] ?? null,
            'client_id' => $emailLog['client_id'] ?? null,
            'sender' => $emailLog['sender'] ?? '',
            'recipients' => $emailLog['recipients'] ?? '',
            'subject' => $emailLog['subject'] ?? '',
            'content_html' => Tools::sanitizeContent($emailLog['content_html'] ?? ''),
            'content_text' => $emailLog['content_text'] ?? '',
            'created_at' => $emailLog['created_at'] ?? null,
        ];
    }
}
